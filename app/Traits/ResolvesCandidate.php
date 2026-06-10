<?php

namespace App\Traits;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Resolves the Candidate record tied to the authenticated user within the
 * agency resolved by the subdomain middleware (`$request->current_agency`).
 */
trait ResolvesCandidate
{
    protected function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    /**
     * Resolve the current candidate or abort with a 404 handled globally.
     *
     * @throws ModelNotFoundException
     */
    protected function currentCandidateOrFail(Request $request): Candidate
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            throw new ModelNotFoundException('Candidate profile not found.');
        }

        return $candidate;
    }
}
