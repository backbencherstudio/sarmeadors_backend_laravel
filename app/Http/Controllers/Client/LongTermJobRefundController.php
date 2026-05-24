<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobRefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LongTermJobRefundController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // POST /client/jobs/long-term/{longTermJob}/refund-request
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'cancelled') {
                return $this->sendError('Refund requests can only be made for cancelled jobs.', [], 422);
            }

            $existing = LongTermJobRefundRequest::where('long_term_job_id', $longTermJob->id)
                ->where('client_id', $client->id)
                ->first();

            if ($existing) {
                return $this->sendResponse($existing, 'A refund request already exists for this job.', 200);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:1000',
            ]);

            $refund = LongTermJobRefundRequest::create([
                'long_term_job_id' => $longTermJob->id,
                'client_id' => $client->id,
                'agency_id' => $longTermJob->agency_id,
                'reason' => $validated['reason'] ?? null,
            ]);

            return $this->sendResponse($refund, 'Refund request submitted.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/long-term/{longTermJob}/refund-request
    public function show(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $refund = LongTermJobRefundRequest::where('long_term_job_id', $longTermJob->id)
                ->where('client_id', $client->id)
                ->first();

            if (! $refund) {
                return $this->sendError('No refund request found.', [], 404);
            }

            return $this->sendResponse($refund, 'Refund request retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
