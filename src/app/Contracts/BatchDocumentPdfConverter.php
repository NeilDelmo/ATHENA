<?php

namespace App\Contracts;

interface BatchDocumentPdfConverter extends DocumentPdfConverter
{
    /**
     * @param  array<string, array{contents: string, format: 'docx'|'xlsx'}>  $documents
     * @return array<string, string>
     */
    public function convertDocuments(array $documents): array;
}
