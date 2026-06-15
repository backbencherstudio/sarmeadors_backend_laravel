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
use App\Traits\FormatsJobPosting;
use App\Traits\FormatsMoney;
use App\Traits\ResolvesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientDashboardController extends Controller
{
    use FormatsJobPosting;
    use FormatsMoney;
    use ResolvesClient;

    // GET /client/dashboard
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $agencyId = $request->current_agency->id;

        $longTermJobIds = LongTermJob::where('client_id', $client->id)->pluck('id');
        $shortTermJobIds = ShortTermJob::where('client_id', $client->id)->pluck('id');

        $stats = [
            'total_job_posts' => $longTermJobIds->count() + $shortTermJobIds->count(),
            'applications' => LongTermJobApplication::whereIn('long_term_job_id', $longTermJobIds)->count(),
            'messages' => JobMessage::where(function ($query) use ($longTermJobIds, $shortTermJobIds) {
                $query->whereIn('long_term_job_id', $longTermJobIds)
                    ->orWhereIn('short_term_job_id', $shortTermJobIds);
            })->count(),
            'interviews' => LongTermJobInterview::whereIn('long_term_job_id', $longTermJobIds)->count(),
        ];

        $longTermJobs = LongTermJob::with(['candidate', 'children', 'applications.candidate'])
            ->withCount('applications')
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get();

        $shortTermJobs = ShortTermJob::with(['candidate', 'children', 'dates'])
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get();

        $currentJobs = $longTermJobs->concat($shortTermJobs)
            ->sortByDesc('created_at')
            ->values();

        $recommendedCandidates = Candidate::with('reviews')
            ->where('agency_id', $agencyId)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        return $this->sendResponse([
            'client' => [
                'id' => $client->id,
                'name' => $this->formatClientName($client),
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'image_url' => $client->image_url,
            ],
            'stats' => $stats,
            'current_jobs' => $currentJobs
                ->map(fn (ShortTermJob|LongTermJob $job): array => $this->formatCurrentJob($job))
                ->values(),
            'recommended_candidates' => $recommendedCandidates
                ->map(fn (Candidate $candidate): array => $this->formatRecommendedCandidate($candidate))
                ->values(),
        ], 'Dashboard retrieved successfully.', 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCurrentJob(ShortTermJob|LongTermJob $job): array
    {
        $isLongTerm = $job instanceof LongTermJob;

        return [
            'id' => $job->id,
            'job_type' => $isLongTerm ? 'long_term' : 'short_term',
            'job_type_label' => $isLongTerm ? 'Long-Term Job' : 'Short-Term Job',
            'title' => $job->title,
            'cover_image_url' => $job->cover_image_url,
            'description' => $job->description,
            'description_preview' => $job->description ? Str::limit($job->description, 140) : null,
            'services' => $this->formatServices($job),
            'location' => $this->formatLocation($job),
            'compensation' => [
                'amount' => $job->compensation_amount,
                'currency' => $job->compensation_currency,
                'type' => $job->compensation_type,
                'label' => $this->formatHourlyRate($job->compensation_amount),
            ],
            'status' => $job->status,
            'status_label' => $this->formatJobStatusLabel($job->status),
            'applicants' => $this->formatApplicants($job),
            'assigned_candidate' => $this->formatAssignedCandidate($job),
            'actions' => [
                'can_view_details' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatApplicants(ShortTermJob|LongTermJob $job): array
    {
        if (! $job instanceof LongTermJob) {
            return ['count' => 0, 'avatars' => []];
        }

        $avatars = $job->applications
            ->map(fn (LongTermJobApplication $application): ?string => $application->candidate?->image_url)
            ->filter()
            ->take(5)
            ->values();

        return [
            'count' => $job->applications_count ?? $job->applications->count(),
            'avatars' => $avatars,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatAssignedCandidate(ShortTermJob|LongTermJob $job): ?array
    {
        $candidate = $job->candidate;

        if (! $candidate) {
            return null;
        }

        return [
            'id' => $candidate->id,
            'name' => trim($candidate->first_name.' '.$candidate->last_name),
            'image_url' => $candidate->image_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecommendedCandidate(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => trim($candidate->first_name.' '.$candidate->last_name),
            'image_url' => $candidate->image_url,
            'location' => $this->formatCandidateLocation($candidate),
            'available_for' => $candidate->available_for ?? [],
            'experience_years' => $candidate->years_of_experience,
            'rating' => [
                'average' => $candidate->average_rating,
                'count' => $candidate->reviews_count,
            ],
            'actions' => [
                'can_view_profile' => true,
            ],
        ];
    }

    private function formatCandidateLocation(Candidate $candidate): ?string
    {
        return collect([$candidate->city, $candidate->province, $candidate->country])
            ->filter()
            ->implode(', ') ?: null;
    }

    private function formatClientName(Client $client): string
    {
        return trim($client->first_name.' '.$client->last_name);
    }

    private function formatJobStatusLabel(?string $status): string
    {
        return match ($status) {
            'running' => 'Running',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'marketplace', 'pending', 'pending_approval' => 'Pending',
            default => $status ? Str::headline($status) : 'Pending',
        };
    }
}
