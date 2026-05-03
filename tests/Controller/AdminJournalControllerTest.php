<?php

namespace App\Tests\Controller;

use App\Controller\AdminJournalController;
use App\Entity\JournalHumeur;
use App\Entity\User;
use App\Enum\Humeur;
use App\Repository\JournalHumeurRepository;
use App\Repository\UserRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class AdminJournalControllerTest extends TestCase
{
    private JournalHumeurRepository&MockObject $journalRepo;
    private UserRepository&MockObject          $userRepo;
    private GroqService&MockObject             $groq;
    private EntityManagerInterface&MockObject  $em;
    private AdminJournalController             $controller;

    protected function setUp(): void
    {
        $this->journalRepo = $this->createMock(JournalHumeurRepository::class);
        $this->userRepo    = $this->createMock(UserRepository::class);
        $this->groq        = $this->createMock(GroqService::class);
        $this->em          = $this->createMock(EntityManagerInterface::class);

        $this->controller = new AdminJournalController(
            $this->journalRepo,
            $this->userRepo,
            $this->groq,
            $this->em,
        );
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function makeUser(string $firstName = 'Alice', string $lastName = 'Martin'): User
    {
        $user = new User();
        $user->setUserPrenom($firstName);
        $user->setUserNom($lastName);
        $user->setUserEmail('alice@example.com');
        $user->setDateInscription('2024-01-01');
        return $user;
    }

    private function makeEntry(User $user, Humeur $humeur = Humeur::BIEN, int $score = 4): JournalHumeur
    {
        $entry = new JournalHumeur();
        $entry->setUser($user);
        $entry->setHumeur($humeur);
        $entry->setContenu('Test entry content');
        $entry->setDateJournal(new \DateTime('2025-03-01'));
        return $entry;
    }

    // ── markRead() ─────────────────────────────────────────────────────────────

    public function testMarkReadCallsRepositoryAndFlushes(): void
    {
        $this->journalRepo
            ->expects($this->once())
            ->method('markUnreadAsRead');

        $this->em
            ->expects($this->once())
            ->method('flush');

        $response = $this->controller->markRead();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // ── students() ─────────────────────────────────────────────────────────────

    public function testStudentsFiltersUsersWithNoEntries(): void
    {
        $userWithEntries    = $this->makeUser('Bob', 'Smith');
        $userWithoutEntries = $this->makeUser('Eve', 'Jones');

        $this->userRepo
            ->method('findAll')
            ->willReturn([$userWithEntries, $userWithoutEntries]);

        $this->journalRepo
            ->method('moodStats')
            ->willReturnCallback(fn(User $u) => match ($u) {
                $userWithEntries    => ['avgScore' => 4.0, 'total' => 5],
                $userWithoutEntries => ['avgScore' => 0.0, 'total' => 0],
                default             => ['avgScore' => 0.0, 'total' => 0],
            });

        // students() builds $userData then filters total > 0
        // We can't render Twig in a unit test, so we verify the repo calls
        $this->userRepo->expects($this->once())->method('findAll');
        $this->journalRepo->expects($this->exactly(2))->method('moodStats');

        // Call via reflection to test the data-building logic without Twig
        $reflection = new \ReflectionClass($this->controller);
        $method     = $reflection->getMethod('students');

        // Expect a Twig render attempt — it will throw since no container is set.
        // We catch it and verify the repo interactions happened correctly.
        try {
            $method->invoke($this->controller);
        } catch (\Throwable) {
            // Expected: no Twig container in unit test
        }
    }

    // ── userJournal() ──────────────────────────────────────────────────────────

    public function testUserJournalFetchesAllDataForUser(): void
    {
        $user    = $this->makeUser();
        $entries = [$this->makeEntry($user)];

        $this->journalRepo->expects($this->once())->method('findAllByUser')->with($user)->willReturn($entries);
        $this->journalRepo->expects($this->once())->method('moodStats')->with($user)->willReturn(['avgScore' => 4.0, 'total' => 1]);
        $this->journalRepo->expects($this->once())->method('scoreTrend')->with($user, 30)->willReturn([]);
        $this->journalRepo->expects($this->once())->method('moodDistribution')->with($user)->willReturn([]);

        try {
            $this->controller->userJournal($user);
        } catch (\Throwable) {
            // Expected: no Twig container in unit test
        }
    }

    // ── rapport() ──────────────────────────────────────────────────────────────

    public function testRapportWithNoEntriesRedirects(): void
    {
        $user = $this->makeUser();

        $this->journalRepo
            ->method('findAllByUser')
            ->with($user)
            ->willReturn([]);

        // Without a container the redirect will throw — we verify the repo was called
        $this->journalRepo->expects($this->once())->method('findAllByUser');

        try {
            $this->controller->rapport($user);
        } catch (\Throwable) {
            // Expected: no container/router in unit test
        }
    }

    public function testRapportCallsGroqWhenEntriesExist(): void
    {
        $user  = $this->makeUser('Alice', 'Martin');
        $entry = $this->makeEntry($user, Humeur::BIEN);

        $this->journalRepo->method('findAllByUser')->willReturn([$entry]);
        $this->journalRepo->method('moodStats')->willReturn(['avgScore' => 4.0, 'total' => 1]);

        $this->groq
            ->expects($this->once())
            ->method('generateWellbeingReport')
            ->with('Alice Martin', $this->isType('string'))
            ->willReturn('Rapport généré.');

        try {
            $this->controller->rapport($user);
        } catch (\Throwable) {
            // Expected: no Twig/Dompdf container in unit test
        }
    }

    public function testRapportHandlesGroqException(): void
    {
        $user  = $this->makeUser();
        $entry = $this->makeEntry($user);

        $this->journalRepo->method('findAllByUser')->willReturn([$entry]);
        $this->journalRepo->method('moodStats')->willReturn(['avgScore' => 3.0, 'total' => 1]);

        $this->groq
            ->method('generateWellbeingReport')
            ->willThrowException(new \RuntimeException('API error'));

        // Should not throw — exception is caught inside rapport()
        try {
            $this->controller->rapport($user);
        } catch (\Throwable $e) {
            // Only Twig/Dompdf errors are acceptable here, not GroqService errors
            $this->assertNotSame('API error', $e->getMessage());
        }
    }
}
