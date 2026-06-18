<?php

namespace App\Traits;

use App\Models\Client;
use App\Models\ClientCandidate;

/**
 * Upserts the link between a client and a candidate that powers the client's
 * "My Candidates" list (status, notes, and the date they were first linked).
 */
trait LinksClientCandidate
{
    protected function linkClientCandidate(Client $client, int $candidateId, ?string $status = null, ?string $notes = null): ClientCandidate
    {
        $link = ClientCandidate::firstOrNew([
            'client_id' => $client->id,
            'candidate_id' => $candidateId,
        ]);

        $link->agency_id = $client->agency_id;

        if ($status !== null) {
            $link->status = $status;
        } elseif (! $link->exists) {
            $link->status = 'interested';
        }

        if ($notes !== null) {
            $link->notes = $notes;
        }

        if (! $link->exists || $link->linked_at === null) {
            $link->linked_at = now();
        }

        $link->save();

        return $link;
    }
}
