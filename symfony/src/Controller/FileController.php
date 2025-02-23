<?php

namespace App\Controller;

use App\Form\FileUploadType;
use App\Repository\FileUploadRepository;
use App\Service\FileUploader;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use DateTime;

use function count;

class FileController extends AbstractController
{
    private const ITEMS_PER_PAGE = 10; // Define items per page constant

    public function __construct(
        private readonly FileUploader $fileUploader,
        private readonly FileUploadRepository $fileUploadRepository,
    ) {
    }

    #[Route('file/upload-from-url', name: 'upload_from_url_form', methods: ['GET'])]
    public function showUrlUploadForm(): Response
    {
        return $this->render('file_upload/url_upload.html.twig');
    }

    #[Route('/files', name: 'file_list_form', methods: ['GET'])]
    public function listForm(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $filename = $request->query->get('filename');

        // Validate and parse dates
        $startDate = $this->validateAndParseDate($request->query->get('startDate'));
        $endDate = $this->validateAndParseDate($request->query->get('endDate'));

        // Validate date range if both dates are provided
        if ($startDate && $endDate && $startDate > $endDate) {
            throw $this->createNotFoundException('Start date must be before end date');
        }

        $path = $request->query->get('path');

        $pagination = $this->fileUploadRepository->findByFiltersPaginated(
            $filename,
            $startDate,
            $endDate,
            $path,
            $page,
            self::ITEMS_PER_PAGE
        );

        return $this->render('file/list.html.twig', $pagination + ['perPage' => self::ITEMS_PER_PAGE]);
    }

    private function validateAndParseDate(?string $dateString): ?DateTime
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            $date = new DateTime($dateString);

            // Optional: Add specific format validation
            $validator = Validation::createValidator();
            $violations = $validator->validate($dateString, [
                new Assert\DateTime([
                    'format' => 'Y-m-d', // Adjust format as needed
                    'message' => 'Invalid date format. Please use YYYY-MM-DD'
                ])
            ]);

            if (count($violations) > 0) {
                throw $this->createNotFoundException($violations[0]->getMessage());
            }

            return $date;
        } catch (Exception $e) {
            throw $this->createNotFoundException('Invalid date format');
        }
    }

    #[Route('/file/upload', name: 'file_upload_form', methods: ['GET', 'POST'])]
    public function uploadForm(Request $request): Response
    {
        $form = $this->createForm(FileUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                $fileName = $this->fileUploader->upload($file);

                $this->addFlash(
                    'success',
                    'File has been uploaded: ' . $fileName['filename']
                );

                return $this->redirectToRoute('file_upload_form');
            }
        }

        return $this->render('file_upload/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}