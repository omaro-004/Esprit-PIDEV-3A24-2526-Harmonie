<?php

namespace App\Tests\Service;

use App\Service\BlessureService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour BlessureService.
 * L'HttpClient est mocké — aucun appel réseau réel.
 *
 * Emplacement : tests/Service/BlessureServiceTest.php
 * Commande   : php vendor/bin/phpunit tests/Service/BlessureServiceTest.php --testdox
 */
class BlessureServiceTest extends TestCase
{
    // ── Constantes de configuration utilisées par le service ──────────
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'llama3-8b-8192';
    private const GROQ_KEY   = 'test-key-xxxx';

    // ── Helper : construit le service avec un HttpClient mocké ────────
    private function buildService(HttpClientInterface $client): BlessureService
    {
        return new BlessureService(
            $client,
            self::GROQ_KEY,
            self::GROQ_URL,
            self::GROQ_MODEL
        );
    }

    // ── Helper : crée une réponse HTTP mockée avec un corps JSON ──────
    private function mockResponse(array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($body);
        return $response;
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 1 : Réponse JSON valide de l'API Groq
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 1 : Une réponse JSON valide doit être parsée correctement */
    public function testGetRecoveryAdviceAvecReponseValide(): void
    {
        $jsonContent = json_encode([
            'traitements'            => ['Repos', 'Glace 20 min', 'Compression'],
            'exercices_recuperation' => ['Étirements doux', 'Renforcement isométrique'],
            'conseils_nutrition'     => ['Oméga-3', 'Hydratation 2L/jour'],
            'quand_consulter'        => 'Douleur intense après 72h',
            'duree_recuperation'     => '5 à 10 jours',
        ]);

        $response = $this->mockResponse([
            'choices' => [
                ['message' => ['content' => $jsonContent]],
            ],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $result  = $service->getRecoveryAdvice('genou_gauche', 'musculation', 'modérée');

        $this->assertIsArray($result);
        $this->assertSame('genou_gauche', $result['articulation']);
        $this->assertSame('genou gauche', $result['label']);
        $this->assertCount(3, $result['traitements']);
        $this->assertCount(2, $result['exercices_recuperation']);
        $this->assertCount(2, $result['conseils_nutrition']);
        $this->assertNotEmpty($result['quand_consulter']);
        $this->assertNotEmpty($result['duree_recuperation']);
        $this->assertArrayHasKey('raw', $result);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 2 : Articulation inconnue → label par défaut
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 2 : Articulation inconnue → label construit à partir de l'ID */
    public function testGetRecoveryAdviceArticulationInconnue(): void
    {
        $jsonContent = json_encode([
            'traitements'            => ['Repos'],
            'exercices_recuperation' => [],
            'conseils_nutrition'     => [],
            'quand_consulter'        => 'Consulter si douleur',
            'duree_recuperation'     => '3 jours',
        ]);

        $response = $this->mockResponse([
            'choices' => [['message' => ['content' => $jsonContent]]],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $result  = $service->getRecoveryAdvice('zone_inconnue', 'yoga', 'légère');

        $this->assertSame('zone_inconnue', $result['articulation']);
        // L'ID est transformé en label lisible (underscores → espaces)
        $this->assertSame('zone inconnue', $result['label']);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 3 : L'API retourne un JSON invalide → fallback activé
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 3 : JSON invalide de l'API → fallback avec conseils par défaut */
    public function testGetRecoveryAdviceJsonInvalide(): void
    {
        $response = $this->mockResponse([
            'choices' => [['message' => ['content' => 'Réponse non-JSON du modèle IA']]],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $result  = $service->getRecoveryAdvice('epaule_gauche', 'cardio', 'intense');

        // Le fallback doit retourner la même structure
        $this->assertArrayHasKey('traitements', $result);
        $this->assertArrayHasKey('exercices_recuperation', $result);
        $this->assertArrayHasKey('conseils_nutrition', $result);
        $this->assertArrayHasKey('quand_consulter', $result);
        $this->assertArrayHasKey('duree_recuperation', $result);
        $this->assertNotEmpty($result['traitements']);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 4 : L'API est indisponible (exception réseau) → fallback
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 4 : Exception réseau → fallback activé sans crash */
    public function testGetRecoveryAdviceApiIndisponible(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('Connection refused'));

        $service = $this->buildService($client);
        $result  = $service->getRecoveryAdvice('genou_droit', 'football', 'intense');

        $this->assertSame('genou_droit', $result['articulation']);
        $this->assertNotEmpty($result['traitements']);
        $this->assertStringContainsString('API indisponible', $result['raw']);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 5 : Vérification de la structure complète du résultat
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 5 : La structure de retour contient toutes les clés requises */
    public function testStructureRetourComplete(): void
    {
        $jsonContent = json_encode([
            'traitements'            => ['RICE'],
            'exercices_recuperation' => ['Marche légère'],
            'conseils_nutrition'     => ['Curcuma'],
            'quand_consulter'        => 'Gonflement',
            'duree_recuperation'     => '7 jours',
        ]);

        $response = $this->mockResponse([
            'choices' => [['message' => ['content' => $jsonContent]]],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $service = $this->buildService($client);
        $result  = $service->getRecoveryAdvice('cheville_gauche');

        $requiredKeys = [
            'articulation', 'label', 'traitements',
            'exercices_recuperation', 'conseils_nutrition',
            'quand_consulter', 'duree_recuperation', 'raw',
        ];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Clé manquante : $key");
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 6 : Labels des articulations connues
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 6 : Les articulations connues ont un label français correct */
    public function testLabelsArticulationsConnus(): void
    {
        $articulations = [
            'genou_gauche'   => 'genou gauche',
            'genou_droit'    => 'genou droit',
            'epaule_gauche'  => 'épaule gauche',
            'dos_bas'        => 'bas du dos / lombaires',
            'tete'           => 'tête / nuque',
        ];

        foreach ($articulations as $id => $expectedLabel) {
            $jsonContent = json_encode([
                'traitements' => ['Repos'], 'exercices_recuperation' => [],
                'conseils_nutrition' => [], 'quand_consulter' => '',
                'duree_recuperation' => '',
            ]);

            $response = $this->mockResponse([
                'choices' => [['message' => ['content' => $jsonContent]]],
            ]);

            $client = $this->createMock(HttpClientInterface::class);
            $client->method('request')->willReturn($response);

            $service = $this->buildService($client);
            $result  = $service->getRecoveryAdvice($id);

            $this->assertSame($expectedLabel, $result['label'], "Label incorrect pour : $id");
        }
    }
}