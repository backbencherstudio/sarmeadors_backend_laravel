<?php

namespace App\Traits;

use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Support\Str;

/**
 * Shared presentation helpers for a candidate's assigned short/long-term jobs.
 * Builds on the shared job-card helpers so assigned-job lists render the same
 * card as every other job feed.
 */
trait FormatsAssignedJob
{
    use FormatsJobPosting;

    /**
     * Shared job card plus the candidate's assignment context (client name
     * and latest attendance state), matching the dashboard job feeds.
     *
     * @return array<string, mixed>
     */
    protected function formatAssignedJobCard(ShortTermJob|LongTermJob $job): array
    {
        $attendance = $job->latestAttendance;

        return array_merge($this->formatJobCard($job), [
            'client_name' => $this->formatClientName($job),
            'latest_attendance' => $attendance,
            'can_check_in' => $job->status === 'running' && (! $attendance || ! $attendance->check_in),
            'can_check_out' => $job->status === 'running' && $attendance?->check_in && ! $attendance?->check_out,
        ]);
    }

    protected function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return "{$remainingMinutes} min";
        }

        return trim($hours.' hr '.$remainingMinutes.' min');
    }

    protected function formatClientName(ShortTermJob|LongTermJob $job): ?string
    {
        return $job->client ? trim($job->client->first_name.' '.$job->client->last_name) : null;
    }

    protected function formatCompensation(ShortTermJob|LongTermJob $job): string
    {
        return '$'.$this->formatAmount($job->compensation_amount).' per '.Str::after($job->compensation_type, 'per_');
    }
}
