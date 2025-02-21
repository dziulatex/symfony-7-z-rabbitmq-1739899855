<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Client entity representing customer data.
 *
 * This example uses PHP 8+ attributes for Doctrine ORM mapping.
 */
#[ORM\Entity]
#[ORM\Table(name: 'clients')] // Optional: Customize table name if needed
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

    /**
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
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
}