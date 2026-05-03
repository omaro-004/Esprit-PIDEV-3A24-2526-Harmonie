<?php

namespace App\Controller;

use App\Form\ProfileFormType;
use App\Form\SecuritySettingsFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    /**
     * Page profil (lecture) — redirige vers settings
     */
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_profile_settings');
    }

    /**
     * Page settings avec onglets Edit profile / Security
     * Section : Edit profile (informations personnelles + avatar)
     */
    #[Route('/profile/settings', name: 'app_profile_settings', methods: ['GET', 'POST'])]
    public function settings(
        Request                $request,
        EntityManagerInterface $em,
        SluggerInterface       $slugger
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        $preSelectedPath = trim((string) $request->request->get('preSelectedAvatarPath', ''));
        $avatarFile = $profileForm->get('avatarFile')->getData();

        if ($profileForm->isSubmitted() && !$avatarFile && '' !== $preSelectedPath) {
            $allowedPrefixes = ['avatars/default/avatar_', 'user_images/ai_avatar_'];
            $isValid = false;

            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($preSelectedPath, $prefix)) {
                    $isValid = true;
                    break;
                }
            }

            if ($isValid) {
                $user->setUserImagePath($preSelectedPath);
            }
        }

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            // ── Gestion avatar ────────────────────────────────────────────
            if ($avatarFile) {
                $safeFilename = $slugger->slug($user->getUserNom());
                $newFilename  = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();
                $uploadDir    = $this->getParameter('kernel.project_dir') . '/public/user_images';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                try {
                    $avatarFile->move($uploadDir, $newFilename);
                    $user->setUserImagePath('user_images/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', "Erreur lors de l'upload de l'image.");
                }
            }

            $em->flush();
            $this->addFlash('success_profile', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_profile_settings');
        }

        // Créer le formulaire sécurité vide (pour l'affichage de l'onglet)
        $securityForm = $this->createForm(SecuritySettingsFormType::class);

        return $this->render('profile/settings.html.twig', [
            'user'         => $user,
            'profileForm'  => $profileForm->createView(),
            'securityForm' => $securityForm->createView(),
            'activeTab'    => 'profile',
        ]);
    }

    /**
     * Section : Security (changement email + mot de passe)
     */
    #[Route('/profile/security', name: 'app_profile_security', methods: ['GET', 'POST'])]
    public function security(
        Request                         $request,
        EntityManagerInterface          $em,
        UserPasswordHasherInterface     $hasher,
        \Symfony\Component\Security\Http\Authentication\AuthenticationUtils $authUtils,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $securityForm = $this->createForm(SecuritySettingsFormType::class);
        $securityForm->handleRequest($request);

        if ($securityForm->isSubmitted() && $securityForm->isValid()) {

            // ── CORRECTION 1 : lire chaque champ individuellement ──────────────
            // getData() renvoie null quand data_class=null + tous les champs mapped=false.
            // Il faut passer par ->get('nom')->getData() sur chaque champ.
            $newEmail   = trim($securityForm->get('newEmail')->getData() ?? '');
            $currentPwd = $securityForm->get('currentPassword')->getData() ?? '';
            $newPwd     = $securityForm->get('newPassword')->getData() ?? '';

            $hasChanges      = false;
            $errors          = [];
            $emailChanged    = false;
            $passwordChanged = false;

            // ── Changement d'email ────────────────────────────────────────────
            if ($newEmail !== '' && $newEmail !== $user->getUserEmail()) {
                $user->setUserEmail($newEmail);
                $hasChanges   = true;
                $emailChanged = true;
            }

            // ── Changement de mot de passe ────────────────────────────────────
            if ($newPwd !== '') {
                if (!$hasher->isPasswordValid($user, $currentPwd)) {
                    $errors[] = 'Le mot de passe actuel est incorrect.';
                } else {
                    $hashed = $hasher->hashPassword($user, $newPwd);
                    $user->setUserPassword($hashed);
                    $hasChanges      = true;
                    $passwordChanged = true;
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $err) {
                    $this->addFlash('error_security', $err);
                }
            } elseif ($hasChanges) {
                $em->flush();

                // ── CORRECTION 2 : régénération de la session ──────────────────
                // Après changement d'email (= userIdentifier) ou de password,
                // le token Symfony en session devient périmé.
                // On invalide la session et on redirige vers le login avec un message.
                $request->getSession()->invalidate();
                $this->addFlash('success', 'Sécurité mise à jour. Veuillez vous reconnecter.');
                return $this->redirectToRoute('app_login');
            }

            return $this->redirectToRoute('app_profile_security');
        }

        $profileForm = $this->createForm(ProfileFormType::class, $user);

        return $this->render('profile/settings.html.twig', [
            'user'         => $user,
            'profileForm'  => $profileForm->createView(),
            'securityForm' => $securityForm->createView(),
            'activeTab'    => 'security',
        ]);
    }

    /**
     * Ancienne route kept pour compatibilité (redirige vers settings)
     */
    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(): Response
    {
        return $this->redirectToRoute('app_profile_settings');
    }
    #[Route('/profile/2fa', name: 'app_profile_2fa', methods: ['GET'])]
    public function twofa(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $profileForm  = $this->createForm(ProfileFormType::class, $user);
        $securityForm = $this->createForm(SecuritySettingsFormType::class);

        return $this->render('profile/settings.html.twig', [
            'user'         => $user,
            'profileForm'  => $profileForm->createView(),
            'securityForm' => $securityForm->createView(),
            'activeTab'    => '2fa',
        ]);
    }
}
