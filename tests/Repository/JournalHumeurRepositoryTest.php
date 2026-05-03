<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\JournalHumeurRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JournalHumeurRepositoryTest extends TestCase
{
    private JournalHumeurRepository&MockObject $repository;
    private QueryBuilder&MockObject $queryBuilder;
    private Query&MockObject $query;

    protected function setUp(): void
    {
        $this->repository = $this->getMockBuilder(JournalHumeurRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query        = $this->createMock(Query::class);
    }

    public function testFindByUserReturnsResults(): void
    {
        $user    = new User();
        $results = [new \stdClass()];

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('j')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('j.user = :user')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('user', $user)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('j.dateJournal', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($results);

        $this->assertSame($results, $this->repository->findByUser($user));
    }

    public function testSearchByUserAppliesQueryAndHumeurFilters(): void
    {
        $user    = new User();
        $results = [new \stdClass()];

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('j')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('j.user = :user')
            ->willReturnSelf();

        $parameterCalls = [
            ['user', $user],
            ['q', '%test%'],
            ['humeur', 'BIEN'],
        ];
        $parameterIndex = 0;

        $this->queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->with($this->isType('string'), $this->anything())
            ->willReturnCallback(function (string $name, $value) use (&$parameterCalls, &$parameterIndex) {
                $this->assertSame($parameterCalls[$parameterIndex][0], $name);
                $this->assertSame($parameterCalls[$parameterIndex][1], $value);
                $parameterIndex++;
                return $this->queryBuilder;
            });

        $andWhereCalls = ['j.contenu LIKE :q', 'j.humeur = :humeur'];
        $andWhereIndex = 0;

        $this->queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->with($this->isType('string'))
            ->willReturnCallback(function (string $condition) use (&$andWhereCalls, &$andWhereIndex) {
                $this->assertSame($andWhereCalls[$andWhereIndex], $condition);
                $andWhereIndex++;
                return $this->queryBuilder;
            });

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('j.dateJournal', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($results);

        $this->assertSame($results, $this->repository->searchByUser($user, 'test', 'BIEN'));
    }

    public function testCountUnreadByAdminReturnsInteger(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('j')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(j.id)')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('j.isReadByAdmin = false')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('7');

        $this->assertSame(7, $this->repository->countUnreadByAdmin());
    }

    public function testMarkUnreadAsReadExecutesUpdate(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('j')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('update')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('set')
            ->with('j.isReadByAdmin', 'true')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('j.isReadByAdmin = false')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $this->repository->markUnreadAsRead();
    }

    public function testMoodStatsReturnsRoundedStats(): void
    {
        $user = new User();

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('j')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('AVG(j.score) AS avgScore, COUNT(j.id) AS total')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('j.user = :user')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('user', $user)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getSingleResult')
            ->willReturn(['avgScore' => '3.3333', 'total' => '9']);

        $this->assertSame(['avgScore' => 3.33, 'total' => 9], $this->repository->moodStats($user));
    }

    public function testScoreTrendFormatsEntries(): void
    {
        $user = new User();

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('j')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('j.dateJournal, j.score, j.humeur')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('j.user = :user')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('user', $user)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('j.dateJournal', 'ASC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(30)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getArrayResult')
            ->willReturn([
                ['dateJournal' => new \DateTime('2025-05-01'), 'score' => 4, 'humeur' => 'BIEN'],
            ]);

        $this->assertSame([
            ['date' => '01/05', 'score' => 4, 'humeur' => 'BIEN'],
        ], $this->repository->scoreTrend($user));
    }
}
