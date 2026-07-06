<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateJobRequest;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobApplication;
use App\Models\ShortTermJobReview;
use App\Traits\SendsNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobApplicationController extends Controller
{
    use SendsNotifications;

    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/short-term/{shortTermJob}/applicants
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $candidate = $shortTermJob->candidate_id
                ? Candidate::with('reviews')->find($shortTermJob->candidate_id)
                : null;

            if ($candidate) {
                $candidate->append(['image_url', 'average_rating', 'reviews_count']);
            }

            $applications = ShortTermJobApplication::with(['candidate.reviews'])
                ->where('short_term_job_id', $shortTermJob->id)
                ->latest()
                ->paginate(10);

            $applications->getCollection()->each(
                fn (ShortTermJobApplication $application) => $application->candidate
                    ?->append(['image_url', 'average_rating', 'reviews_count']),
            );

            return $this->sendResponse([
                'hired_candidate' => $candidate,
                'status' => $shortTermJob->status,
                'applications' => $applications,
            ], 'Applicants retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/short-term/{shortTermJob}/applicants/{applicationId}
    public function show(Request $request, ShortTermJob $shortTermJob, int $applicationId): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $candidate = Candidate::with('reviews')
                ->where('agency_id', $request->current_agency->id)
                ->find($applicationId);

            if (! $candidate) {
                return $this->sendError('Candidate not found.', [], 404);
            }

            $candidate->append(['image_url', 'average_rating', 'reviews_count']);

            return $this->sendResponse($candidate, 'Candidate retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/short-term/{shortTermJob}/applicants/{applicationId}/candidate-reviews
    public function candidateReviews(Request $request, ShortTermJob $shortTermJob, int $applicationId): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $reviews = ShortTermJobReview::with('client')
                ->where('candidate_id', $applicationId)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($reviews, 'Reviews retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/short-term/{shortTermJob}/applicants/{applicationId}/hire
    // Hiring an applicant assigns them and starts the job; only one candidate
    // can be hired per short-term job.
    public function hire(Request $request, ShortTermJob $shortTermJob, int $applicationId): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if (! in_array($shortTermJob->status, ['marketplace', 'pending_approval'])) {
                return $this->sendError('Cannot hire for a job in its current status.', [], 422);
            }

            $candidate = Candidate::where('agency_id', $request->current_agency->id)
                ->find($applicationId);

            if (! $candidate) {
                return $this->sendError('Candidate not found.', [], 404);
            }

            if ($shortTermJob->candidate_id && $shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('This job already has a hired candidate.', [], 422);
            }

            $hasApplied = ShortTermJobApplication::where('short_term_job_id', $shortTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->exists();

            // A candidate who never applied (hired directly from browsing) must
            // still confirm through the requested-jobs flow before work starts.
            if (! $hasApplied && $shortTermJob->candidate_id !== $candidate->id) {
                $shortTermJob->update(['candidate_id' => $candidate->id]);

                CandidateJobRequest::updateOrCreate(
                    [
                        'agency_id' => $request->current_agency->id,
                        'client_id' => $client->id,
                        'candidate_id' => $candidate->id,
                        'short_term_job_id' => $shortTermJob->id,
                        'job_type' => 'short_term',
                    ],
                    [
                        'status' => 'pending',
                        'message' => $request->input('message'),
                        'responded_at' => null,
                    ]
                );

                return $this->sendResponse([], 'Hire request forwarded to the candidate for confirmation.', 200);
            }

            // The candidate applied (or already accepted the job), so hiring
            // starts the job immediately.
            $shortTermJob->update([
                'candidate_id' => $candidate->id,
                'status' => 'running',
            ]);

            ShortTermJobApplication::where('short_term_job_id', $shortTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->update(['status' => 'hired']);

            ShortTermJobApplication::where('short_term_job_id', $shortTermJob->id)
                ->where('candidate_id', '!=', $candidate->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            $meta = [
                'short_term_job_id' => $shortTermJob->id,
                'candidate_id' => $candidate->id,
            ];
            $body = trim($candidate->first_name.' '.$candidate->last_name)." has been hired for \"{$shortTermJob->title}\".";

            $this->notifyPortalUser(
                $request->current_agency->id,
                $candidate->email,
                'short_term_job_hired',
                'You Have Been Hired',
                "You have been hired for \"{$shortTermJob->title}\". The job is now running.",
                null,
                $meta,
            );
            $this->notifyAgencyAdmins($request->current_agency->id, 'short_term_job_hired', 'Candidate Hired', $body, null, $meta);

            return $this->sendResponse([
                'job_id' => $shortTermJob->id,
                'status' => 'running',
                'hired_candidate' => [
                    'id' => $candidate->id,
                    'name' => trim($candidate->first_name.' '.$candidate->last_name),
                    'image_url' => $candidate->image_url,
                ],
            ], 'Candidate hired. The job is now running.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
