<?php

namespace App\Tests\Entity;

use App\Entity\Course;
use App\Entity\CourseFile;
use PHPUnit\Framework\TestCase;

class CourseFileTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $file = new CourseFile();

        $this->assertNull($file->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $file->getUploadedAt());

        $file->setOriginalName('document.pdf');
        $this->assertSame('document.pdf', $file->getOriginalName());

        $file->setMimeType('application/pdf');
        $this->assertSame('application/pdf', $file->getMimeType());

        $file->setSizeBytes(1024);
        $this->assertSame(1024, $file->getSizeBytes());

        $file->setFileData('binary_data');
        $this->assertSame('binary_data', $file->getFileData());

        $date = new \DateTime('2026-01-01');
        $file->setUploadedAt($date);
        $this->assertSame($date, $file->getUploadedAt());

        $course = new Course();
        $file->setCourse($course);
        $this->assertSame($course, $file->getCourse());
    }
}
