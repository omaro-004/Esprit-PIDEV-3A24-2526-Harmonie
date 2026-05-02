<?php

namespace App\Tests\Service;

use App\Service\GroqService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GroqServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private GroqService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->service = new GroqService(
            httpClient:     $this->httpClient,
            groqApiKey:     'test-api-key',
            groqChatUrl:    'https://api.groq.com/openai/v1/chat/completions',
            groqSttUrl:     'https://api.groq.com/openai/v1/audio/transcriptions',
            groqChatModel:  'llama3-8b-8192',
            groqSttModel:   'whisper-large-v3',
        );
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function mockChatResponse(string $content): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ]);
        return $response;
    }

    // ── chat() ─────────────────────────────────────────────────────────────────

    public function testChatReturnsTrimedContent(): void
    {
        $response = $this->mockChatResponse('  Hello world  ');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->service->chat('system', 'user');
        $this->assertSame('Hello world', $result);
    }

    public function testChatReturnsEmptyStringWhenNoChoices(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['choices' => []]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->chat('system', 'user');
        $this->assertSame('', $result);
    }

    // ── generateMeditation() ───────────────────────────────────────────────────

    public function testGenerateMeditationParsesAllFields(): void
    {
        $raw = implode("\n", [
            'AUTEUR: Marie Curie',
            'DUREE: 20',
            'QUERY: relaxing piano meditation',
            'CONSEIL1: Fermez les yeux et respirez profondément pendant cinq minutes.',
            'CONSEIL2: Concentrez-vous sur votre souffle et laissez vos pensées passer.',
            'CONSEIL3: Visualisez un endroit calme et paisible pour vous détendre.',
        ]);

        // First call: chat for meditation data
        // Second call: YouTube search (searchYouTubeVideo)
        $chatResponse    = $this->mockChatResponse($raw);
        $youtubeResponse = $this->createMock(ResponseInterface::class);
        $youtubeResponse->method('toArray')->willReturn([
            'contents' => [
                'twoColumnSearchResultsRenderer' => [
                    'primaryContents' => [
                        'sectionListRenderer' => [
                            'contents' => [
                                [
                                    'itemSectionRenderer' => [
                                        'contents' => [
                                            ['videoRenderer' => ['videoId' => 'abc123']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($chatResponse, $youtubeResponse);

        $result = $this->service->generateMeditation('Sommeil');

        $this->assertSame('Marie Curie', $result['auteur']);
        $this->assertSame(20, $result['duree']);
        $this->assertSame('relaxing piano meditation', $result['searchQuery']);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $result['audioUrl']);
        $this->assertCount(3, $result['conseils']);
    }

    public function testGenerateMeditationClampsDureeMin(): void
    {
        $raw = "AUTEUR: Test\nDUREE: 1\nQUERY: \nCONSEIL1: a\nCONSEIL2: b\nCONSEIL3: c";

        $chatResponse    = $this->mockChatResponse($raw);
        $youtubeResponse = $this->createMock(ResponseInterface::class);
        $youtubeResponse->method('toArray')->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willReturnOnConsecutiveCalls($chatResponse, $youtubeResponse);

        $result = $this->service->generateMeditation('Focus');
        $this->assertGreaterThanOrEqual(5, $result['duree']);
    }

    public function testGenerateMeditationClampsDureeMax(): void
    {
        $raw = "AUTEUR: Test\nDUREE: 999\nQUERY: \nCONSEIL1: a\nCONSEIL2: b\nCONSEIL3: c";

        $chatResponse    = $this->mockChatResponse($raw);
        $youtubeResponse = $this->createMock(ResponseInterface::class);
        $youtubeResponse->method('toArray')->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willReturnOnConsecutiveCalls($chatResponse, $youtubeResponse);

        $result = $this->service->generateMeditation('Focus');
        $this->assertLessThanOrEqual(60, $result['duree']);
    }

    // ── generateConseils() ─────────────────────────────────────────────────────

    public function testGenerateConseilsReturnsThreeItems(): void
    {
        $raw = implode("\n", [
            'CONSEIL1: Respirez lentement et profondément pour calmer votre esprit.',
            'CONSEIL2: Concentrez-vous sur les sensations de votre corps au sol.',
            'CONSEIL3: Laissez vos pensées passer sans les juger ni les retenir.',
        ]);

        $response = $this->mockChatResponse($raw);
        $this->httpClient->method('request')->willReturn($response);

        $conseils = $this->service->generateConseils('Stress');

        $this->assertCount(3, $conseils);
        $this->assertStringContainsString('Respirez', $conseils[0]);
        $this->assertStringContainsString('Concentrez', $conseils[1]);
        $this->assertStringContainsString('Laissez', $conseils[2]);
    }

    public function testGenerateConseilsReturnsEmptyArrayOnBadResponse(): void
    {
        $response = $this->mockChatResponse('Aucun conseil disponible.');
        $this->httpClient->method('request')->willReturn($response);

        $conseils = $this->service->generateConseils('Anxiété');
        $this->assertSame([], $conseils);
    }

    // ── parseJournalFromSpeech() ───────────────────────────────────────────────

    public function testParseJournalFromSpeechParsesAllFields(): void
    {
        $raw = "DATE: 2025-04-10\nHUMEUR: BIEN\nCONTENU: Bonne journée au travail.";

        $response = $this->mockChatResponse($raw);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->parseJournalFromSpeech('Bonne journée au travail.', '2025-04-10');

        $this->assertSame('2025-04-10', $result['date']);
        $this->assertSame('BIEN', $result['humeur']);
        $this->assertSame('Bonne journée au travail.', $result['contenu']);
    }

    public function testParseJournalFromSpeechFallsBackToNeutreOnInvalidHumeur(): void
    {
        $raw = "DATE: 2025-04-10\nHUMEUR: INVALID_VALUE\nCONTENU: Test.";

        $response = $this->mockChatResponse($raw);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->parseJournalFromSpeech('Test.', '2025-04-10');
        $this->assertSame('NEUTRE', $result['humeur']);
    }

    public function testParseJournalFromSpeechUsesTodayAsFallback(): void
    {
        $today    = (new \DateTime())->format('Y-m-d');
        $response = $this->mockChatResponse(''); // empty response → defaults used

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->parseJournalFromSpeech('Test.', $today);
        $this->assertSame($today, $result['date']);
        $this->assertSame('NEUTRE', $result['humeur']);
    }

    // ── generateWellbeingReport() ──────────────────────────────────────────────

    public function testGenerateWellbeingReportReturnsString(): void
    {
        $response = $this->mockChatResponse('Rapport de bien-être généré.');
        $this->httpClient->method('request')->willReturn($response);

        $report = $this->service->generateWellbeingReport('Alice Martin', 'score moyen: 4.2');
        $this->assertSame('Rapport de bien-être généré.', $report);
    }

    // ── searchYouTubeVideo() ───────────────────────────────────────────────────

    public function testSearchYouTubeVideoReturnsUrlOnSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'contents' => [
                'twoColumnSearchResultsRenderer' => [
                    'primaryContents' => [
                        'sectionListRenderer' => [
                            'contents' => [
                                [
                                    'itemSectionRenderer' => [
                                        'contents' => [
                                            ['videoRenderer' => ['videoId' => 'xyz789']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $url = $this->service->searchYouTubeVideo('relaxing music');
        $this->assertSame('https://www.youtube.com/watch?v=xyz789', $url);
    }

    public function testSearchYouTubeVideoReturnsEmptyStringOnException(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $url = $this->service->searchYouTubeVideo('relaxing music');
        $this->assertSame('', $url);
    }

    public function testSearchYouTubeVideoReturnsEmptyStringWhenNoVideos(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->method('request')->willReturn($response);

        $url = $this->service->searchYouTubeVideo('relaxing music');
        $this->assertSame('', $url);
    }
}
