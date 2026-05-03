<?php

namespace App\Service\LibraryServices;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    // ── Groq API — key injected from .env as I_AM_GROQ ───────────────────────
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL    = 'llama-3.3-70b-versatile';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $apiKey           // bind in services.yaml: "%env(I_AM_GROQ)%"
    ) {}

    /**
     * Send a system instruction + user content to Groq and return the generated text.
     *
     * @throws \RuntimeException on network failure or non-200 response
     */
    public function generate(string $systemPrompt, string $userContent): string
    {
        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::MODEL,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userContent],
                ],
                'max_tokens'  => 800,
                'temperature' => 0.4,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('Groq API error %d: %s', $response->getStatusCode(), $response->getContent(false))
            );
        }

        $body = $response->toArray();

        $text = $body['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new \RuntimeException('Groq API returned no text in response.');
        }

        return trim($text);
    }
}
