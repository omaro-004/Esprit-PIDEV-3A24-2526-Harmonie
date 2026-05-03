<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: '`courses`')]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(name: 'subjectid', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Subject $subject = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'user_id', onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'is_published', type: 'boolean', options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(name: 'cover_image_path', type: 'string', length: 500, nullable: true)]
    private ?string $coverImagePath = null;

    #[ORM\Column(name: 'saves', type: 'integer', options: ['default' => 0])]
    private int $saves = 0;

    #[ORM\Column(name: 'admin_locked', type: 'boolean', options: ['default' => false])]
    private bool $adminLocked = false;

    /**
     * @var Collection<int, CourseFile>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: CourseFile::class, orphanRemoval: true)]
    private Collection $courseFiles;

    /**
     * @var Collection<int, SavedCourse>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: SavedCourse::class, orphanRemoval: true)]
    private Collection $savedCourses;

    /**
     * @var Collection<int, CourseReport>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: CourseReport::class, orphanRemoval: true)]
    private Collection $courseReports;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->courseFiles = new ArrayCollection();
        $this->savedCourses = new ArrayCollection();
        $this->courseReports = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getSubject(): ?Subject { return $this->subject; }
    public function setSubject(?Subject $subject): self { $this->subject = $subject; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getIsPublished(): bool { return $this->isPublished; }
    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }

    public function getCoverImagePath(): ?string { return $this->coverImagePath; }
    public function setCoverImagePath(?string $coverImagePath): self { $this->coverImagePath = $coverImagePath; return $this; }

    public function getSaves(): int { return $this->saves; }
    public function setSaves(int $saves): self { $this->saves = $saves; return $this; }

    public function getAdminLocked(): bool { return $this->adminLocked; }
    public function isAdminLocked(): bool { return $this->adminLocked; }
    public function setAdminLocked(bool $adminLocked): self { $this->adminLocked = $adminLocked; return $this; }

    /**
     * @return Collection<int, CourseFile>
     */
    public function getCourseFiles(): Collection { return $this->courseFiles; }
    public function addCourseFile(CourseFile $courseFile): self
    {
        if (!$this->courseFiles->contains($courseFile)) {
            $this->courseFiles->add($courseFile);
            $courseFile->setCourse($this);
        }
        return $this;
    }
    public function removeCourseFile(CourseFile $courseFile): self
    {
        if ($this->courseFiles->removeElement($courseFile)) {
            if ($courseFile->getCourse() === $this) {
                $courseFile->setCourse(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SavedCourse>
     */
    public function getSavedCourses(): Collection { return $this->savedCourses; }
    public function addSavedCourse(SavedCourse $savedCourse): self
    {
        if (!$this->savedCourses->contains($savedCourse)) {
            $this->savedCourses->add($savedCourse);
            $savedCourse->setCourse($this);
        }
        return $this;
    }
    public function removeSavedCourse(SavedCourse $savedCourse): self
    {
        if ($this->savedCourses->removeElement($savedCourse)) {
            if ($savedCourse->getCourse() === $this) {
                $savedCourse->setCourse(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CourseReport>
     */
    public function getCourseReports(): Collection { return $this->courseReports; }
    public function addCourseReport(CourseReport $courseReport): self
    {
        if (!$this->courseReports->contains($courseReport)) {
            $this->courseReports->add($courseReport);
            $courseReport->setCourse($this);
        }
        return $this;
    }
    public function removeCourseReport(CourseReport $courseReport): self
    {
        if ($this->courseReports->removeElement($courseReport)) {
            if ($courseReport->getCourse() === $this) {
                $courseReport->setCourse(null);
            }
        }
        return $this;
    }
}
