<?php

use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use Illuminate\Support\Facades\Broadcast;

/*
 * Private channel: private-job-messages.{longTermJobId}
 *
 * Authorized users:
 *  - Agency admin whose agency owns the job
 *  - Client whose client profile owns the job
 *  - Candidate assigned to the job
 */
Broadcast::channel('job-messages.{longTermJobId}', function ($user, int $longTermJobId) {
    $job = LongTermJob::find($longTermJobId);

    if (! $job) {
        return false;
    }

    // Agency admin
    if ($user->hasRole('agency_admin')) {
        return $job->agency_id === optional($user->agency)->id;
    }

    // Client
    if ($user->hasRole('client')) {
        $client = Client::where('email', $user->email)
            ->where('agency_id', $job->agency_id)
            ->first();

        return $client && $job->client_id === $client->id;
    }

    // Candidate
    if ($user->hasRole('candidate')) {
        $candidate = Candidate::where('email', $user->email)
            ->where('agency_id', $job->agency_id)
            ->first();

        return $candidate && $job->candidate_id === $candidate->id;
    }

    return false;
});
