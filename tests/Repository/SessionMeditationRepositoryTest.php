<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\SessionMeditationRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionMeditationRepositoryTest extends TestCase
{
    private SessionMeditationRepository&MockObject $repository;
    private QueryBuilder&MockObject $queryBuilder;
    private Query&MockObject $query;

    protected function setUp(): void
    {
        $this->repository = $this->getMockBuilder(SessionMeditationRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query        = $this->createMock(Query::class);
    }

    public function testSearchAndSortAppliesQueryAndSorting(): void
    {
        $expected = [new \stdClass()];

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('s')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('s.theme LIKE :q OR s.auteur LIKE :q')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('q', '%test%')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('s.theme', 'ASC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expected);

        $this->assertSame($expected, $this->repository->searchAndSort('test', 'theme', 'ASC'));
    }

    public function testSearchAndSortUsesDefaultsWhenNoQueryAndInvalidSortDirection(): void
    {
        $expected = [new \stdClass()];

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('s')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->never())
            ->method('where');

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('s.id', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expected);

        $this->assertSame($expected, $this->repository->searchAndSort('', 'invalid_field', 'bad'));
    }
}
