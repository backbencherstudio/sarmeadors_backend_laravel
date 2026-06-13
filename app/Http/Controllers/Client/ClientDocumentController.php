<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SignDocumentRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTemplate;
use App\Notifications\AgreementSignedNotification;
use App\Traits\ResolvesClient;
use App\Traits\SendsNotifications;
use App\Traits\SnapshotsAgreementFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClientDocumentController extends Controller
{
    use ResolvesClient;
    use SendsNotifications;
    use SnapshotsAgreementFiles;

    // GET /client/documents
    public function index(Request $request): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        $documents = ClientDocument::where('client_id', $client->id)
            ->where('agency_id', $request->current_agency->id)
            ->get();

        $agreements = DocumentTemplate::where('agency_id', $request->current_agency->id)
            ->where('user_type', 'client')
            ->latest()
            ->get()
            ->map(fn (DocumentTemplate $template): array => $this->formatAgreementCard(
                $template,
                $documents->firstWhere('document_template_id', $template->id)
            ));

        return $this->sendResponse([
            'agreements' => $agreements->values(),
        ], 'Documents retrieved successfully.', 200);
    }

    // GET /client/documents/{document}
    public function show(Request $request, DocumentTemplate $document): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        if (! $this->canUseTemplate($request, $document)) {
            return $this->sendError('Document not found.', [], 404);
        }

        $clientDocument = $this->findClientDocument($client, $document);

        return $this->sendResponse([
            'agreement' => $this->formatAgreementCard($document, $clientDocument),
            'content_html' => $this->resolveContent($document, $clientDocument),
            'file_url' => $this->resolveFileUrl($document, $clientDocument),
            'organization' => [
                'name' => $document->org_name,
                'signer_name' => $document->org_signer_name,
            ],
            'client_signature' => $clientDocument ? [
                'signature' => $clientDocument->signature,
                'signed_at' => $clientDocument->signed_at?->toDateTimeString(),
                'signed_at_label' => $this->formatAuditTimestamp($clientDocument->signed_at),
            ] : null,
            'audit_trail' => [
                'added_at' => $document->created_at?->toDateTimeString(),
                'added_at_label' => $this->formatAuditTimestamp($document->created_at),
                'signed_at' => $clientDocument?->signed_at?->toDateTimeString(),
                'signed_at_label' => $this->formatAuditTimestamp($clientDocument?->signed_at),
                'ip' => $clientDocument?->signed_ip,
            ],
        ], 'Document retrieved successfully.', 200);
    }

    // POST /client/documents/{document}/sign
    public function sign(SignDocumentRequest $request, DocumentTemplate $document): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        if (! $this->canUseTemplate($request, $document)) {
            return $this->sendError('Document not found.', [], 404);
        }

        if ($this->findClientDocument($client, $document)?->status === 'signed') {
            return $this->sendError('You have already signed this agreement.', [], 422);
        }

        $validated = $request->validated();

        $clientDocument = ClientDocument::updateOrCreate(
            [
                'client_id' => $client->id,
                'document_template_id' => $document->id,
            ],
            [
                'agency_id' => $request->current_agency->id,
                'title' => $document->name,
                'description' => "You've already signed this agreement.",
                'status' => 'signed',
                'signature' => $validated['signature'],
                'signed_content' => $document->content_type === 'text' ? $document->content : null,
                'signed_content_type' => $document->content_type,
                'file_path' => $this->snapshotAgreementFile($document, 'client-documents'),
                'signed_ip' => $request->ip(),
                'signed_at' => now(),
            ]
        );

        $signerName = trim($client->first_name.' '.$client->last_name);

        $this->notifyAgencyAdmins(
            $request->current_agency->id,
            'agreement_signed',
            'Agreement Signed',
            sprintf('%s signed "%s".', $signerName, $document->name),
            null,
            [
                'document_template_id' => $document->id,
                'client_document_id' => $clientDocument->id,
                'signer_type' => 'client',
            ]
        );

        $this->notifyEmailRecipients(
            $document->notification_emails,
            new AgreementSignedNotification($document->name, $signerName, 'client')
        );

        return $this->sendResponse(
            $this->formatAgreementCard($document, $clientDocument),
            'Agreement signed successfully.',
            200
        );
    }

    // GET /client/documents/{document}/download
    public function download(Request $request, DocumentTemplate $document): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        if (! $this->canUseTemplate($request, $document)) {
            return $this->sendError('Document not found.', [], 404);
        }

        $clientDocument = $this->findClientDocument($client, $document);

        return $this->sendResponse([
            'title' => $document->name,
            'content_type' => $document->content_type,
            'content_html' => $this->resolveContent($document, $clientDocument),
            'file_url' => $this->resolveFileUrl($document, $clientDocument),
            'signature' => $clientDocument?->signature,
            'signed_at' => $clientDocument?->signed_at?->toDateTimeString(),
            'signed_ip' => $clientDocument?->signed_ip,
        ], 'Download data generated.', 200);
    }

    /**
     * Signed agreements always render the content frozen at signing time;
     * pending agreements follow the live template.
     */
    private function resolveContent(DocumentTemplate $template, ?ClientDocument $clientDocument): ?string
    {
        if ($clientDocument?->status === 'signed' && $clientDocument->signed_content !== null) {
            return $clientDocument->signed_content;
        }

        return $template->content;
    }

    private function resolveFileUrl(DocumentTemplate $template, ?ClientDocument $clientDocument): ?string
    {
        if ($clientDocument?->status === 'signed' && $clientDocument->file_path) {
            return $clientDocument->file_url;
        }

        return $template->file_path ? asset($template->file_path) : null;
    }

    private function findClientDocument(Client $client, DocumentTemplate $template): ?ClientDocument
    {
        return ClientDocument::where('client_id', $client->id)
            ->where('document_template_id', $template->id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAgreementCard(DocumentTemplate $template, ?ClientDocument $document): array
    {
        $signed = $document?->status === 'signed';

        return [
            'id' => $template->id,
            'document_record_id' => $document?->id,
            'title' => $template->name,
            'description' => $signed ? "You've already signed this agreement." : 'Please review and sign this agreement.',
            'status' => $document?->status ?? 'pending',
            'signed_at' => $document?->signed_at?->toDateTimeString(),
            'content_type' => $template->content_type,
            'file_url' => $template->file_path ? asset($template->file_path) : null,
            'can_sign' => ! $signed,
            'can_view' => true,
        ];
    }

    private function formatAuditTimestamp(?Carbon $timestamp): ?string
    {
        return $timestamp?->format('D M d Y \a\t g:i:s A');
    }

    private function canUseTemplate(Request $request, DocumentTemplate $documentTemplate): bool
    {
        return $documentTemplate->agency_id === $request->current_agency->id
            && $documentTemplate->user_type === 'client';
    }
}
