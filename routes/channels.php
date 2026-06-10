<?php

use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
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

    if ($user->hasRole('agency_admin') || $user->hasRole('admin_staff')) {
        return $job->agency_id === optional($user->agency)->id;
    }

    $client = Client::where('email', $user->email)
        ->where('agency_id', $job->agency_id)
        ->first();

    if ($client && $job->client_id === $client->id) {
        return true;
    }

    $candidate = Candidate::where('email', $user->email)
        ->where('agency_id', $job->agency_id)
        ->first();

    if ($candidate && $job->candidate_id === $candidate->id) {
        return true;
    }

    return false;
});

Broadcast::channel('short-term-job-messages.{shortTermJobId}', function ($user, int $shortTermJobId) {
    $job = ShortTermJob::find($shortTermJobId);

    if (! $job) {
        return false;
    }

    if ($user->hasRole('agency_admin') || $user->hasRole('admin_staff')) {
        return $job->agency_id === optional($user->agency)->id;
    }

    $client = Client::where('email', $user->email)
        ->where('agency_id', $job->agency_id)
        ->first();

    if ($client && $job->client_id === $client->id) {
        return true;
    }

    $candidate = Candidate::where('email', $user->email)
        ->where('agency_id', $job->agency_id)
        ->first();

    if ($candidate && $job->candidate_id === $candidate->id) {
        return true;
    }

    return false;
});
