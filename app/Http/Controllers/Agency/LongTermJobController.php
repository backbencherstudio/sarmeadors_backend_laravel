<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobController extends Controller
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

            $query = LongTermJob::with(['schedules', 'children', 'location'])
                ->where('agency_id', $agency->id)
                ->where('client_id', $clientId);

            if ($status) {
                $query->where('status', $status);
            }

            $jobs = $query->latest()->get();

            $counts = LongTermJob::where('agency_id', $agency->id)
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

    public function show(LongTermJob $longTermJob, Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            $longTermJob->load(['client', 'schedules', 'children', 'location']);

            return $this->sendResponse($longTermJob, 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function approve(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'pending_approval') {
                return $this->sendError('Only pending jobs can be approved.', [], 422);
            }

            $longTermJob->update(['status' => 'marketplace']);

            return $this->sendResponse($longTermJob, 'Job approved and moved to marketplace.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function reject(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'pending_approval') {
                return $this->sendError('Only pending jobs can be rejected.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $longTermJob->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['reason'] ?? null,
            ]);

            return $this->sendResponse($longTermJob, 'Job rejected.', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
