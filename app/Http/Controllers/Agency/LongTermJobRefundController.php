<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Models\LongTermJobRefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LongTermJobRefundController extends Controller
{
    private function resolveJob(Request $request, LongTermJob $job): bool
    {
        return $job->agency_id === $request->current_agency->id;
    }

    // GET /agency/jobs/long-term/{longTermJob}/refund-request
    public function show(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $refund = LongTermJobRefundRequest::with('client')
                ->where('long_term_job_id', $longTermJob->id)
                ->first();

            if (! $refund) {
                return $this->sendError('No refund request found.', [], 404);
            }

            return $this->sendResponse($refund, 'Refund request retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/long-term/{longTermJob}/refund-request/approve
    public function approve(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $refund = LongTermJobRefundRequest::where('long_term_job_id', $longTermJob->id)
                ->where('status', 'pending')
                ->first();

            if (! $refund) {
                return $this->sendError('No pending refund request found.', [], 404);
            }

            $validated = $request->validate([
                'agency_note' => 'nullable|string|max:1000',
            ]);

            $refund->update([
                'status' => 'approved',
                'agency_note' => $validated['agency_note'] ?? null,
                'resolved_at' => now(),
            ]);

            return $this->sendResponse($refund, 'Refund request approved.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/long-term/{longTermJob}/refund-request/reject
    public function reject(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $refund = LongTermJobRefundRequest::where('long_term_job_id', $longTermJob->id)
                ->where('status', 'pending')
                ->first();

            if (! $refund) {
                return $this->sendError('No pending refund request found.', [], 404);
            }

            $validated = $request->validate([
                'agency_note' => 'nullable|string|max:1000',
            ]);

            $refund->update([
                'status' => 'rejected',
                'agency_note' => $validated['agency_note'] ?? null,
                'resolved_at' => now(),
            ]);

            return $this->sendResponse($refund, 'Refund request rejected.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
