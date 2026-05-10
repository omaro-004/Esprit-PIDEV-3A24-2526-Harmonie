<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;


class ImageGenerationService
{
    private const API_KEY = 'REDACTED';
    private const API_URL = 'https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell';

    public function __construct(private readonly HttpClientInterface $http) {}


    public function generateImage(string $prompt): ?string
    {
        try {
            $response = $this->http->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::API_KEY,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'image/png',
                ],
                'json'    => ['inputs' => $prompt],
                'timeout' => 60,
            ]);

            if ($response->getStatusCode() === 200) {
                return $response->getContent();
            }
        } catch (\Throwable $e) {
            // Silently fail — caller handles null
        }

        return null;
    }


    public function generateCourseImage(string $courseTitle, string $subject): ?string
    {
        $prompt = sprintf(
            'A sleek minimalist course cover icon for an online education platform. '
            . 'The course is titled "%s" and teaches "%s" as an academic subject. '
            . 'Interpret "%s" strictly as an educational or technical discipline, not literally. '
            . 'For example if the subject is Python it means the programming language, '
            . 'if it is Java it means software development, if it is Biology it means life sciences. '
            . 'Flat vector illustration, modern app icon style, subtle gradient, '
            . 'centered composition, soft geometric shapes, professional edu-tech aesthetic, '
            . 'vibrant but clean color palette, no text, no letters, no words, no animals unless abstractly symbolic',
            $courseTitle,
            $subject,
            $subject
        );

        return $this->generateImage($prompt);
    }
}
