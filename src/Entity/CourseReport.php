<?php

namespace App\Entity;

use App\Repository\CourseReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseReportRepository::class)]
#[ORM\Table(name: '`course_reports`')]
#[ORM\UniqueConstraint(name: 'uq_report', columns: ['course_id', 'reporter_id'])]
class CourseReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'courseReports')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false)]
    private Course $course;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reporter_id', referencedColumnName: 'user_id', nullable: false)]
    private User $reporter;

    #[ORM\Column(name: 'reason', type: 'string', length: 255)]
    private string $reason = '';

    #[ORM\Column(name: 'details', type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(name: 'status', type: 'string', columnDefinition: "ENUM('pending', 'reviewed', 'dismissed')", options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getCourse(): Course { return $this->course; }
    public function setCourse(Course $course): self { $this->course = $course; return $this; }

    public function getReporter(): User { return $this->reporter; }
    public function setReporter(User $reporter): self { $this->reporter = $reporter; return $this; }

    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): self { $this->reason = $reason; return $this; }

    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): self { $this->details = $details; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
