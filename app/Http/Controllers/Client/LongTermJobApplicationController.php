<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use App\Models\LongTermJobReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LongTermJobApplicationController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/long-term/{longTermJob}/applicants
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $applications = LongTermJobApplication::with(['candidate.reviews'])
                ->where('long_term_job_id', $longTermJob->id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($applications, 'Applicants retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/long-term/{longTermJob}/applicants/{application}
    public function show(Request $request, LongTermJob $longTermJob, LongTermJobApplication $application): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id || $application->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            $application->load(['candidate.reviews', 'interview']);

            return $this->sendResponse($application, 'Applicant retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/long-term/{longTermJob}/applicants/{application}/candidate-reviews
    public function candidateReviews(Request $request, LongTermJob $longTermJob, LongTermJobApplication $application): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id || $application->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            $reviews = LongTermJobReview::with(['job', 'client'])
                ->where('candidate_id', $application->candidate_id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($reviews, 'Candidate reviews retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/long-term/{longTermJob}/applicants/{application}/interview
    public function interview(Request $request, LongTermJob $longTermJob, LongTermJobApplication $application): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id || $application->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            $validated = $request->validate([
                'scheduled_date' => 'required|date|after_or_equal:today',
                'available_from' => 'required|date_format:H:i',
                'available_to' => 'required|date_format:H:i|after:available_from',
                'special_note' => 'nullable|string|max:1000',
                'interview_type' => 'nullable|in:in_person,zoom,google_meet',
                'interview_link' => 'nullable|string|max:500',
                'description' => 'nullable|string|max:2000',
            ]);

            $interview = LongTermJobInterview::updateOrCreate(
                ['long_term_job_application_id' => $application->id],
                [
                    'long_term_job_id' => $longTermJob->id,
                    'candidate_id' => $application->candidate_id,
                    'agency_id' => $longTermJob->agency_id,
                    'scheduled_date' => $validated['scheduled_date'],
                    'available_from' => $validated['available_from'],
                    'available_to' => $validated['available_to'],
                    'special_note' => $validated['special_note'] ?? null,
                    'interview_type' => $validated['interview_type'] ?? 'in_person',
                    'interview_link' => $validated['interview_link'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'status' => 'scheduled',
                ]
            );

            $application->update(['status' => 'interviewed']);

            return $this->sendResponse($interview, 'Interview scheduled successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/long-term/{longTermJob}/applicants/{application}/hire
    public function hire(Request $request, LongTermJob $longTermJob, LongTermJobApplication $application): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id || $application->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            if (! in_array($longTermJob->status, ['marketplace'])) {
                return $this->sendError('Candidates can only be hired from marketplace jobs.', [], 422);
            }

            // Hiring notifies agency — agency then assigns nanny and starts the job
            $application->update(['status' => 'hired']);

            // Reject all other pending applications
            LongTermJobApplication::where('long_term_job_id', $longTermJob->id)
                ->where('id', '!=', $application->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            return $this->sendResponse([], 'Hire request forwarded to admin for confirmation.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
