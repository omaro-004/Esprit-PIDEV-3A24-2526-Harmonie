<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\RegistrationStep2FormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class RegistrationController extends AbstractController
{
    // ── Étape 1 ───────────────────────────────────────────────────────────────
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain          = $form->get('plainPassword')->getData();
            $hashedPassword = $hasher->hashPassword($user, $plain);

            $request->getSession()->set('reg_step1', [
                'nom'           => $user->getUserNom(),
                'prenom'        => $user->getUserPrenom(),
                'email'         => $user->getUserEmail(),
                'password'      => $hashedPassword,
                'dateNaissance' => $user->getUserDateDeNaissance(),
            ]);

            return $this->redirectToRoute('app_register_step2');
        }

        return $this->render('registration/register_step1.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ── Étape 2 ───────────────────────────────────────────────────────────────
    #[Route('/register/step2', name: 'app_register_step2')]
    public function registerStep2(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        $session = $request->getSession();
        $step1   = $session->get('reg_step1');

        if (!$step1) {
            return $this->redirectToRoute('app_register');
        }

        $user = new User();
        $user->setUserNom($step1['nom']);
        $user->setUserPrenom($step1['prenom']);
        $user->setUserEmail($step1['email']);
        $user->setUserPassword($step1['password']);
        $user->setUserDateDeNaissance($step1['dateNaissance']);
        $user->setDateInscription((new \DateTime())->format('Y-m-d'));
        $user->setTypeUtilisateur('ETUDIANT');
        $user->setIsActive(true);

        $form = $this->createForm(RegistrationStep2FormType::class, $user);
        $form->handleRequest($request);

        $preSelectedPath = trim((string) $request->request->get('preSelectedAvatarPath', ''));
        $avatarFile = $form->get('avatarFile')->getData();

        if ($form->isSubmitted() && !$avatarFile && '' !== $preSelectedPath) {
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

        if ($form->isSubmitted() && $form->isValid()) {

            // Upload avatar
            if ($avatarFile) {
                $safeFilename = $slugger->slug($step1['nom']);
                $newFilename  = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();
                $uploadDir    = $this->getParameter('kernel.project_dir') . '/public/user_images';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                try {
                    $avatarFile->move($uploadDir, $newFilename);
                    $user->setUserImagePath('user_images/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', "Erreur upload image : " . $e->getMessage());
                }
            }

            // Stocker les données step2 en session pour step3
            $session->set('reg_step2_user', [
                'nom'                      => $user->getUserNom(),
                'prenom'                   => $user->getUserPrenom(),
                'email'                    => $user->getUserEmail(),
                'password'                 => $user->getUserPassword(),
                'dateNaissance'            => $user->getUserDateDeNaissance(),
                'sexe'                     => $user->getUserSexe(),
                'poids'                    => $user->getUserPoids(),
                'taille'                   => $user->getUserTaille(),
                'niveauActivite'           => $user->getUserNiveauActivitePhysique(),
                'niveauScolaire'           => $user->getUserNiveauScolaire(),
                'etablissement'            => $user->getUserEtablissementScolaire(),
                'imagePath'                => $user->getUserImagePath(),
                'dateInscription'          => $user->getDateInscription(),
            ]);

            return $this->redirectToRoute('app_register_step3');
        }

        return $this->render('registration/register_step2.html.twig', [
            'form'  => $form->createView(),
            'step1' => $step1,
        ]);
    }

    // ── Étape 3 : Capture faciale ─────────────────────────────────────────────
    #[Route('/register/step3', name: 'app_register_step3')]
    public function registerStep3(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        $session = $request->getSession();

        if (!$session->get('reg_step2_user')) {
            return $this->redirectToRoute('app_register');
        }

        return $this->render('registration/register_step3.html.twig');
    }

    // ── API : Sauvegarder l'image faciale et créer le compte ──────────────────
    #[Route('/register/save-face', name: 'app_register_save_face', methods: ['POST'])]
    public function saveFace(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $session  = $request->getSession();
        $userData = $session->get('reg_step2_user');

        if (!$userData) {
            return new JsonResponse(['error' => 'Session expirée'], 400);
        }

        $data      = json_decode($request->getContent(), true);
        $faceImage = $data['faceImage'] ?? null; // base64 PNG

        // Créer le user
        $user = new User();
        $user->setUserNom($userData['nom']);
        $user->setUserPrenom($userData['prenom']);
        $user->setUserEmail($userData['email']);
        $user->setUserPassword($userData['password']);
        $user->setUserDateDeNaissance($userData['dateNaissance']);
        $user->setUserSexe($userData['sexe']);
        $user->setUserPoids($userData['poids']);
        $user->setUserTaille($userData['taille'] ? (int)$userData['taille'] : null);
        $user->setUserNiveauActivitePhysique($userData['niveauActivite']);
        $user->setUserNiveauScolaire($userData['niveauScolaire']);
        $user->setUserEtablissementScolaire($userData['etablissement']);
        $user->setUserImagePath($userData['imagePath']);
        $user->setDateInscription($userData['dateInscription']);
        $user->setTypeUtilisateur('ETUDIANT');
        $user->setIsActive(true);

        // Sauvegarder l'image faciale
        if ($faceImage) {
            $faceDir = $this->getParameter('kernel.project_dir') . '/public/face_data';
            if (!is_dir($faceDir)) {
                mkdir($faceDir, 0777, true);
            }

            // Décoder le base64
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $faceImage);
            $imageData = base64_decode($imageData);

            $filename = 'face_' . uniqid() . '.png';
            file_put_contents($faceDir . '/' . $filename, $imageData);

            $user->setFaceImagePath('face_data/' . $filename);
            $user->setFaceIdEnabled(true); // Active le face ID par défaut si photo prise
        }

        try {
            $em->persist($user);
            $em->flush();

            // Nettoyer la session
            $session->remove('reg_step1');
            $session->remove('reg_step2_user');

            return new JsonResponse(['success' => true]);

        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $session->remove('reg_step1');
            $session->remove('reg_step2_user');
            return new JsonResponse(['error' => 'Email déjà utilisé.'], 409);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // ── API : Ignorer la capture faciale (inscription sans face ID) ───────────
    #[Route('/register/skip-face', name: 'app_register_skip_face', methods: ['POST'])]
    public function skipFace(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $session  = $request->getSession();
        $userData = $session->get('reg_step2_user');

        if (!$userData) {
            return new JsonResponse(['error' => 'Session expirée'], 400);
        }

        $user = new User();
        $user->setUserNom($userData['nom']);
        $user->setUserPrenom($userData['prenom']);
        $user->setUserEmail($userData['email']);
        $user->setUserPassword($userData['password']);
        $user->setUserDateDeNaissance($userData['dateNaissance']);
        $user->setUserSexe($userData['sexe']);
        $user->setUserPoids($userData['poids']);
        $user->setUserTaille($userData['taille'] ? (int)$userData['taille'] : null);
        $user->setUserNiveauActivitePhysique($userData['niveauActivite']);
        $user->setUserNiveauScolaire($userData['niveauScolaire']);
        $user->setUserEtablissementScolaire($userData['etablissement']);
        $user->setUserImagePath($userData['imagePath']);
        $user->setDateInscription($userData['dateInscription']);
        $user->setTypeUtilisateur('ETUDIANT');
        $user->setIsActive(true);
        $user->setFaceIdEnabled(false);

        try {
            $em->persist($user);
            $em->flush();

            $session->remove('reg_step1');
            $session->remove('reg_step2_user');

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
