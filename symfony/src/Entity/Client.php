<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'clients')]
class Client
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $city = null;

    #[ORM\ManyToOne(targetEntity: FileUpload::class, inversedBy: 'clients')]
    #[ORM\JoinColumn(name: 'file_upload_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')] // Added JoinColumn
    private ?FileUpload $fileUpload;

    private bool $invalid = false;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setInvalid()
    {
        $this->invalid = true;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    //we should validate if its email, but for now we only load it from fixture so whatever
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getFileUpload(): ?FileUpload
    {
        return $this->fileUpload;
    }

    public function setFileUpload(?FileUpload $fileUpload): self
    {
        $this->fileUpload = $fileUpload;

        return $this;
    }
}