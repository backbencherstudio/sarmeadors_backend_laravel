<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AgencyCandidateDocumentController extends Controller
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

    // GET /agency/candidates/{id}/documents
    public function index($candidateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $documents = CandidateDocument::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->get();

        $agreements = DocumentTemplate::with(['fields', 'signers'])
            ->where('agency_id', $agencyId)
            ->where('user_type', 'candidate')
            ->latest()
            ->get()
            ->map(fn (DocumentTemplate $template) => $this->formatAgreement($template, $documents));

        $requiredDocuments = collect(self::REQUIRED_DOCUMENTS)
            ->map(fn (array $definition, string $key) => $this->formatRequiredDocument($key, $definition, $documents))
            ->values();

        $additionalDocuments = $documents
            ->where('category', 'additional')
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (CandidateDocument $document) => $this->formatUploadedDocument($document));

        return response()->json([
            'status' => true,
            'data' => [
                'agreements' => $agreements->values(),
                'required_documents' => $requiredDocuments,
                'additional_documents' => $additionalDocuments->values(),
            ],
        ]);
    }

    /**
     * Admin uploads or replaces a required document on the candidate's behalf.
     * POST /agency/candidates/{id}/documents/required/{key}
     */
    public function uploadRequired(Request $request, $candidateId, string $documentKey)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        if (! array_key_exists($documentKey, self::REQUIRED_DOCUMENTS)) {
            return response()->json(['status' => false, 'message' => 'Document type not found.'], 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $existing = CandidateDocument::where('candidate_id', $candidate->id)
            ->where('required_key', $documentKey)
            ->where('category', 'required')
            ->first();

        if ($existing?->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $file = $request->file('file');
        $path = $file->store('candidate-documents', 'public');
        $definition = self::REQUIRED_DOCUMENTS[$documentKey];

        $document = CandidateDocument::updateOrCreate(
            [
                'candidate_id' => $candidate->id,
                'required_key' => $documentKey,
                'category' => 'required',
            ],
            [
                'agency_id' => $agencyId,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'file_path' => $path,
                'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'status' => 'uploaded',
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $this->formatUploadedDocument($document),
        ]);
    }

    /**
     * Admin deletes any document (required or additional) by its record ID.
     * DELETE /agency/candidates/{id}/documents/{documentId}
     */
    public function destroy($candidateId, $documentId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $document = CandidateDocument::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->findOrFail($documentId);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }

    /**
     * Admin uploads an additional document on the candidate's behalf.
     * POST /agency/candidates/{id}/documents/additional
     */
    public function uploadAdditional(Request $request, $candidateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('file');
        $path = $file->store('candidate-documents', 'public');

        $document = CandidateDocument::create([
            'agency_id' => $agencyId,
            'candidate_id' => $candidate->id,
            'required_key' => null,
            'category' => 'additional',
            'title' => $request->input('title') ?? $file->getClientOriginalName(),
            'description' => $request->input('description'),
            'file_path' => $path,
            'original_file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'uploaded',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $this->formatUploadedDocument($document),
        ], 201);
    }

    /**
     * Admin updates an additional document's title/description.
     * PATCH /agency/candidates/{id}/documents/additional/{documentId}
     */
    public function updateAdditional(Request $request, $candidateId, $documentId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $document = CandidateDocument::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->where('category', 'additional')
            ->findOrFail($documentId);

        $data = $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        if ($request->hasFile('file')) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $file = $request->file('file');
            $data['file_path'] = $file->store('candidate-documents', 'public');
            $data['original_file_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getClientMimeType();
            $data['size'] = $file->getSize();
        }

        unset($data['file']);
        $document->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Document updated successfully.',
            'data' => $this->formatUploadedDocument($document->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
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
            'description' => $definition['description'],
            'status' => $document?->status ?? 'missing',
            'file_name' => $document?->original_file_name,
            'file_url' => $document?->file_url,
            'uploaded_at' => $document?->updated_at?->toDateTimeString(),
            'can_upload' => ! $document,
            'can_replace' => (bool) $document,
            'can_delete' => (bool) $document,
        ];
    }

    /**
     * @return array<string, mixed>
     */
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
            'status' => $document?->status ?? 'pending',
            'signed_at' => $document?->signed_at?->toDateTimeString(),
            'file_url' => $document?->file_url ?? ($template->file_path ? asset($template->file_path) : null),
            'can_view' => (bool) $document,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUploadedDocument(CandidateDocument $document): array
    {
        return [
            'document_record_id' => $document->id,
            'category' => $document->category,
            'required_key' => $document->required_key,
            'title' => $document->title,
            'description' => $document->description,
            'status' => $document->status,
            'file_name' => $document->original_file_name,
            'file_url' => $document->file_url,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'uploaded_at' => $document->updated_at?->toDateTimeString(),
            'can_replace' => true,
            'can_delete' => true,
        ];
    }
}
