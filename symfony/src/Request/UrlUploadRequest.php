<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UrlUploadRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'URL jest wymagany')]
        #[Assert\Url(message: 'Nieprawidłowy format URL')]
        private readonly string $url
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}