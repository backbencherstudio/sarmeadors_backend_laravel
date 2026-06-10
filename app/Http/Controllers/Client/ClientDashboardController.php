<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobMessage;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/dashboard
    public function index(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $longTermJobIds = LongTermJob::where('client_id', $client->id)->pluck('id');
            $shortTermJobIds = ShortTermJob::where('client_id', $client->id)->pluck('id');

            $totalJobPosts = $longTermJobIds->count() + $shortTermJobIds->count();

            $totalApplications = LongTermJobApplication::whereIn('long_term_job_id', $longTermJobIds)->count();

            $totalMessages = JobMessage::where(function ($q) use ($longTermJobIds, $shortTermJobIds) {
                $q->whereIn('long_term_job_id', $longTermJobIds)
                    ->orWhereIn('short_term_job_id', $shortTermJobIds);
            })->count();

            $totalInterviews = LongTermJobInterview::whereIn('long_term_job_id', $longTermJobIds)->count();

            $latestLongTermJob = LongTermJob::with(['candidate', 'applications'])
                ->withCount('applications')
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->latest()
                ->first();

            $latestShortTermJob = ShortTermJob::with(['candidate', 'dates'])
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->latest()
                ->first();

            $currentJob = $latestLongTermJob ?? $latestShortTermJob;

            $recommendedCandidates = Candidate::with('reviews')
                ->where('agency_id', $agencyId)
                ->inRandomOrder()
                ->limit(6)
                ->get()
                ->each(fn ($c) => $c->append(['image_url', 'average_rating', 'reviews_count']));

            return $this->sendResponse([
                'stats' => [
                    'total_job_posts' => $totalJobPosts,
                    'applications' => $totalApplications,
                    'messages' => $totalMessages,
                    'interviews' => $totalInterviews,
                ],
                'current_job' => $currentJob,
                'recommended_candidates' => $recommendedCandidates,
            ], 'Dashboard retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
