<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AvatarController extends AbstractController
{
    #[Route('/avatar_creation', name: 'avatar_creation', methods: ['GET'])]
    public function create(Request $request): Response
    {
        $returnUrl = $request->query->get('returnUrl', $this->generateUrl('journal_new'));

        return $this->render('journal/avatar_create.html.twig', [
            'returnUrl' => $returnUrl,
        ]);
    }

    #[Route('/api/avatar/set-default', name: 'api_avatar_set_default', methods: ['POST'])]
    public function setDefault(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $slug = $data['avatarSlug'] ?? null;

        if (!$slug || !preg_match('/^avatar_\d{2}$/', $slug)) {
            return $this->json(['success' => false, 'error' => 'Invalid avatar slug'], 400);
        }

        return $this->json([
            'success' => true,
            'imagePath' => 'avatars/default/' . $slug . '.svg'
        ]);
    }

    #[Route('/api/avatar/generate-from-url', name: 'api_avatar_generate_from_url', methods: ['POST'])]
    public function generateFromUrl(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $imageUrl = $data['imageUrl'] ?? null;

        if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $this->json(['success' => false, 'error' => 'Invalid image URL'], 400);
        }

        try {
            // Context with a timeout to avoid hanging indefinitely
            $context = stream_context_create(['http' => ['timeout' => 15]]);
            $imageContent = file_get_contents($imageUrl, false, $context);

            if ($imageContent === false) {
                return $this->json(['success' => false, 'error' => 'Could not download image'], 500);
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/user_images';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = 'ai_avatar_' . uniqid() . '.png';
            $filePath = $uploadDir . '/' . $filename;

            file_put_contents($filePath, $imageContent);

            return $this->json([
                'success' => true,
                'imagePath' => 'user_images/' . $filename
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
