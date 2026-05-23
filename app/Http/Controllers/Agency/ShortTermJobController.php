<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'client_id' => 'required|integer|exists:clients,id',
                'status'    => 'nullable|string',
            ]);

            $agency   = $request->current_agency;
            $clientId = $request->query('client_id');
            $status   = $request->query('status');

            $query = ShortTermJob::with(['dates', 'children', 'location'])
                ->where('agency_id', $agency->id)
                ->where('client_id', $clientId);

            if ($status) {
                $query->where('status', $status);
            }

            $jobs = $query->latest()->get();

            $counts = ShortTermJob::where('agency_id', $agency->id)
                ->where('client_id', $clientId)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->sendResponse([
                'counts' => $counts,
                'jobs'   => $jobs,
            ], 'Jobs retrieved successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function show(ShortTermJob $shortTermJob, Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($shortTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            $shortTermJob->load(['client', 'dates', 'children', 'location']);

            return $this->sendResponse($shortTermJob, 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function approve(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($shortTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($shortTermJob->status !== 'pending_approval') {
                return $this->sendError('Only pending jobs can be approved.', [], 422);
            }

            $shortTermJob->update(['status' => 'marketplace']);

            return $this->sendResponse($shortTermJob, 'Job approved and moved to marketplace.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function reject(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($shortTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($shortTermJob->status !== 'pending_approval') {
                return $this->sendError('Only pending jobs can be rejected.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $shortTermJob->update([
                'status'          => 'rejected',
                'rejection_reason' => $validated['reason'] ?? null,
            ]);

            return $this->sendResponse($shortTermJob, 'Job rejected.', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
