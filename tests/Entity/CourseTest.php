<?php

namespace App\Tests\Entity;

use App\Entity\Course;
use App\Entity\CourseFile;
use App\Entity\CourseReport;
use App\Entity\SavedCourse;
use App\Entity\Subject;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class CourseTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $course = new Course();

        $this->assertNull($course->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $course->getCreatedAt());
        
        $course->setTitle('Test Course');
        $this->assertSame('Test Course', $course->getTitle());

        $this->assertFalse($course->isPublished());
        $course->setIsPublished(true);
        $this->assertTrue($course->isPublished());

        $this->assertNull($course->getCoverImagePath());
        $course->setCoverImagePath('path/to/image.jpg');
        $this->assertSame('path/to/image.jpg', $course->getCoverImagePath());

        $this->assertSame(0, $course->getSaves());
        $course->setSaves(5);
        $this->assertSame(5, $course->getSaves());

        $this->assertFalse($course->isAdminLocked());
        $course->setAdminLocked(true);
        $this->assertTrue($course->isAdminLocked());
        
        $date = new \DateTime('2026-01-01');
        $course->setCreatedAt($date);
        $this->assertSame($date, $course->getCreatedAt());
    }

    public function testRelationships(): void
    {
        $course = new Course();
        
        $subject = new Subject();
        $course->setSubject($subject);
        $this->assertSame($subject, $course->getSubject());

        $user = new User();
        $course->setUser($user);
        $this->assertSame($user, $course->getUser());
    }

    public function testCourseFileRelationship(): void
    {
        $course = new Course();
        $file = new CourseFile();

        $this->assertInstanceOf(Collection::class, $course->getCourseFiles());
        $this->assertCount(0, $course->getCourseFiles());

        $course->addCourseFile($file);
        $this->assertCount(1, $course->getCourseFiles());
        $this->assertTrue($course->getCourseFiles()->contains($file));
        $this->assertSame($course, $file->getCourse());

        $course->removeCourseFile($file);
        $this->assertCount(0, $course->getCourseFiles());
    }

    public function testSavedCourseRelationship(): void
    {
        $course = new Course();
        $savedCourse = new SavedCourse();

        $this->assertInstanceOf(Collection::class, $course->getSavedCourses());
        $this->assertCount(0, $course->getSavedCourses());

        $course->addSavedCourse($savedCourse);
        $this->assertCount(1, $course->getSavedCourses());
        $this->assertTrue($course->getSavedCourses()->contains($savedCourse));
        $this->assertSame($course, $savedCourse->getCourse());

        $course->removeSavedCourse($savedCourse);
        $this->assertCount(0, $course->getSavedCourses());
    }

    public function testCourseReportRelationship(): void
    {
        $course = new Course();
        $report = new CourseReport();

        $this->assertInstanceOf(Collection::class, $course->getCourseReports());
        $this->assertCount(0, $course->getCourseReports());

        $course->addCourseReport($report);
        $this->assertCount(1, $course->getCourseReports());
        $this->assertTrue($course->getCourseReports()->contains($report));
        $this->assertSame($course, $report->getCourse());

        $course->removeCourseReport($report);
        $this->assertCount(0, $course->getCourseReports());
    }
}
