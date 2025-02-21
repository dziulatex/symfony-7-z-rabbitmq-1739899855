<?php

namespace App\Service\FileProcessing;

enum FileProcessingStatus: string
{
    case NEW = 'new';
    case PROCESSING = 'processing';
    case WAITING = 'waiting';
    case PROCESSED = 'processed';
    case ERROR = 'error';
}