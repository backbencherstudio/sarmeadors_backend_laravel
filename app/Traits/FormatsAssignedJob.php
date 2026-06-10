<?php

namespace App\Traits;

use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared presentation helpers for a candidate's assigned short/long-term jobs.
 */
trait FormatsAssignedJob
{
    /**
     * @param  Collection<int, mixed>  $children
     * @return Collection<int, array<string, mixed>>
     */
    protected function formatChildren(Collection $children): Collection
    {
        return $children->map(fn ($child): array => [
            'id' => $child->id,
            'name' => trim($child->first_name.' '.$child->last_name),
            'date_of_birth' => $child->date_of_birth,
            'gender' => $child->gender,
            'interests' => $child->interests,
            'allergies' => $child->allergies,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAddress(ShortTermJob|LongTermJob $job): array
    {
        return [
            'street_address' => $job->job_address,
            'city' => $job->home_city,
            'province' => $job->home_province,
            'postal_code' => $job->home_postal_code,
            'country' => $job->country,
            'label' => collect([$job->job_address, $job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
        ];
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
        return '$'.rtrim(rtrim((string) $job->compensation_amount, '0'), '.').' per '.Str::after($job->compensation_type, 'per_');
    }
}
