<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\FormSubmission;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientCandidateController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/candidates
    // tab=new (applied to client's active jobs) | tab=previous (hired + completed)
    public function index(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;
            $tab = $request->query('tab', 'new');

            if ($tab === 'previous') {
                $candidateIds = LongTermJob::where('client_id', $client->id)
                    ->where('status', 'completed')
                    ->whereNotNull('candidate_id')
                    ->pluck('candidate_id')
                    ->unique();

                $candidates = Candidate::with('reviews')
                    ->where('agency_id', $agencyId)
                    ->whereIn('id', $candidateIds)
                    ->paginate(12);
            } else {
                $jobIds = LongTermJob::where('client_id', $client->id)->pluck('id');

                $candidateIds = LongTermJobApplication::whereIn('long_term_job_id', $jobIds)
                    ->pluck('candidate_id')
                    ->unique();

                $candidates = Candidate::with('reviews')
                    ->where('agency_id', $agencyId)
                    ->whereIn('id', $candidateIds)
                    ->paginate(12);
            }

            $candidates->each(fn ($c) => $c->append(['image_url', 'average_rating', 'reviews_count']));

            return $this->sendResponse($candidates, 'Candidates retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/candidates/discover
    // All agency candidates for browsing (marketplace discovery)
    public function discover(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $query = Candidate::with('reviews')
                ->where('agency_id', $agencyId);

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $candidates = $query->paginate(12);
            $candidates->each(fn ($c) => $c->append(['image_url', 'average_rating', 'reviews_count']));

            return $this->sendResponse($candidates, 'Candidates retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/candidates/{candidate}
    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            if ($candidate->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $candidate->append(['image_url', 'average_rating', 'reviews_count']);

            $reviews = LongTermJobReview::with('client')
                ->where('candidate_id', $candidate->id)
                ->latest()
                ->get();

            $myReview = $reviews->firstWhere('client_id', $client->id);

            $formSubmissions = FormSubmission::with('form', 'fieldValues.field')
                ->where('entity_id', $candidate->id)
                ->get();

            $application = LongTermJobApplication::whereHas('job', fn ($q) => $q->where('client_id', $client->id))
                ->where('candidate_id', $candidate->id)
                ->with(['job', 'interview'])
                ->latest()
                ->first();

            return $this->sendResponse([
                'candidate' => $candidate,
                'reviews' => [
                    'my_review' => $myReview,
                    'all' => $reviews,
                ],
                'form_submissions' => $formSubmissions,
                'application' => $application,
            ], 'Candidate retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
