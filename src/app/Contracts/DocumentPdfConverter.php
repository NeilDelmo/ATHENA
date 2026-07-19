<?php

namespace App\Contracts;

interface DocumentPdfConverter
{
    public function convertDocx(string $contents): string;
}
