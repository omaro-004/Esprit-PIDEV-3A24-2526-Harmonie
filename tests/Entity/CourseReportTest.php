<?php

namespace App\Tests\Entity;

use App\Entity\Course;
use App\Entity\CourseReport;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CourseReportTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $report = new CourseReport();

        $this->assertNull($report->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $report->getCreatedAt());

        $report->setReason('Inappropriate content');
        $this->assertSame('Inappropriate content', $report->getReason());

        $this->assertNull($report->getDetails());
        $report->setDetails('More details here');
        $this->assertSame('More details here', $report->getDetails());

        $this->assertSame('pending', $report->getStatus());
        $report->setStatus('reviewed');
        $this->assertSame('reviewed', $report->getStatus());

        $date = new \DateTime('2026-01-01');
        $report->setCreatedAt($date);
        $this->assertSame($date, $report->getCreatedAt());

        $course = new Course();
        $report->setCourse($course);
        $this->assertSame($course, $report->getCourse());

        $user = new User();
        $report->setReporter($user);
        $this->assertSame($user, $report->getReporter());
    }
}
