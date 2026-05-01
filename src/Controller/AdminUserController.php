<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminEditUserFormType;
use App\Repository\UserRepository;
use App\Service\SuspicionScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository          $repo,
        private readonly EntityManagerInterface  $em,
        private readonly SuspicionScoreService   $suspicion,
    ) {}

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q    = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'suspicion');

        $users = $q
            ? $this->repo->searchByName($q)
            : $this->repo->findAllStudents();

        if ($sort === 'suspicion') {
            $users = $this->suspicion->sortBySuspicion($users);
        }

        $scores = [];
        foreach ($users as $u) {
            $s = $this->suspicion->compute($u);
            $scores[$u->getUserId()] = [
                'score' => $s,
                'label' => $this->suspicion->getLabel($s),
                'color' => $this->suspicion->getColor($s),
                'border'=> $this->suspicion->getBorderColor($s),
            ];
        }

        return $this->render('admin/users/index.html.twig', compact('users', 'scores', 'q', 'sort'));
    }

    #[Route('/search', name: 'admin_users_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q    = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'suspicion');

        $users = $q ? $this->repo->searchByName($q) : $this->repo->findAllStudents();

        if ($sort === 'suspicion') {
            $users = $this->suspicion->sortBySuspicion($users);
        }

        $data = array_map(fn(User $u) => [
            'id'     => $u->getUserId(),
            'nom'    => $u->getUserNom(),
            'prenom' => $u->getUserPrenom(),
            'email'  => $u->getUserEmail(),
            'active' => $u->isActive(),
            'score'  => $this->suspicion->compute($u),
            'label'  => $this->suspicion->getLabel($this->suspicion->compute($u)),
            'color'  => $this->suspicion->getColor($this->suspicion->compute($u)),
            'border' => $this->suspicion->getBorderColor($this->suspicion->compute($u)),
            'image'  => $u->getUserImagePath(),
            'date'   => $u->getDateInscription(),
        ], $users);

        return new JsonResponse($data);
    }

    #[Route('/suspended', name: 'admin_users_suspended', methods: ['GET'])]
    public function suspended(): Response
    {
        $users = $this->repo->findSuspendedStudents();
        return $this->render('admin/users/suspended.html.twig', compact('users'));
    }

    #[Route('/{id}', name: 'admin_users_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        $score = $this->suspicion->compute($user);
        return $this->render('admin/users/show.html.twig', [
            'user'       => $user,
            'score'      => $score,
            'scoreLabel' => $this->suspicion->getLabel($score),
            'scoreColor' => $this->suspicion->getColor($score),
            'breakdown'  => $this->suspicion->getBreakdown($user),
        ]);
    }

    /**
     * Edit user — inclut l'upload d'image de profil
     */
    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(AdminEditUserFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── Gestion de l'avatar ────────────────────────────────────────
            $avatarFile = $form->get('avatarFile')->getData();
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

            $this->em->flush();
            $this->addFlash('success', 'Compte mis à jour.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_users_toggle', methods: ['POST'])]
    public function toggle(User $user, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $user->getUserId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $this->em->flush();
            $action = $user->isActive() ? 'réactivé' : 'suspendu';
            $this->addFlash('success', "Compte {$action} avec succès.");
        }
        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/{id}/suspicion', name: 'admin_users_suspicion', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function suspicionDetail(User $user): JsonResponse
    {
        $score = $this->suspicion->compute($user);
        return new JsonResponse([
            'score'     => $score,
            'label'     => $this->suspicion->getLabel($score),
            'color'     => $this->suspicion->getColor($score),
            'breakdown' => $this->suspicion->getBreakdown($user),
        ]);
    }
}
