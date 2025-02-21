<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class FileUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Plik',
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/csv',
                            'application/x-csv',
                            'text/x-csv',
                            'text/comma-separated-values',
                            'text/x-comma-separated-values',
                        ],
                        'mimeTypesMessage' => 'Proszę przesłać plik w formacie CSV lub arkusz kalkulacyjny',
                    ])
                ],
            ]);
    }
}