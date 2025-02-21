<?php

namespace App\Entity;

use App\Repository\FileUploadRepository;
use App\Service\FileProcessing\FileProcessingStatus;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;

use function in_array;
use function sprintf;

#[ORM\Entity(repositoryClass: FileUploadRepository::class)]
class FileUpload
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(type: 'datetime')]
    private DateTime $uploadedAt;

    #[ORM\Column(length: 32, nullable: false)]
    private ?string $md5Hash;

    #[ORM\Column(type: 'string', enumType: FileProcessingStatus::class)]
    private FileProcessingStatus $state;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage;

    public function __construct(
        string $extension,
        string $baseDirectory,
    ) {
        $this->id = Uuid::v4();
        $this->filename = $this->id->toString() . '.' . $extension;
        $this->uploadedAt = new DateTime();
        $this->path = $this->generatePath($baseDirectory);
        $this->md5Hash = null;
        $this->state = FileProcessingStatus::NEW;
    }

    public function generateMd5()
    {
        $fullPath = $this->getFullPath();
        if (!file_exists($fullPath)) {
            throw new LogicException(sprintf('Path "%s" does not exist', $fullPath));
        }

        $this->md5Hash = md5_file($fullPath);
    }

    private function generatePath(string $baseDirectory): string
    {
        // folder structure to not overwhelm linux filesystem by having all files in one folder.
        return sprintf(
            '%s/%s/%s/%s/%s/%s',
            $baseDirectory,
            $this->uploadedAt->format('Y'),    // Year
            $this->uploadedAt->format('m'),    // Month
            $this->uploadedAt->format('d'),    // Day
            $this->uploadedAt->format('H'),    // Hour
            $this->uploadedAt->format('i')     // Minute
        );
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUploadedAt(): DateTime
    {
        return $this->uploadedAt;
    }

    public function getMd5Hash(): ?string
    {
        return $this->md5Hash;
    }

    public function getFullPath(): string
    {
        return $this->path . '/' . $this->filename;
    }

    public function getState(): FileProcessingStatus
    {
        return $this->state;
    }

    public function markAsWaiting(): self
    {
        if (!in_array($this->state, [FileProcessingStatus::NEW, FileProcessingStatus::ERROR], true)) {
            throw new LogicException('Can only mark NEW or ERROR files as processing');
        }
        $this->state = FileProcessingStatus::WAITING;
        $this->errorMessage = null; // Clear any previous error message
        return $this;
    }

    public function markAsProcessing(): self
    {
        if (!in_array(
            $this->state,
            [FileProcessingStatus::NEW, FileProcessingStatus::ERROR, FileProcessingStatus::WAITING],
            true
        )) {
            throw new LogicException('Can only mark NEW or ERROR files as processing');
        }
        $this->state = FileProcessingStatus::PROCESSING;
        $this->errorMessage = null; // Clear any previous error message
        return $this;
    }

    public function markAsProcessed(): self
    {
        if ($this->state->value !== FileProcessingStatus::PROCESSING->value) {
            throw new LogicException('Can only mark PROCESSING files as processed');
        }
        $this->state = FileProcessingStatus::PROCESSED;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function markAsError(string $errorMessage): self
    {
        $this->state = FileProcessingStatus::ERROR;
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function isNew(): bool
    {
        return $this->state === FileProcessingStatus::NEW;
    }

    public function isWaiting(): bool
    {
        return $this->state === FileProcessingStatus::WAITING;
    }

    public function isProcessed(): bool
    {
        return $this->state === FileProcessingStatus::PROCESSED;
    }

    public function isProcessing(): bool
    {
        return $this->state === FileProcessingStatus::PROCESSING;
    }
}