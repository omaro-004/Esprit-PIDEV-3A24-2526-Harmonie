<?php

namespace App\Tests\Controller;

use App\Controller\MeditationController;
use App\Entity\SessionMeditation;
use App\Repository\SessionMeditationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MeditationControllerTest extends TestCase
{
    private SessionMeditationRepository&MockObject $repo;
    private MeditationController $controller;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SessionMeditationRepository::class);
        $this->controller = new MeditationController($this->repo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function makeSession(string $theme = 'Calme', string $auteur = 'Sophie', int $duree = 30): SessionMeditation
    {
        $session = new SessionMeditation();
        $session->setTheme($theme);
        $session->setAuteur($auteur);
        $session->setDuree($duree);
        return $session;
    }

    public function testSearchReturnsJsonWithSessionData(): void
    {
        $session = $this->makeSession();

        $this->repo
            ->expects($this->once())
            ->method('searchAndSort')
            ->with('relaxation', 'theme', 'ASC')
            ->willReturn([$session]);

        $request = Request::create('/meditation/search', 'GET', [
            'q' => 'relaxation',
            'sort' => 'theme',
            'dir' => 'ASC',
        ]);

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('Calme', $data[0]['theme']);
        $this->assertSame('Sophie', $data[0]['auteur']);
        $this->assertSame(30, $data[0]['duree']);
    }

    public function testSearchReturnsEmptyArrayWhenNoResults(): void
    {
        $this->repo
            ->expects($this->once())
            ->method('searchAndSort')
            ->with('', 'id', 'DESC')
            ->willReturn([]);

        $request = Request::create('/meditation/search', 'GET');
        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testIndexRendersTemplateWithDefaultParameters(): void
    {
        $sessions = [$this->makeSession()];

        $this->repo
            ->expects($this->once())
            ->method('searchAndSort')
            ->with('', 'id', 'DESC')
            ->willReturn($sessions);

        $controller = $this->getMockBuilder(MeditationController::class)
            ->onlyMethods(['render'])
            ->setConstructorArgs([$this->repo])
            ->getMock();

        $controller
            ->expects($this->once())
            ->method('render')
            ->with('meditation/etudiant/index.html.twig', [
                'sessions' => $sessions,
                'q' => '',
                'sort' => 'id',
                'dir' => 'DESC',
            ])
            ->willReturn(new Response('ok'));

        $request = Request::create('/meditation', 'GET');
        $response = $controller->index($request);

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowRendersTemplate(): void
    {
        $session = $this->makeSession();

        $controller = $this->getMockBuilder(MeditationController::class)
            ->onlyMethods(['render'])
            ->setConstructorArgs([$this->repo])
            ->getMock();

        $controller
            ->expects($this->once())
            ->method('render')
            ->with('meditation/etudiant/show.html.twig', ['session' => $session])
            ->willReturn(new Response('show'));

        $response = $controller->show($session);

        $this->assertSame('show', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }
}
