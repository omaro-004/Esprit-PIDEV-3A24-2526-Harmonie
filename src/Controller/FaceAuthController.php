<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FaceAuthController extends AbstractController
{
    // ── Page de vérification faciale après login ──────────────────────────────
    #[Route('/face-verify', name: 'face_verify')]
    public function verify(Request $request): Response
    {
        // Doit avoir un pending_face_user_id en session (mis par le login listener)
        $userId = $request->getSession()->get('pending_face_user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/face_verify.html.twig');
    }

    // ── API : Vérifier le visage au login ─────────────────────────────────────
    #[Route('/face-verify/check', name: 'face_verify_check', methods: ['POST'])]
    public function check(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $session = $request->getSession();
        $userId  = $session->get('pending_face_user_id');

        if (!$userId) {
            return new JsonResponse(['error' => 'Session expirée'], 400);
        }

        $user = $userRepo->find($userId);
        if (!$user || !$user->getFaceImagePath()) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 400);
        }

        // Retourner le chemin de l'image de référence pour comparaison côté client
        $projectDir = is_string($dir = $this->getParameter('kernel.project_dir')) ? $dir : '';
        $faceImagePath = $projectDir . '/public/' . $user->getFaceImagePath();

        if (!file_exists($faceImagePath)) {
            // Si l'image de référence n'existe plus, on laisse passer
            return new JsonResponse(['bypass' => true]);
        }

        $imageData = file_get_contents($faceImagePath);
        if ($imageData === false) {
            return new JsonResponse(['error' => 'Lecture image impossible'], 500);
        }
        $base64    = 'data:image/png;base64,' . base64_encode($imageData);

        return new JsonResponse([
            'referenceImage' => $base64,
            'userId'         => $userId,
        ]);
    }

    // ── API : Confirmer la vérification réussie et connecter ─────────────────
    #[Route('/face-verify/success', name: 'face_verify_success', methods: ['POST'])]
    public function faceSuccess(
        Request $request,
        UserRepository $userRepo,
    ): JsonResponse {
        $session = $request->getSession();
        $userId  = $session->get('pending_face_user_id');

        if (!$userId) {
            return new JsonResponse(['error' => 'Session expirée'], 400);
        }

        $user = $userRepo->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 400);
        }

        // Marquer comme vérifié → le listener laissera passer
        $session->set('face_verified', true);
        $session->remove('pending_face_user_id');

        return new JsonResponse(['redirect' => $this->generateUrl('homepage')]);
    }

    // ── Toggle Face ID dans les settings ──────────────────────────────────────
    #[IsGranted('ROLE_USER')]
    #[Route('/profile/toggle-faceid', name: 'app_toggle_faceid', methods: ['POST'])]
    public function toggleFaceId(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $data    = json_decode($request->getContent(), true);
        $enabled = (bool)($data['enabled'] ?? false);

        // Ne peut pas activer sans image faciale enregistrée
        if ($enabled && !$user->getFaceImagePath()) {
            return new JsonResponse(['error' => 'Aucune image faciale enregistrée.'], 400);
        }

        $user->setFaceIdEnabled($enabled);
        $em->flush();

        return new JsonResponse(['success' => true, 'enabled' => $enabled]);
    }

    // ── API : Mettre à jour l'image faciale depuis les settings ───────────────
    #[IsGranted('ROLE_USER')]
    #[Route('/profile/update-face', name: 'app_update_face', methods: ['POST'])]
    public function updateFace(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user      = $this->getUser();
        $data      = json_decode($request->getContent(), true);
        $faceImage = $data['faceImage'] ?? null;

        if (!$faceImage) {
            return new JsonResponse(['error' => 'Aucune image fournie'], 400);
        }

        $projectDir = is_string($dir = $this->getParameter('kernel.project_dir')) ? $dir : '';
        $faceDir = $projectDir . '/public/face_data';
        if (!is_dir($faceDir)) {
            mkdir($faceDir, 0777, true);
        }

        // Supprimer l'ancienne image
        if ($user->getFaceImagePath()) {
            $projectDir = is_string($dir = $this->getParameter('kernel.project_dir')) ? $dir : '';
            $old = $projectDir . '/public/' . $user->getFaceImagePath();
            if (file_exists($old)) {
                unlink($old);
            }
        }

        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $faceImage);
        $imageData = base64_decode($imageData);
        $filename  = 'face_' . uniqid() . '.png';
        file_put_contents($faceDir . '/' . $filename, $imageData);

        $user->setFaceImagePath('face_data/' . $filename);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
