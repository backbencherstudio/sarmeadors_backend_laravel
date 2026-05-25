<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDocumentController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/documents
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'documents' => [
                [
                    'id' => 1,
                    'title' => 'Client - Agency Agreement Placement Fee & Refund Policy',
                    'description' => 'Please review and sign this agreement.',
                    'status' => 'pending',
                    'signed_at' => null,
                ],
                [
                    'id' => 2,
                    'title' => 'Client Agency Agreement Midwest Nannies',
                    'description' => "You've already signed this agreement.",
                    'status' => 'signed',
                    'signed_at' => '2026-02-10 14:24:13',
                ],
            ],
        ], 'Documents retrieved successfully.', 200);
    }

    // GET /client/documents/{document}
    public function show(Request $request, int $document): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'id' => $document,
            'title' => 'Client Agency Agreement Midwest Nannies',
            'content_html' => '<h1>Coast to Coast Nannies</h1><p>Agreement content...</p>',
            'status' => 'signed',
            'signed_at' => '2026-02-10 14:24:13',
            'signature' => 'Per Sarah Meadors',
            'audit_trail' => [
                'added_at' => '2026-02-10 14:24:13',
                'signed_at' => '2026-02-10 14:24:13',
                'ip' => '103.89.241.10',
            ],
        ], 'Document retrieved successfully.', 200);
    }

    // POST /client/documents/{document}/sign
    public function sign(Request $request, int $document): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $validated = $request->validate([
            'signature' => 'required|string|max:255',
            'agree' => 'required|boolean',
        ]);

        return $this->sendResponse([
            'id' => $document,
            'status' => 'signed',
            'signature' => $validated['signature'],
            'signed_at' => now()->toDateTimeString(),
        ], 'Document signed successfully.', 200);
    }

    // GET /client/documents/{document}/download
    public function download(Request $request, int $document): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'download_url' => url("/storage/documents/agreement-{$document}.pdf"),
            'expires_at' => now()->addMinutes(15)->toDateTimeString(),
        ], 'Download URL generated.', 200);
    }
}
