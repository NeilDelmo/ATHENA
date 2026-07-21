<?php

namespace App\Contracts;

interface DocumentPdfConverter
{
    public function convertDocx(string $contents): string;

    public function convertXlsx(string $contents): string;
}
