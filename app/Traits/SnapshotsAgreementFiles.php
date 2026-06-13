<?php

namespace App\Traits;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Copies an image-based agreement template's file onto the public storage
 * disk at signing time, so the signed document keeps rendering even after
 * the agency edits or deletes the template (which removes the original file).
 */
trait SnapshotsAgreementFiles
{
    protected function snapshotAgreementFile(DocumentTemplate $template, string $directory): ?string
    {
        if ($template->content_type !== 'image' || ! $template->file_path) {
            return null;
        }

        $sourcePath = public_path($template->file_path);

        if (! File::exists($sourcePath)) {
            return null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $snapshotPath = $directory.'/agreement_'.uniqid().($extension ? '.'.$extension : '');

        Storage::disk('public')->put($snapshotPath, File::get($sourcePath));

        return $snapshotPath;
    }
}
