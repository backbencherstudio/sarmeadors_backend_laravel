<?php

namespace App\Traits;

use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared presentation helpers for job postings across the client and candidate
 * dashboards (My Job / Available Jobs cards, requested jobs and short/long-term
 * marketplace applications).
 */
trait FormatsJobPosting
{
    use FormatsMoney;

    /**
     * Shared "job card" shape rendered by both the client dashboard (My Job)
     * and the candidate dashboard (Available Jobs) so the two feeds stay in
     * sync. Callers layer their own context-specific fields on top.
     *
     * @return array<string, mixed>
     */
    protected function formatJobCard(ShortTermJob|LongTermJob $job): array
    {
        $isLongTerm = $job instanceof LongTermJob;

        return [
            'id' => $job->id,
            'job_type' => $isLongTerm ? 'long_term' : 'short_term',
            'job_type_label' => $isLongTerm ? 'Long-Term Job' : 'Short-Term Job',
            'term_label' => $isLongTerm ? 'Long-term' : 'Short-term',
            'title' => $job->title,
            'cover_image_url' => $job->cover_image_url,
            'description' => $job->description,
            'description_preview' => $job->description ? Str::limit($job->description, 140) : null,
            'services' => $this->formatServices($job),
            'location' => $this->formatLocation($job),
            'address' => $this->formatAddress($job),
            'compensation' => [
                'amount' => $job->compensation_amount,
                'currency' => $job->compensation_currency,
                'type' => $job->compensation_type,
                'label' => $this->formatHourlyRate($job->compensation_amount),
            ],
            'status' => $job->status,
            'status_label' => $this->formatJobStatusLabel($job->status),
            'actions' => [
                'can_view_details' => true,
            ],
        ];
    }

    protected function formatJobStatusLabel(?string $status): string
    {
        return match ($status) {
            'running' => 'Running',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'marketplace', 'pending', 'pending_approval' => 'Pending',
            default => $status ? Str::headline($status) : 'Pending',
        };
    }

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
            'label' => collect([$job->job_address, $job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function formatAssignedCandidate(ShortTermJob|LongTermJob $job): ?array
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

    protected function formatDayName(int $day): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? 'Unknown';
    }
}
