<?php

namespace App\Tests\Entity;

use App\Entity\Course;
use App\Entity\Subject;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class SubjectTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $subject = new Subject();

        $this->assertNull($subject->getId());

        $subject->setName('Programming');
        $this->assertSame('Programming', $subject->getName());
    }

    public function testCourseRelationship(): void
    {
        $subject = new Subject();
        $course = new Course();

        $this->assertInstanceOf(Collection::class, $subject->getCourses());
        $this->assertCount(0, $subject->getCourses());

        $subject->addCourse($course);
        $this->assertCount(1, $subject->getCourses());
        $this->assertTrue($subject->getCourses()->contains($course));
        $this->assertSame($subject, $course->getSubject());

        $subject->removeCourse($course);
        $this->assertCount(0, $subject->getCourses());
        $this->assertNull($course->getSubject());
    }
}
