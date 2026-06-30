<?php

namespace App\Traits;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Resolves the Candidate record tied to the authenticated user within the
 * agency resolved by the subdomain middleware (`$request->current_agency`).
 * Secondary logins (see App\Models\CandidateSecondaryLogin) authenticate as
 * this same primary user (see AuthController::login), so a plain email
 * match is always correct here.
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
