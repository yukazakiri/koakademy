<?php

declare(strict_types=1);

use App\Models\DocumentLocation;

it('resolves student document paths to r2 urls while preserving absolute values', function (): void {
    config(['filesystems.disks.r2.url' => 'https://r2.dccp.edu.ph']);

    $documentLocation = new DocumentLocation([
        'picture_1x1' => 'students/206528/documents/photo.jpg',
        'birth_certificate' => 'https://cdn.example.com/birth-certificate.pdf',
        'form_137' => '/storage/legacy/form-137.pdf',
    ]);

    expect($documentLocation->toResolvedDocumentArray())
        ->toMatchArray([
            'picture_1x1' => 'https://r2.dccp.edu.ph/students/206528/documents/photo.jpg',
            'birth_certificate' => 'https://cdn.example.com/birth-certificate.pdf',
            'form_137' => '/storage/legacy/form-137.pdf',
        ]);
});
