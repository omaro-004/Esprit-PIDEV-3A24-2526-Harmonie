<?php

namespace App\Entity;

use App\Repository\CourseFileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseFileRepository::class)]
#[ORM\Table(name: '`coursefile`')]
class CourseFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'courseFiles')]
    #[ORM\JoinColumn(name: 'courseid', referencedColumnName: 'id', nullable: false)]
    private Course $course;

    #[ORM\Column(name: 'originalname', type: 'string', length: 255)]
    private string $originalName = '';

    #[ORM\Column(name: 'mimetype', type: 'string', length: 120)]
    private string $mimeType = '';

    #[ORM\Column(name: 'sizebytes', type: Types::BIGINT)]
    private int $sizeBytes = 0;

    #[ORM\Column(name: 'filedata', type: Types::BLOB)]
    /** @var resource|string|null */
    private mixed $fileData;

    #[ORM\Column(name: 'uploaded_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getCourse(): Course { return $this->course; }
    public function setCourse(Course $course): self { $this->course = $course; return $this; }

    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): self { $this->originalName = $originalName; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): self { $this->mimeType = $mimeType; return $this; }

    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $sizeBytes): self { $this->sizeBytes = $sizeBytes; return $this; }

    /** @return resource|string */
    public function getFileData() { return $this->fileData; }
    /** @param resource|string $fileData */
    public function setFileData($fileData): self { $this->fileData = $fileData; return $this; }

    public function getUploadedAt(): \DateTimeInterface { return $this->uploadedAt; }
    public function setUploadedAt(\DateTimeInterface $uploadedAt): self { $this->uploadedAt = $uploadedAt; return $this; }
}
