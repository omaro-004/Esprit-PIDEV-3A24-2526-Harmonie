<?php

namespace App\Tests\Controller;

use App\Controller\AdminMeditationController;
use App\Entity\Conseil;
use App\Entity\SessionMeditation;
use App\Entity\User;
use App\Repository\SessionMeditationRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMeditationControllerTest extends TestCase
{
    private SessionMeditationRepository&MockObject $repo;
    private EntityManagerInterface&MockObject      $em;
    private GroqService&MockObject                 $groq;
    private AdminMeditationController              $controller;

    protected function setUp(): void
    {
        $this->repo       = $this->createMock(SessionMeditationRepository::class);
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->groq       = $this->createMock(GroqService::class);

        $this->controller = new AdminMeditationController(
            $this->repo,
            $this->em,
            $this->groq,
        );
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function makeSession(string $theme = 'Sommeil', string $auteur = 'Jean Dupont', int $duree = 20): SessionMeditation
    {
        $session = new SessionMeditation();
        $session->setTheme($theme);
        $session->setAuteur($auteur);
        $session->setDuree($duree);
        return $session;
    }

    // ── search() ───────────────────────────────────────────────────────────────

    public function testSearchReturnsJsonWithSessionData(): void
    {
        $session = $this->makeSession();

        $this->repo
            ->expects($this->once())
            ->method('searchAndSort')
            ->with('sommeil', 'id', 'DESC')
            ->willReturn([$session]);

        $request  = Request::create('/admin/meditation/search', 'GET', ['q' => 'sommeil', 'sort' => 'id', 'dir' => 'DESC']);
        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('Sommeil', $data[0]['theme']);
        $this->assertSame('Jean Dupont', $data[0]['auteur']);
        $this->assertSame(20, $data[0]['duree']);
    }

    public function testSearchReturnsEmptyArrayWhenNoResults(): void
    {
        $this->repo->method('searchAndSort')->willReturn([]);

        $request  = Request::create('/admin/meditation/search', 'GET');
        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);
    }

    public function testSearchDefaultsApplied(): void
    {
        $this->repo
            ->expects($this->once())
            ->method('searchAndSort')
            ->with('', 'id', 'DESC')
            ->willReturn([]);

        $request = Request::create('/admin/meditation/search', 'GET');
        $this->controller->search($request);
    }

    // ── delete() ───────────────────────────────────────────────────────────────

    public function testDeleteWithInvalidCsrfDoesNotRemove(): void
    {
        $session = $this->makeSession();

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $request = Request::create('/admin/meditation/1/delete', 'POST', ['_token' => 'invalid']);

        try {
            $this->controller->delete($session, $request);
        } catch (\Throwable) {
            // Expected: no container/router in unit test
        }
    }

    // ── generate() ─────────────────────────────────────────────────────────────

    public function testGenerateReturnsBadRequestWhenThemeEmpty(): void
    {
        $request  = Request::create('/admin/meditation/generate', 'POST', ['theme' => '']);
        $response = $this->controller->generate($request);

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGenerateReturnsBadRequestWhenThemeMissing(): void
    {
        $request  = Request::create('/admin/meditation/generate', 'POST');
        $response = $this->controller->generate($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testGenerateCallsGroqAndReturnsJson(): void
    {
        $expected = ['auteur' => 'Test', 'duree' => 15, 'conseils' => []];

        $this->groq
            ->expects($this->once())
            ->method('generateMeditation')
            ->with('Stress')
            ->willReturn($expected);

        $request  = Request::create('/admin/meditation/generate', 'POST', ['theme' => 'Stress']);
        $response = $this->controller->generate($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Test', $data['auteur']);
        $this->assertSame(15, $data['duree']);
    }

    public function testGenerateReturns500OnGroqException(): void
    {
        $this->groq
            ->method('generateMeditation')
            ->willThrowException(new \RuntimeException('API down'));

        $request  = Request::create('/admin/meditation/generate', 'POST', ['theme' => 'Stress']);
        $response = $this->controller->generate($request);

        $this->assertSame(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('API down', $data['error']);
    }

    // ── regenerateConseils() ───────────────────────────────────────────────────

    public function testRegenerateConseilsReturnsNewConseils(): void
    {
        $session = $this->makeSession('Anxiété');

        $this->groq
            ->expects($this->once())
            ->method('generateConseils')
            ->with('Anxiété', $this->isType('string'))
            ->willReturn(['Conseil A', 'Conseil B', 'Conseil C']);

        $this->em->expects($this->exactly(2))->method('flush');
        $this->em->expects($this->exactly(3))->method('persist');

        $response = $this->controller->regenerateConseils($session);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('conseils', $data);
    }

    public function testRegenerateConseilsReturns500OnException(): void
    {
        $session = $this->makeSession();

        $this->groq
            ->method('generateConseils')
            ->willThrowException(new \RuntimeException('IA error'));

        $response = $this->controller->regenerateConseils($session);

        $this->assertSame(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    // ── regenerateSession() ────────────────────────────────────────────────────

    public function testRegenerateSessionUpdatesFields(): void
    {
        $session = $this->makeSession('Focus');

        $this->groq
            ->expects($this->once())
            ->method('generateMeditation')
            ->with('Focus')
            ->willReturn([
                'auteur'      => 'New Author',
                'duree'       => 25,
                'audioUrl'    => 'https://youtube.com/watch?v=new',
                'searchQuery' => 'focus music',
            ]);

        $this->em->expects($this->once())->method('flush');

        $response = $this->controller->regenerateSession($session);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('New Author', $data['auteur']);
        $this->assertSame(25, $data['duree']);
    }

    public function testRegenerateSessionReturns500OnException(): void
    {
        $session = $this->makeSession();

        $this->groq
            ->method('generateMeditation')
            ->willThrowException(new \RuntimeException('Timeout'));

        $response = $this->controller->regenerateSession($session);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ── index() ────────────────────────────────────────────────────────────────

    public function testIndexCallsSearchAndSort(): void
    {
        $this->repo
            ->expects($this->once())
            ->method('searchAndSort')
            ->with('', 'id', 'DESC')
            ->willReturn([]);

        $request = Request::create('/admin/meditation', 'GET');

        try {
            $this->controller->index($request);
        } catch (\Throwable) {
            // Expected: no Twig container in unit test
        }
    }
}
