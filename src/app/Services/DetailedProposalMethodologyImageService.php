<?php

namespace App\Services;

use App\Models\ProposalDraft;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DetailedProposalMethodologyImageService
{
    public function store(ProposalDraft $proposalDraft, UploadedFile $image): string
    {
        return $image->store($proposalDraft->storageDirectory().'/detailed-proposal/methodology-images', 'local');
    }

    /** @param array<string, mixed> $image */
    public function contents(array $image): ?string
    {
        $uploadedImage = $image['image'] ?? null;

        if ($uploadedImage instanceof UploadedFile && $uploadedImage->isValid()) {
            $contents = file_get_contents($uploadedImage->getRealPath());

            return $contents === false ? null : $contents;
        }

        $storedPath = $image['stored_path'] ?? null;

        if (! is_string($storedPath) || ! Storage::disk('local')->exists($storedPath)) {
            return null;
        }

        return Storage::disk('local')->get($storedPath);
    }

    /** @param array<string, mixed> $image */
    public function dataUrl(array $image): ?string
    {
        $contents = $this->contents($image);

        if ($contents === null) {
            return null;
        }

        $mimeType = $this->mimeType($image);

        if ($mimeType === null) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    /** @param array<string, mixed> $image */
    public function mimeType(array $image): ?string
    {
        $uploadedImage = $image['image'] ?? null;

        if ($uploadedImage instanceof UploadedFile && $uploadedImage->isValid()) {
            return $uploadedImage->getMimeType();
        }

        $mimeType = $image['mime_type'] ?? null;

        return is_string($mimeType) && str_starts_with($mimeType, 'image/')
            ? $mimeType
            : null;
    }
}
