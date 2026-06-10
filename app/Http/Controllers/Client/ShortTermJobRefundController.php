<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShortTermJobRefundController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/short-term/{shortTermJob}/refund-request
    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $payment = Payment::where('short_term_job_id', $shortTermJob->id)
                ->where('client_id', $client->id)
                ->latest()
                ->first();

            if (! $payment) {
                return $this->sendResponse(['refund' => null], 'No payment found for this job.', 200);
            }

            return $this->sendResponse([
                'payment' => $payment,
                'is_refunded' => $payment->status === 'refunded',
                'refund_eligible' => in_array($shortTermJob->status, ['cancelled']) && $payment->status === 'succeeded',
            ], 'Refund info retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/short-term/{shortTermJob}/refund-request
    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($shortTermJob->status !== 'cancelled') {
                return $this->sendError('Refund requests can only be submitted for cancelled jobs.', [], 422);
            }

            $payment = Payment::where('short_term_job_id', $shortTermJob->id)
                ->where('client_id', $client->id)
                ->where('status', 'succeeded')
                ->latest()
                ->first();

            if (! $payment) {
                return $this->sendError('No eligible payment found for refund.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'required|string|max:1000',
            ]);

            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'refund_requested' => true,
                    'refund_reason' => $validated['reason'],
                    'refund_requested_at' => now()->toISOString(),
                ]),
            ]);

            return $this->sendResponse([], 'Refund request submitted. Admin will review it shortly.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
