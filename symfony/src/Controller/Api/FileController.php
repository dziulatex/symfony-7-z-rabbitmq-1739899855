<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Command\ParseFileCommand;
use App\Command\UploadFileFromUrlCommand;
use App\Repository\FileUploadRepository;
use App\Request\UrlUploadRequest;
use App\Service\FileProcessing\FileProcessingStatus;
use App\Service\FileProcessing\FileProgressTracker;
use Exception;
use InvalidArgumentException;
use Redis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

use function in_array;

class FileController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly FileUploadRepository $repository,
    ) {
    }

    #[Route('/api/file/upload-from-url', name: 'upload_from_url', methods: ['POST'])]
    public function uploadFromUrlAction(
        #[MapRequestPayload] UrlUploadRequest $urlRequest,
    ): Response {
        try {
            $command = new UploadFileFromUrlCommand($urlRequest->getUrl());
            $this->messageBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'File upload initiated successfully'
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Assert\Collection(
        fields: [
            'fileId' => new Assert\Uuid()
        ]
    )]
    #[Route('/api/file/status/{fileId}', name: 'file_status', methods: ['GET'])]
    public function getFileStatusAction(
        string $fileId,
        FileProgressTracker $fileProgressTracker,
        Redis $redis  // Inject Redis service
    ): Response
    {
        try {
            // Check if client supports SSE
            if (!str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/event-stream')) {
                // Fallback to regular status check for non-SSE requests
                try {
                    $status = $fileProgressTracker->getStatus($fileId);
                    return $this->json([
                        'success' => true,
                        'data' => $status
                    ]);
                } catch (InvalidArgumentException $e) {
                    return $this->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            $response = new StreamedResponse(function () use ($fileId, $fileProgressTracker, $redis) {
                set_time_limit(0);
                $channel = FileProgressTracker::CHANNEL_PREFIX . $fileId;

                // Send initial status
                $initialStatus = $fileProgressTracker->getStatus($fileId);
                echo "data: " . json_encode(['success' => true, 'data' => $initialStatus]) . "\n\n";
                flush();

                // If already done, don't subscribe
                if (in_array($initialStatus['status'], [
                    FileProcessingStatus::PROCESSED->value,
                    FileProcessingStatus::ERROR->value
                ], true)) {
                    if ($initialStatus['status'] === FileProcessingStatus::ERROR->value) {
                        return $initialStatus['error_message'];
                    } else {
                        return '';
                    }
                }

                // Just subscribe directly - Redis handles the loop
                $redis->subscribe([$channel], function ($redis, $channel, $message) {
                    $data = json_decode($message, true);
                    echo "data: " . json_encode(['success' => true, 'data' => $data]) . "\n\n";
                    flush();

                    // Return false to end subscription when done
                    return !in_array($data['status'], [
                        FileProcessingStatus::PROCESSED->value,
                        FileProcessingStatus::ERROR->value
                    ], true);
                });
            });

            $response->headers->set('Content-Type', 'text/event-stream');
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('Connection', 'keep-alive');
            $response->headers->set('X-Accel-Buffering', 'no');

            return $response;
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Assert\Collection(
        fields: [
            'fileId' => new Assert\Uuid()
        ]
    )]
    #[Route('/api/file/parse/{fileId}', name: 'parse_file', methods: ['POST'])]
    public function parseFileAction(
        string $fileId,
        EntityManagerInterface $entityManager,
    ): Response {
        try {
            if (!$file = $this->repository->find($fileId)) {
                return $this->json([
                    'success' => false,
                    'message' => 'File doesnt exist',
                ], Response::HTTP_BAD_REQUEST);
            }
            if (!in_array(
                $file->getState()->value,
                [FileProcessingStatus::NEW->value, FileProcessingStatus::ERROR->value],
                true
            )) {
                return $this->json([
                    'success' => false,
                    'message' => 'You can only parse new or failed files!',
                ], Response::HTTP_BAD_REQUEST);
            }
            $file->markAsWaiting();
            $entityManager->flush();
            $command = new ParseFileCommand(Uuid::fromString($fileId));
            $this->messageBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'File parsing job dispatched successfully' // More accurate message
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}