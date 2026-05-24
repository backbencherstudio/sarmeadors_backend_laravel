<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShortTermJobRefundController extends Controller
{
    private function resolveJob(Request $request, ShortTermJob $job): bool
    {
        return $job->agency_id === $request->current_agency->id;
    }

    // GET /agency/jobs/short-term/{shortTermJob}/refund-request
    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $shortTermJob)) {
                return $this->sendError('Not found.', [], 404);
            }

            $payment = Payment::where('short_term_job_id', $shortTermJob->id)
                ->latest()
                ->first();

            if (! $payment) {
                return $this->sendResponse(['refund' => null], 'No payment found.', 200);
            }

            $metadata = $payment->metadata ?? [];
            $requested = $metadata['refund_requested'] ?? false;

            return $this->sendResponse([
                'payment' => $payment,
                'refund_requested' => $requested,
                'refund_reason' => $metadata['refund_reason'] ?? null,
                'refund_requested_at' => $metadata['refund_requested_at'] ?? null,
                'refund_status' => $metadata['refund_status'] ?? null,
                'is_refunded' => $payment->status === 'refunded',
            ], 'Refund info retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/short-term/{shortTermJob}/refund-request/approve
    public function approve(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $shortTermJob)) {
                return $this->sendError('Not found.', [], 404);
            }

            $payment = Payment::where('short_term_job_id', $shortTermJob->id)
                ->where('status', 'succeeded')
                ->latest()
                ->first();

            if (! $payment) {
                return $this->sendError('No eligible payment found.', [], 404);
            }

            $metadata = $payment->metadata ?? [];

            if (empty($metadata['refund_requested'])) {
                return $this->sendError('No refund request has been submitted for this job.', [], 422);
            }

            if (($metadata['refund_status'] ?? null) === 'approved') {
                return $this->sendError('Refund has already been approved.', [], 422);
            }

            $validated = $request->validate([
                'agency_note' => 'nullable|string|max:1000',
            ]);

            $payment->update([
                'status' => 'refunded',
                'metadata' => array_merge($metadata, [
                    'refund_status' => 'approved',
                    'agency_note' => $validated['agency_note'] ?? null,
                    'refund_resolved_at' => now()->toISOString(),
                ]),
            ]);

            return $this->sendResponse($payment->fresh(), 'Refund approved.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/short-term/{shortTermJob}/refund-request/reject
    public function reject(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $shortTermJob)) {
                return $this->sendError('Not found.', [], 404);
            }

            $payment = Payment::where('short_term_job_id', $shortTermJob->id)
                ->where('status', 'succeeded')
                ->latest()
                ->first();

            if (! $payment) {
                return $this->sendError('No eligible payment found.', [], 404);
            }

            $metadata = $payment->metadata ?? [];

            if (empty($metadata['refund_requested'])) {
                return $this->sendError('No refund request has been submitted for this job.', [], 422);
            }

            if (! is_null($metadata['refund_status'] ?? null)) {
                return $this->sendError('Refund request has already been resolved.', [], 422);
            }

            $validated = $request->validate([
                'agency_note' => 'nullable|string|max:1000',
            ]);

            $payment->update([
                'metadata' => array_merge($metadata, [
                    'refund_status' => 'rejected',
                    'agency_note' => $validated['agency_note'] ?? null,
                    'refund_resolved_at' => now()->toISOString(),
                ]),
            ]);

            return $this->sendResponse($payment->fresh(), 'Refund rejected.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
