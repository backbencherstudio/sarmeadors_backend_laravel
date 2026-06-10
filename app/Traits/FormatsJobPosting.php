<?php

namespace App\Traits;

use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Support\Collection;

/**
 * Shared presentation helpers for candidate-facing job postings
 * (requested jobs and short/long-term marketplace applications).
 */
trait FormatsJobPosting
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
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
            'date_of_birth' => $child->date_of_birth,
            'gender' => $child->gender,
            'interests' => $child->interests,
            'allergies' => $child->allergies,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function formatServices(ShortTermJob|LongTermJob $job): array
    {
        return collect(['Nanny'])
            ->when($job->has_housekeeper ?? false, fn (Collection $services) => $services->push('House Manager'))
            ->when($job->children?->isNotEmpty(), fn (Collection $services) => $services->push('Baby/Night Nurse'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatLocation(ShortTermJob|LongTermJob $job): array
    {
        return [
            'label' => collect([$job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
            'city' => $job->home_city,
            'province' => $job->home_province,
            'country' => $job->country,
        ];
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
        ];
    }

    protected function formatDayName(int $day): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? 'Unknown';
    }
}
