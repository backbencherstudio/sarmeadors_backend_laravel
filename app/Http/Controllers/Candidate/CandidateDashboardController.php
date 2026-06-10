<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use App\Traits\ResolvesCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CandidateDashboardController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/dashboard
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $agencyId = $request->current_agency->id;

        // Status statistics
        $shortTermJobCount = ShortTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->count();

        $longTermJobCount = LongTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->count();

        $runningJobCount = ShortTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->where('status', 'running')
            ->count()
            + LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->where('status', 'running')
                ->count();

        // Unique families (clients) the candidate has worked with
        $shortTermClientIds = ShortTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->pluck('client_id');

        $longTermClientIds = LongTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->pluck('client_id');

        $familyCount = $shortTermClientIds->merge($longTermClientIds)->unique()->count();

        // Running short-term job
        $runningShortTermJobs = ShortTermJob::with(['client', 'dates', 'latestAttendance'])
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->where('status', 'running')
            ->latest()
            ->limit(4)
            ->get();

        // Running long-term job
        $runningLongTermJobs = LongTermJob::with(['client', 'schedules', 'latestAttendance'])
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->where('status', 'running')
            ->latest()
            ->limit(4)
            ->get();

        // Available marketplace jobs (short-term)
        $availableShortTermJobs = ShortTermJob::with(['dates', 'location', 'client', 'latestAttendance'])
            ->where('agency_id', $agencyId)
            ->where('status', 'marketplace')
            ->whereNull('candidate_id')
            ->latest()
            ->limit(4)
            ->get();

        // Available marketplace jobs (long-term)
        $availableLongTermJobs = LongTermJob::with(['schedules', 'location', 'client', 'latestAttendance'])
            ->where('agency_id', $agencyId)
            ->where('status', 'marketplace')
            ->whereDoesntHave('applications', fn ($q) => $q->where('candidate_id', $candidate->id))
            ->latest()
            ->limit(4)
            ->get();

        $runningJobs = $this->formatDashboardJobs(
            $runningShortTermJobs
                ->concat($runningLongTermJobs)
                ->sortByDesc('created_at')
                ->take(4)
                ->values()
        );

        $availableJobs = $this->formatDashboardJobs(
            $availableShortTermJobs
                ->concat($availableLongTermJobs)
                ->sortByDesc('created_at')
                ->take(6)
                ->values()
        );

        return $this->sendResponse([
            'candidate' => [
                'id' => $candidate->id,
                'name' => $this->formatCandidateName($candidate),
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'image_url' => $candidate->image_url,
            ],
            'stats' => [
                'short_term_jobs' => $shortTermJobCount,
                'long_term_jobs' => $longTermJobCount,
                'my_jobs' => $runningJobCount,
                'my_families' => $familyCount,
            ],
            'running_jobs' => $runningJobs,
            'available_jobs' => $availableJobs,
        ], 'Dashboard retrieved successfully.', 200);
    }

    private function formatDashboardJobs(Collection $jobs): Collection
    {
        return $jobs->map(function (ShortTermJob|LongTermJob $job): array {
            $isShortTerm = $job instanceof ShortTermJob;
            $jobType = $isShortTerm ? 'short_term' : 'long_term';
            $attendance = $job->latestAttendance;

            return [
                'id' => $job->id,
                'job_type' => $jobType,
                'title' => $job->title,
                'client_name' => $this->formatClientName($job->client),
                'description' => $job->description,
                'cover_image_url' => $job->cover_image_url,
                'address' => [
                    'line' => $job->job_address,
                    'city' => $job->home_city,
                    'province' => $job->home_province,
                    'postal_code' => $job->home_postal_code,
                    'country' => $job->country,
                ],
                'compensation' => [
                    'amount' => $job->compensation_amount,
                    'currency' => $job->compensation_currency,
                    'type' => $job->compensation_type,
                ],
                'status' => $job->status,
                'latest_attendance' => $attendance,
                'can_check_in' => $job->status === 'running' && (! $attendance || ! $attendance->check_in),
                'can_check_out' => $job->status === 'running' && $attendance?->check_in && ! $attendance?->check_out,
            ];
        });
    }

    private function formatClientName(?Client $client): ?string
    {
        if (! $client) {
            return null;
        }

        return trim($client->first_name.' '.$client->last_name);
    }

    private function formatCandidateName(Candidate $candidate): string
    {
        return trim($candidate->first_name.' '.$candidate->last_name);
    }
}
