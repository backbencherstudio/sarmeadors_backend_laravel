<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Agency-side "Documents" tab on a client's detail page. Document templates
 * (`DocumentTemplate`, user_type = 'client') are global to the agency — every
 * client implicitly sees all of them, mirroring the client-facing signing flow
 * in `App\Http\Controllers\Client\ClientDocumentController`. Only the client
 * can sign a document; the agency admin can only toggle whether a (not yet
 * signed) document is active for this client, and view a signed document's
 * frozen content. A `ClientDocument` row only exists once it's been toggled
 * inactive or the client has signed it.
 */
class ClientDocumentController extends Controller
{
    // GET /agency/clients/{id}/documents
    public function index($clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $clientDocuments = ClientDocument::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->get();

        $templates = DocumentTemplate::where('agency_id', $agencyId)
            ->where('user_type', 'client')
            ->latest()
            ->get();

        $documents = $templates->map(fn (DocumentTemplate $template) => $this->formatDocumentCard(
            $template,
            $clientDocuments->firstWhere('document_template_id', $template->id)
        ))->values();

        return response()->json([
            'status' => true,
            'message' => 'Client documents retrieved successfully',
            'data' => $documents,
        ]);
    }

    // GET /agency/clients/{id}/documents/templates
    public function availableTemplates($clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $trackedTemplateIds = ClientDocument::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->pluck('document_template_id');

        $templates = DocumentTemplate::where('agency_id', $agencyId)
            ->where('user_type', 'client')
            ->whereNotIn('id', $trackedTemplateIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'status' => true,
            'data' => $templates->map(fn ($template) => ['id' => $template->id, 'name' => $template->name])->values(),
        ]);
    }

    // POST /agency/clients/{id}/documents
    public function store(Request $request, $clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $request->validate([
            'document_template_id' => [
                'required',
                'integer',
                Rule::exists('document_templates', 'id')->where(function ($query) use ($agencyId) {
                    $query->where('agency_id', $agencyId)->where('user_type', 'client');
                }),
            ],
        ]);

        $template = DocumentTemplate::findOrFail($request->document_template_id);

        $clientDocument = ClientDocument::firstOrCreate(
            [
                'client_id' => $client->id,
                'document_template_id' => $template->id,
            ],
            [
                'agency_id' => $agencyId,
                'title' => $template->name,
                'status' => 'pending',
                'is_active' => true,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Document added to client successfully',
            'data' => $this->formatDocumentCard($template, $clientDocument),
        ]);
    }

    /**
     * "Details"/eye button: view this client's copy of the document — the
     * frozen signed content + signature/audit trail once signed, otherwise
     * the live template content.
     */
    public function show($clientId, $templateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $template = DocumentTemplate::where('agency_id', $agencyId)
            ->where('user_type', 'client')
            ->findOrFail($templateId);

        $clientDocument = ClientDocument::where('client_id', $client->id)
            ->where('document_template_id', $template->id)
            ->first();

        $signed = $clientDocument?->status === 'signed';

        return response()->json([
            'status' => true,
            'data' => array_merge($this->formatDocumentCard($template, $clientDocument), [
                'content_html' => $signed && $clientDocument->signed_content !== null
                    ? $clientDocument->signed_content
                    : $template->content,
                'organization' => [
                    'name' => $template->org_name,
                    'signer_name' => $template->org_signer_name,
                ],
                'audit_trail' => [
                    'added_at' => $template->created_at,
                    'signed_at' => $clientDocument?->signed_at,
                    'signed_ip' => $clientDocument?->signed_ip,
                ],
            ]),
        ]);
    }

    /**
     * "Download PDF" button: renders this client's copy of the document
     * (frozen signed content once signed, otherwise the live template) as a
     * downloadable PDF file.
     */
    public function downloadPdf($clientId, $templateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $template = DocumentTemplate::where('agency_id', $agencyId)
            ->where('user_type', 'client')
            ->findOrFail($templateId);

        $clientDocument = ClientDocument::where('client_id', $client->id)
            ->where('document_template_id', $template->id)
            ->first();

        $signed = $clientDocument?->status === 'signed';
        $contentType = ($signed ? $clientDocument->signed_content_type : null) ?? $template->content_type;

        if ($contentType === 'image') {
            $imagePath = $signed && $clientDocument->file_path
                ? Storage::disk('public')->path($clientDocument->file_path)
                : ($template->file_path ? public_path($template->file_path) : null);

            $html = $imagePath && File::exists($imagePath)
                ? '<img src="'.$imagePath.'" style="width:100%;">'
                : '<p>Document file not found.</p>';
        } else {
            $html = $signed && $clientDocument->signed_content !== null
                ? $clientDocument->signed_content
                : ($template->content ?? '');
        }

        $filename = Str::slug($client->full_name.'-'.$template->name).'.pdf';

        return Pdf::loadHTML($html)->download($filename);
    }

    /**
     * Flip whether this document is active/required for this client — hitting
     * this route just toggles the current state, regardless of signed status.
     */
    public function toggleActive($clientId, $templateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $template = DocumentTemplate::where('agency_id', $agencyId)
            ->where('user_type', 'client')
            ->findOrFail($templateId);

        $clientDocument = ClientDocument::firstOrCreate(
            [
                'client_id' => $client->id,
                'document_template_id' => $template->id,
            ],
            [
                'agency_id' => $agencyId,
                'title' => $template->name,
                'status' => 'pending',
                'is_active' => true,
            ]
        );

        $clientDocument->update(['is_active' => ! $clientDocument->is_active]);

        return response()->json([
            'status' => true,
            'message' => 'Document availability updated successfully',
            'data' => $this->formatDocumentCard($template, $clientDocument->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDocumentCard(?DocumentTemplate $template, ?ClientDocument $document): array
    {
        $signed = $document?->status === 'signed';

        return [
            'document_record_id' => $document?->id,
            'document_template_id' => $template?->id,
            'title' => $document?->title ?? $template?->name,
            'status' => $document?->status ?? 'pending',
            'is_active' => $document?->is_active ?? true,
            'added_at' => $document?->created_at ?? $template?->created_at,
            'signed_at' => $document?->signed_at,
            'signature' => $document?->signature,
            'content_type' => $template?->content_type,
            'file_url' => $signed && $document?->file_path
                ? $document->file_url
                : ($template?->file_path ? asset($template->file_path) : null),
            'can_edit' => ! $signed,
        ];
    }
}
