<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJobInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateInterviewController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/interviews
    // view=list | view=calendar
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $filter = $request->query('filter', 'all');
            $view = $request->query('view', 'list');

            $query = LongTermJobInterview::with(['job', 'job.client', 'application'])
                ->where('candidate_id', $candidate->id)
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

                return $this->sendResponse([
                    'view' => 'calendar',
                    'month' => $month,
                    'year' => $year,
                    'interviews' => $interviews->groupBy(fn ($i) => $i->scheduled_date->format('Y-m-d')),
                ], 'Interviews retrieved successfully.', 200);
            }

            $interviews = $query->paginate(20);

            $next = LongTermJobInterview::with(['job', 'job.client'])
                ->where('candidate_id', $candidate->id)
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
}
