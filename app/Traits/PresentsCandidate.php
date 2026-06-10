<?php

namespace App\Traits;

use App\Models\Candidate;
use App\Models\Location;
use App\Models\Type;

/**
 * Shared helpers for presenting a Candidate to a client audience
 * (role tags resolved from `type_id`, location label, full name).
 */
trait PresentsCandidate
{
    protected function candidateFullName(Candidate $candidate): string
    {
        return trim($candidate->first_name.' '.$candidate->last_name);
    }

    /**
     * Resolve the candidate's `type_id` array into display role names
     * (e.g. ["Nanny", "House Manager", "Baby/Night Nurse"]).
     *
     * @return array<int, string>
     */
    protected function candidateRoleNames(Candidate $candidate): array
    {
        $ids = collect($candidate->type_id ?? [])->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Type::whereIn('id', $ids)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve the candidate's location into a readable label, preferring the
     * configured `location_id` areas and falling back to the address fields.
     */
    protected function candidateLocationLabel(Candidate $candidate): ?string
    {
        $ids = collect($candidate->location_id ?? [])->filter()->values();

        if ($ids->isNotEmpty()) {
            $names = Location::whereIn('id', $ids)->pluck('location')->filter();

            if ($names->isNotEmpty()) {
                return $names->implode(', ');
            }
        }

        return collect([$candidate->city, $candidate->province, $candidate->country])
            ->filter()
            ->implode(', ') ?: null;
    }
}
