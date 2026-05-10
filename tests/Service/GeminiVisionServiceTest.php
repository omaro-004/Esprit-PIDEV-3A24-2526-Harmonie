<?php

namespace App\Tests\Service;

use App\Service\GeminiVisionService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour GeminiVisionService.
 *
 * Emplacement : tests/Service/GeminiVisionServiceTest.php
 * Commande   : php vendor/bin/phpunit tests/Service/GeminiVisionServiceTest.php --testdox
 */
class GeminiVisionServiceTest extends TestCase
{
    private function buildService(HttpClientInterface $client, string $key = 'AIzaFakeKey123'): GeminiVisionService
    {
        return new GeminiVisionService($client, $key);
    }

    private function mockResponse(int $status, array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getContent')->willReturn(json_encode($body));
        return $response;
    }

    /** ✅ Test 1 : Réponse JSON valide → données nutritionnelles parsées */
    public function testAnalyzeMealPhotoValide(): void
    {
        $apiBody = [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'plats_detectes'      => ['Poulet rôti', 'Riz'],
                        'calories_totales'    => 650,
                        'proteines_g'         => 45.0,
                        'glucides_g'          => 60.0,
                        'lipides_g'           => 18.0,
                        'score_equilibre'     => 7,
                        'suggestions'         => ['Ajouter des légumes', 'Réduire le sel'],
                        'note_nutritionnelle' => 'Repas équilibré pour un étudiant.',
                    ]),
                ]]],
            ]],
        ];

        $response = $this->mockResponse(200, $apiBody);
        $client   = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $result  = $service->analyzeMealPhoto(base64_encode('fake_image'), 'image/jpeg', 'Déjeuner');

        $this->assertIsArray($result);
        $this->assertSame(650, $result['calories_totales']);
        $this->assertSame(45.0, $result['proteines_g']);
        $this->assertSame(7, $result['score_equilibre']);
        $this->assertCount(2, $result['suggestions']);
        $this->assertSame('Repas équilibré pour un étudiant.', $result['note_nutritionnelle']);
    }

    /** ❌ Test 2 : Clé API invalide → RuntimeException */
    public function testAnalyzeMealPhotoCleApiInvalide(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clé API Gemini invalide ou manquante');

        $client  = $this->createMock(HttpClientInterface::class);
        $service = $this->buildService($client, 'mauvaise-cle');
        $service->analyzeMealPhoto(base64_encode('img'), 'image/jpeg');
    }

    /** ❌ Test 3 : Image vide → RuntimeException */
    public function testAnalyzeMealPhotoImageVide(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image vide ou manquante');

        $client  = $this->createMock(HttpClientInterface::class);
        $service = $this->buildService($client);
        $service->analyzeMealPhoto('', 'image/jpeg');
    }

    /** ❌ Test 4 : API retourne HTTP 400 → RuntimeException */
    public function testAnalyzeMealPhotoErreurHttp(): void
    {
        $this->expectException(\RuntimeException::class);

        $response = $this->mockResponse(400, [
            'error' => ['message' => 'Bad Request from Gemini'],
        ]);
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $service->analyzeMealPhoto(base64_encode('img'), 'image/jpeg');
    }

    /** ❌ Test 5 : JSON invalide dans la réponse → RuntimeException */
    public function testAnalyzeMealPhotoJsonInvalide(): void
    {
        $this->expectException(\RuntimeException::class);

        $apiBody = [
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Ce n\'est pas du JSON valide du tout.']]],
            ]],
        ];

        $response = $this->mockResponse(200, $apiBody);
        $client   = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $service->analyzeMealPhoto(base64_encode('img'), 'image/jpeg');
    }

    /** ✅ Test 6 : score_equilibre est clampé entre 1 et 10 */
    public function testScoreEquilibreClamp(): void
    {
        $apiBody = [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'plats_detectes'      => ['Pizza'],
                        'calories_totales'    => 900,
                        'proteines_g'         => 30.0,
                        'glucides_g'          => 100.0,
                        'lipides_g'           => 35.0,
                        'score_equilibre'     => 150, // hors limites
                        'suggestions'         => [],
                        'note_nutritionnelle' => 'Trop calorique.',
                    ]),
                ]]],
            ]],
        ];

        $response = $this->mockResponse(200, $apiBody);
        $client   = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $result  = $service->analyzeMealPhoto(base64_encode('img'), 'image/jpeg');

        $this->assertSame(10, $result['score_equilibre']);
    }
}