<?php

namespace App\Entity;

use App\Repository\SavedCourseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SavedCourseRepository::class)]
#[ORM\Table(name: '`saved_courses`')]
#[ORM\UniqueConstraint(name: 'uq_user_course', columns: ['user_id', 'course_id'])]
class SavedCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'savedCourses')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false)]
    private Course $course;

    #[ORM\Column(name: 'saved_at', type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $savedAt = null;

    public function __construct()
    {
        $this->savedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getCourse(): Course { return $this->course; }
    public function setCourse(Course $course): self { $this->course = $course; return $this; }

    public function getSavedAt(): ?\DateTimeInterface { return $this->savedAt; }
    public function setSavedAt(?\DateTimeInterface $savedAt): self { $this->savedAt = $savedAt; return $this; }
}
