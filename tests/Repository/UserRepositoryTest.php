<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(User::class);

        $schemaTool = new SchemaTool($this->em);
        $classes = [$this->em->getClassMetadata(User::class)];
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    private function persistUser(string $nom, string $prenom, string $email, bool $active, string $type): User
    {
        $user = new User();
        $user->setUserNom($nom);
        $user->setUserPrenom($prenom);
        $user->setUserEmail($email);
        $user->setUserPassword('hashed');
        $user->setUserDateDeNaissance('2000-01-01');
        $user->setDateInscription('2024-01-01');
        $user->setTypeUtilisateur($type);
        $user->setIsActive($active);

        $this->em->persist($user);
        return $user;
    }

    public function testSearchByName(): void
    {
        $this->persistUser('Dupont', 'Jean', 'jean.dupont@example.com', true, 'ETUDIANT');
        $this->persistUser('Durand', 'Alice', 'alice@example.com', true, 'ETUDIANT');
        $this->em->flush();

        $results = $this->repo->searchByName('jean');
        $this->assertCount(1, $results);
        $this->assertSame('Jean', $results[0]->getUserPrenom());
    }

    public function testFindActiveAndSuspendedStudents(): void
    {
        $this->persistUser('Dupont', 'Jean', 'jean.dupont@example.com', true, 'ETUDIANT');
        $this->persistUser('Durand', 'Alice', 'alice@example.com', false, 'ETUDIANT');
        $this->persistUser('Martin', 'Admin', 'admin@example.com', true, 'ADMIN');
        $this->em->flush();

        $active = $this->repo->findActiveStudents();
        $suspended = $this->repo->findSuspendedStudents();
        $all = $this->repo->findAllStudents();

        $this->assertCount(1, $active);
        $this->assertCount(1, $suspended);
        $this->assertCount(2, $all);
    }
}
