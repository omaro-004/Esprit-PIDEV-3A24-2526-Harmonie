<?php

namespace App\Tests\Controller;

use App\Controller\JournalController;
use App\Entity\JournalHumeur;
use App\Entity\User;
use App\Enum\Humeur;
use App\Repository\JournalHumeurRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class JournalControllerTest extends TestCase
{
    private JournalHumeurRepository&MockObject $repo;
    private EntityManagerInterface&MockObject  $em;
    private GroqService&MockObject             $groq;
    private JournalController                  $controller;

    protected function setUp(): void
    {
        $this->repo       = $this->createMock(JournalHumeurRepository::class);
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->groq       = $this->createMock(GroqService::class);

        $this->controller = new JournalController(
            $this->repo,
            $this->em,
            $this->groq,
        );
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        $user = new User();
        $user->setUserPrenom('Alice');
        $user->setUserNom('Martin');
        $user->setUserEmail('alice@example.com');
        $user->setDateInscription('2024-01-01');
        return $user;
    }

    private function makeEntry(User $user, Humeur $humeur = Humeur::BIEN): JournalHumeur
    {
        $entry = new JournalHumeur();
        $entry->setUser($user);
        $entry->setHumeur($humeur);
        $entry->setContenu('Test content');
        $entry->setDateJournal(new \DateTime('2025-03-01'));
        return $entry;
    }

    // ── search() ───────────────────────────────────────────────────────────────

    public function testSearchReturnsJsonWithEntryData(): void
    {
        $user  = $this->makeUser();
        $entry = $this->makeEntry($user, Humeur::BIEN);

        // Inject the logged-in user via a partial mock
        $controller = $this->getMockBuilder(JournalController::class)
            ->setConstructorArgs([$this->repo, $this->em, $this->groq])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($user);

        $this->repo
            ->expects($this->once())
            ->method('searchByUser')
            ->with($user, 'test', '')
            ->willReturn([$entry]);

        $request  = Request::create('/journal/search', 'GET', ['q' => 'test', 'humeur' => '']);
        $response = $controller->search($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('BIEN', $data[0]['humeur']);
        $this->assertSame('Bien', $data[0]['humeurLabel']);
        $this->assertSame('🙂', $data[0]['humeurEmoji']);
        $this->assertSame(4, $data[0]['score']);
        $this->assertSame('Test content', $data[0]['contenu']);
    }

    public function testSearchDefaultsToEmptyStrings(): void
    {
        $user = $this->makeUser();

        $controller = $this->getMockBuilder(JournalController::class)
            ->setConstructorArgs([$this->repo, $this->em, $this->groq])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($user);

        $this->repo
            ->expects($this->once())
            ->method('searchByUser')
            ->with($user, '', '')
            ->willReturn([]);

        $request  = Request::create('/journal/search', 'GET');
        $response = $controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);
    }

    // ── stats() ────────────────────────────────────────────────────────────────

    public function testStatsReturnsJsonWithAllKeys(): void
    {
        $user = $this->makeUser();

        $controller = $this->getMockBuilder(JournalController::class)
            ->setConstructorArgs([$this->repo, $this->em, $this->groq])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($user);

        $this->repo->method('moodStats')->willReturn(['avgScore' => 3.5, 'total' => 10]);
        $this->repo->method('scoreTrend')->willReturn([['date' => '01/03', 'score' => 4]]);
        $this->repo->method('moodDistribution')->willReturn([['humeur' => 'BIEN', 'cnt' => 5]]);

        $response = $controller->stats();

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('trend', $data);
        $this->assertArrayHasKey('dist', $data);
        $this->assertSame(3.5, $data['stats']['avgScore']);
        $this->assertSame(10, $data['stats']['total']);
    }

    // ── delete() ───────────────────────────────────────────────────────────────

    public function testDeleteThrowsAccessDeniedForWrongUser(): void
    {
        $owner = $this->makeUser();
        $other = new User();
        $other->setUserEmail('other@example.com');
        $other->setDateInscription('2024-01-01');

        $entry = $this->makeEntry($owner);

        $controller = $this->getMockBuilder(JournalController::class)
            ->setConstructorArgs([$this->repo, $this->em, $this->groq])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($other);

        $this->expectException(AccessDeniedException::class);

        $request = Request::create('/journal/1/delete', 'POST', ['_token' => 'token']);
        $controller->delete($entry, $request);
    }

    public function testDeleteWithInvalidCsrfDoesNotRemove(): void
    {
        $user  = $this->makeUser();
        $entry = $this->makeEntry($user);

        $controller = $this->getMockBuilder(JournalController::class)
            ->setConstructorArgs([$this->repo, $this->em, $this->groq])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($user);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $request = Request::create('/journal/1/delete', 'POST', ['_token' => 'bad_token']);

        try {
            $controller->delete($entry, $request);
        } catch (\Throwable) {
            // Expected: no router/container in unit test
        }
    }

    // ── edit() ─────────────────────────────────────────────────────────────────

    public function testEditThrowsAccessDeniedForWrongUser(): void
    {
        $owner = $this->makeUser();
        $other = new User();
        $other->setUserEmail('other@example.com');
        $other->setDateInscription('2024-01-01');

        $entry = $this->makeEntry($owner);

        $controller = $this->getMockBuilder(JournalController::class)
            ->setConstructorArgs([$this->repo, $this->em, $this->groq])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($other);

        $this->expectException(AccessDeniedException::class);

        $request = Request::create('/journal/1/edit', 'GET');
        $controller->edit($entry, $request);
    }

    // ── transcribe() ───────────────────────────────────────────────────────────

    public function testTranscribeReturnsBadRequestWhenNoFile(): void
    {
        $request  = Request::create('/journal/transcribe', 'POST');
        $response = $this->controller->transcribe($request);

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
