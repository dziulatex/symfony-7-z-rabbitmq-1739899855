<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\FileUploadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

// Import EntityManagerInterface

class ClientController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        private readonly FileUploadRepository $fileUploadRepository,
        EntityManagerInterface $entityManager // Inject EntityManager
    )
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/clients/download-errors', name: 'app_clients_download_errors')]
    public function downloadErrorFile(Request $request): Response
    {
        $fileUploadId = $request->query->get('fileUploadId');

        if (!$fileUploadId) {
            throw $this->createNotFoundException('No file upload ID provided.');
        }

        $selectedFileUpload = $this->fileUploadRepository->find($fileUploadId);

        if (!$selectedFileUpload) {
            throw $this->createNotFoundException('File upload not found.');
        }

        $errorFilePath = $selectedFileUpload->getPath() . '/error/' . $selectedFileUpload->getFilename();

        // Check if the error file exists
        if (!file_exists($errorFilePath)) {
            //  throw $this->createNotFoundException('Error file not found.'); can return to list with a flash message
            $this->addFlash('error', 'Error file not found for the selected upload.');
            return $this->redirectToRoute('app_clients_list');
        }
        if (!$selectedFileUpload->isProcessed()) {
            $this->addFlash('error', 'Error file not found for the selected upload.');
            return $this->redirectToRoute('app_clients_list');
        }
        // Use BinaryFileResponse to send the file
        $response = new BinaryFileResponse($errorFilePath);

        // Set content disposition to force download
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'errors_' . $selectedFileUpload->getFilename()
        );
        $response->headers->set('Content-Type', 'text/csv');

        return $response;
    }

    #[Route('/clients', name: 'app_clients_list')]
    public function list(
        Request $request,
        ClientRepository $clientRepository
    ): Response {
        $fileUploadId = $request->query->get('fileUploadId');
        $page = $request->query->getInt('page', 1);
        $limit = 10;

        $selectedFileUpload = null;
        if ($fileUploadId) {
            $selectedFileUpload = $this->fileUploadRepository->find($fileUploadId);
        }

        $fileUploads = $this->fileUploadRepository->findAll();


        $result = $clientRepository->findClientsPaginated($selectedFileUpload, $page, $limit);
        $clients = $result['clients'];
        $totalCount = $result['totalCount'];


        return $this->render('client/list.html.twig', [
            'clients' => $clients,
            'fileUploads' => $fileUploads,
            'selectedFileUpload' => $selectedFileUpload,
            'currentPage' => $page,
            'limit' => $limit,
            'totalCount' => $totalCount,
        ]);
    }
}