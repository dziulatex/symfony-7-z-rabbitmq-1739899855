<?php

namespace App\Command;

use Symfony\Component\Uid\Uuid;

class ParseFileCommand
{
    private Uuid $fileId;

    public function __construct(Uuid $fileId)
    {
        $this->fileId = $fileId;
    }

    public function getFileId(): Uuid
    {
        return $this->fileId;
    }
}