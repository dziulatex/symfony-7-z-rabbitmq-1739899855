<?php

namespace App\Command;

use Symfony\Component\Uid\Uuid;

class ParseFileInBatchCommand
{
    private Uuid $fileId;
    private int $startLine;
    private int $endLine;
    private int $totalRows;

    public function __construct(Uuid $fileId, int $startLine, int $endLine, int $totalRows)
    {
        $this->fileId = $fileId;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
        $this->totalRows = $totalRows;
    }

    public function getFileId(): Uuid
    {
        return $this->fileId;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getStartLine(): int
    {
        return $this->startLine;
    }

    public function getEndLine(): int
    {
        return $this->endLine;
    }
}