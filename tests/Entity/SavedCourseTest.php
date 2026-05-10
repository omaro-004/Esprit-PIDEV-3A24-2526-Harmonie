<?php

namespace App\Tests\Entity;

use App\Entity\Course;
use App\Entity\SavedCourse;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class SavedCourseTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $savedCourse = new SavedCourse();

        $this->assertNull($savedCourse->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $savedCourse->getSavedAt());

        $date = new \DateTime('2026-01-01');
        $savedCourse->setSavedAt($date);
        $this->assertSame($date, $savedCourse->getSavedAt());

        $course = new Course();
        $savedCourse->setCourse($course);
        $this->assertSame($course, $savedCourse->getCourse());

        $user = new User();
        $savedCourse->setUser($user);
        $this->assertSame($user, $savedCourse->getUser());
    }
}
