<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/avatar')]
class AvatarController extends AbstractController
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    #[Route('/generate-from-url', name: 'api_avatar_generate_from_url', methods: ['POST'])]
    public function generateFromUrl(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $imageUrl = trim((string) ($data['imageUrl'] ?? ''));

        if ('' === $imageUrl) {
            return new JsonResponse(['success' => false, 'error' => 'imageUrl manquante'], 400);
        }

        if (!str_starts_with($imageUrl, 'https://image.pollinations.ai/')) {
            return new JsonResponse(['success' => false, 'error' => 'URL non autorisee'], 400);
        }

        try {
            $response = $this->httpClient->request('GET', $imageUrl, [
                'timeout' => 30,
            ]);

            if (200 !== $response->getStatusCode()) {
                return new JsonResponse(['success' => false, 'error' => 'Impossible de telecharger l\'image'], 502);
            }

            $contentType = strtolower((string) ($response->getHeaders(false)['content-type'][0] ?? ''));
            if ('' !== $contentType && !str_starts_with($contentType, 'image/')) {
                return new JsonResponse(['success' => false, 'error' => 'La reponse distante n\'est pas une image'], 400);
            }

            $binary = $response->getContent();
            if ('' === $binary) {
                return new JsonResponse(['success' => false, 'error' => 'Image vide'], 400);
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/user_images';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = 'ai_avatar_' . uniqid('', true);
            $filename = str_replace('.', '', $filename) . '.png';
            file_put_contents($uploadDir . '/' . $filename, $binary);

            return new JsonResponse([
                'success' => true,
                'imagePath' => 'user_images/' . $filename,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur serveur lors de la generation avatar',
            ], 500);
        }
    }

    #[Route('/set-default', name: 'api_avatar_set_default', methods: ['POST'])]
    public function setDefault(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $avatarSlug = trim((string) ($data['avatarSlug'] ?? ''));

        $allowed = [
            'avatar_01',
            'avatar_02',
            'avatar_03',
            'avatar_04',
            'avatar_05',
            'avatar_06',
            'avatar_07',
            'avatar_08',
            'avatar_09',
            'avatar_10',
            'avatar_11',
            'avatar_12',
        ];

        if (!in_array($avatarSlug, $allowed, true)) {
            return new JsonResponse(['success' => false, 'error' => 'Avatar invalide'], 400);
        }

        return new JsonResponse([
            'success' => true,
            'imagePath' => 'avatars/default/' . $avatarSlug . '.svg',
        ]);
    }
}
