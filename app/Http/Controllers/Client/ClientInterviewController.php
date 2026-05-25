<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientInterviewController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/interviews
    // view=list | view=calendar
    public function index(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $jobIds = LongTermJob::where('client_id', $client->id)->pluck('id');

            $filter = $request->query('filter', 'all');
            $view = $request->query('view', 'list');

            $query = LongTermJobInterview::with(['job', 'candidate', 'application'])
                ->whereIn('long_term_job_id', $jobIds)
                ->orderBy('scheduled_date')
                ->orderBy('available_from');

            if ($filter === 'upcoming') {
                $query->where('scheduled_date', '>=', now()->toDateString())
                    ->where('status', 'scheduled');
            } elseif ($filter === 'previous') {
                $query->where(function ($q) {
                    $q->where('scheduled_date', '<', now()->toDateString())
                        ->orWhere('status', 'completed');
                });
            } elseif ($filter === 'cancelled') {
                $query->where('status', 'cancelled');
            }

            if ($view === 'calendar') {
                $month = $request->query('month', now()->month);
                $year = $request->query('year', now()->year);

                $interviews = $query->whereMonth('scheduled_date', $month)
                    ->whereYear('scheduled_date', $year)
                    ->get();

                $grouped = $interviews->groupBy(fn ($i) => $i->scheduled_date->format('Y-m-d'));

                return $this->sendResponse([
                    'view' => 'calendar',
                    'month' => $month,
                    'year' => $year,
                    'interviews' => $grouped,
                ], 'Interviews retrieved successfully.', 200);
            }

            $interviews = $query->paginate(20);

            $next = LongTermJobInterview::with(['job', 'candidate'])
                ->whereIn('long_term_job_id', $jobIds)
                ->where('scheduled_date', '>=', now()->toDateString())
                ->where('status', 'scheduled')
                ->orderBy('scheduled_date')
                ->orderBy('available_from')
                ->first();

            return $this->sendResponse([
                'view' => 'list',
                'next_interview' => $next,
                'interviews' => $interviews,
            ], 'Interviews retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/interviews/{interview}
    public function show(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $job = LongTermJob::find($interview->long_term_job_id);

            if (! $job || $job->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            return $this->sendResponse(
                $interview->load(['job', 'candidate', 'application']),
                'Interview retrieved successfully.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /client/interviews/{interview}/reschedule
    public function reschedule(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $job = LongTermJob::find($interview->long_term_job_id);

            if (! $job || $job->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($interview->status !== 'scheduled') {
                return $this->sendError('Only scheduled interviews can be rescheduled.', [], 422);
            }

            $validated = $request->validate([
                'scheduled_date' => 'required|date|after_or_equal:today',
                'available_from' => 'required|date_format:H:i',
                'available_to' => 'required|date_format:H:i|after:available_from',
                'reason' => 'nullable|string|max:1000',
            ]);

            $interview->update([
                'scheduled_date' => $validated['scheduled_date'],
                'available_from' => $validated['available_from'],
                'available_to' => $validated['available_to'],
                'special_note' => $validated['reason'] ?? $interview->special_note,
            ]);

            return $this->sendResponse(
                $interview->fresh()->load(['job', 'candidate']),
                'Interview rescheduled successfully.',
                200
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /client/interviews/{interview}
    public function cancel(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $job = LongTermJob::find($interview->long_term_job_id);

            if (! $job || $job->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($interview->status !== 'scheduled') {
                return $this->sendError('Only scheduled interviews can be cancelled.', [], 422);
            }

            $interview->update(['status' => 'cancelled']);

            return $this->sendResponse([], 'Interview cancelled successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
