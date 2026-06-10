<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CandidateDocumentController extends Controller
{
    private const REQUIRED_DOCUMENTS = [
        'headshot' => [
            'title' => 'Please upload a headshot of yourself',
            'description' => 'Please upload a clear recent photo.',
        ],
        'government_id' => [
            'title' => "Driver's license or government issued card",
            'description' => 'Upload a valid identity document.',
        ],
        'recommendation_letter' => [
            'title' => 'Letter(s) of recommendation',
            'description' => 'Upload at least one recommendation letter.',
        ],
        'additional_recommendation_letter' => [
            'title' => 'Additional Letter(s) of recommendation',
            'description' => 'Upload an additional recommendation letter.',
        ],
        'secondary_recommendation_letter' => [
            'title' => 'Additional Letter(s) of recommendation',
            'description' => 'Upload another recommendation letter if available.',
        ],
        'nanny_resume' => [
            'title' => 'Nanny Resume',
            'description' => 'Make sure your resume highlights your nanny and childcare experience.',
        ],
    ];

    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;
            $documents = CandidateDocument::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->get();

            $agreements = DocumentTemplate::with(['fields', 'signers'])
                ->where('agency_id', $agencyId)
                ->where('user_type', 'candidate')
                ->latest()
                ->get()
                ->map(fn (DocumentTemplate $template): array => $this->formatAgreement($template, $documents));

            return $this->sendResponse([
                'agreements' => $agreements->values(),
                'required_documents' => collect(self::REQUIRED_DOCUMENTS)
                    ->map(fn (array $definition, string $key): array => $this->formatRequiredDocument($key, $definition, $documents))
                    ->values(),
                'additional_documents' => $documents
                    ->where('category', 'additional')
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (CandidateDocument $document): array => $this->formatUploadedDocument($document)),
            ], 'Documents retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function showAgreement(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || ! $this->canUseTemplate($request, $documentTemplate)) {
                return $this->sendError('Document not found.', [], 404);
            }

            $candidateDocument = CandidateDocument::where('candidate_id', $candidate->id)
                ->where('document_template_id', $documentTemplate->id)
                ->where('category', 'agreement')
                ->first();

            return $this->sendResponse([
                'agreement' => $this->formatAgreement($documentTemplate->load(['fields', 'signers']), collect([$candidateDocument])->filter()),
                'content_html' => $documentTemplate->content,
                'fields' => $documentTemplate->fields,
                'signers' => $documentTemplate->signers,
                'file_url' => $documentTemplate->file_path ? asset($documentTemplate->file_path) : null,
            ], 'Agreement retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function signAgreement(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || ! $this->canUseTemplate($request, $documentTemplate)) {
                return $this->sendError('Document not found.', [], 404);
            }

            $validated = $request->validate([
                'signature' => 'required|string|max:255',
                'agree' => 'required|boolean|accepted',
            ]);

            $document = CandidateDocument::updateOrCreate(
                [
                    'candidate_id' => $candidate->id,
                    'document_template_id' => $documentTemplate->id,
                    'category' => 'agreement',
                ],
                [
                    'agency_id' => $request->current_agency->id,
                    'title' => $documentTemplate->name,
                    'description' => "You've already signed this agreement.",
                    'status' => 'signed',
                    'signature' => $validated['signature'],
                    'signed_at' => now(),
                ]
            );

            return $this->sendResponse($this->formatUploadedDocument($document), 'Agreement signed successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function uploadRequired(Request $request, string $documentKey): JsonResponse
    {
        return $this->storeUploadedDocument($request, 'required', $documentKey);
    }

    public function deleteRequired(Request $request, string $documentKey): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || ! array_key_exists($documentKey, self::REQUIRED_DOCUMENTS)) {
                return $this->sendError('Document not found.', [], 404);
            }

            $document = CandidateDocument::where('candidate_id', $candidate->id)
                ->where('required_key', $documentKey)
                ->where('category', 'required')
                ->first();

            if (! $document) {
                return $this->sendError('Document not found.', [], 404);
            }

            $this->deleteStoredFile($document);
            $document->delete();

            return $this->sendResponse([], 'Document deleted successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function storeAdditional(Request $request): JsonResponse
    {
        return $this->storeUploadedDocument($request, 'additional');
    }

    public function destroyAdditional(Request $request, CandidateDocument $candidateDocument): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $candidateDocument->candidate_id !== $candidate->id || $candidateDocument->category !== 'additional') {
                return $this->sendError('Document not found.', [], 404);
            }

            $this->deleteStoredFile($candidateDocument);
            $candidateDocument->delete();

            return $this->sendResponse([], 'Document deleted successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function downloadUploaded(Request $request, CandidateDocument $candidateDocument): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $candidateDocument->candidate_id !== $candidate->id) {
            return $this->sendError('Document not found.', [], 404);
        }

        return $this->sendResponse([
            'file_url' => $candidateDocument->file_url,
            'expires_at' => now()->addMinutes(15)->toDateTimeString(),
        ], 'Document URL generated.', 200);
    }

    private function storeUploadedDocument(Request $request, string $category, ?string $documentKey = null): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($category === 'required' && ! array_key_exists((string) $documentKey, self::REQUIRED_DOCUMENTS)) {
                return $this->sendError('Document not found.', [], 404);
            }

            $validated = $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $definition = $documentKey ? self::REQUIRED_DOCUMENTS[$documentKey] : null;
            $existingDocument = $category === 'required'
                ? CandidateDocument::where('candidate_id', $candidate->id)
                    ->where('required_key', $documentKey)
                    ->where('category', 'required')
                    ->first()
                : null;

            if ($existingDocument) {
                $this->deleteStoredFile($existingDocument);
            }

            $file = $request->file('file');
            $path = $file->store('candidate-documents', 'public');

            $documentData = [
                'agency_id' => $request->current_agency->id,
                'title' => $validated['title'] ?? $definition['title'] ?? $file->getClientOriginalName(),
                'description' => $validated['description'] ?? $definition['description'] ?? null,
                'file_path' => $path,
                'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'status' => 'uploaded',
            ];

            if ($category === 'required') {
                $document = CandidateDocument::updateOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'required_key' => $documentKey,
                        'category' => 'required',
                    ],
                    $documentData
                );
            } else {
                $document = CandidateDocument::create([
                    'candidate_id' => $candidate->id,
                    'required_key' => null,
                    'category' => 'additional',
                    ...$documentData,
                ]);
            }

            return $this->sendResponse($this->formatUploadedDocument($document), 'Document uploaded successfully.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function formatAgreement(DocumentTemplate $template, $documents): array
    {
        $document = $documents
            ->where('document_template_id', $template->id)
            ->where('category', 'agreement')
            ->first();

        return [
            'id' => $template->id,
            'document_record_id' => $document?->id,
            'title' => $template->name,
            'description' => $document ? "You've already signed this agreement." : 'Please review and sign this agreement.',
            'status' => $document?->status ?? 'pending',
            'signed_at' => $document?->signed_at?->toDateTimeString(),
            'content_type' => $template->content_type,
            'file_url' => $template->file_path ? asset($template->file_path) : null,
            'can_sign' => ! $document,
            'can_view' => true,
        ];
    }

    private function formatRequiredDocument(string $documentKey, array $definition, $documents): array
    {
        $document = $documents
            ->where('required_key', $documentKey)
            ->where('category', 'required')
            ->first();

        return [
            'key' => $documentKey,
            'document_record_id' => $document?->id,
            'title' => $definition['title'],
            'description' => $document ? "You've already uploaded this document." : $definition['description'],
            'status' => $document?->status ?? 'missing',
            'file_name' => $document?->original_file_name,
            'file_url' => $document?->file_url,
            'can_upload' => ! $document,
            'can_view' => (bool) $document,
            'can_replace' => (bool) $document,
            'can_delete' => (bool) $document,
        ];
    }

    private function formatUploadedDocument(CandidateDocument $document): array
    {
        return [
            'id' => $document->id,
            'category' => $document->category,
            'required_key' => $document->required_key,
            'title' => $document->title,
            'description' => $document->description,
            'status' => $document->status,
            'file_name' => $document->original_file_name,
            'file_url' => $document->file_url,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'signed_at' => $document->signed_at?->toDateTimeString(),
            'can_view' => (bool) ($document->file_path || $document->signed_at),
            'can_replace' => $document->category !== 'agreement',
            'can_delete' => $document->category !== 'agreement',
        ];
    }

    private function canUseTemplate(Request $request, DocumentTemplate $documentTemplate): bool
    {
        return $documentTemplate->agency_id === $request->current_agency->id
            && $documentTemplate->user_type === 'candidate';
    }

    private function deleteStoredFile(CandidateDocument $document): void
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
    }
}
