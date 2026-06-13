<?php

namespace App\Traits;

use App\Models\Candidate;
use App\Models\Client;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Dispatches in-app (database) notifications to portal users.
 * Portal users are matched to their Client/Candidate record by email
 * within the agency, mirroring the ResolvesClient/ResolvesCandidate traits.
 */
trait SendsNotifications
{
    /**
     * @param  iterable<int, User|null>|Collection  $users
     * @param  array<string, mixed>  $meta
     */
    protected function notifyUsers(iterable $users, string $type, string $title, string $body, ?string $actionUrl = null, array $meta = []): void
    {
        $users = collect($users)->filter();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new DatabaseNotification($type, $title, $body, $actionUrl, $meta));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function notifyAgencyAdmins(int $agencyId, string $type, string $title, string $body, ?string $actionUrl = null, array $meta = []): void
    {
        $admins = User::where('agency_id', $agencyId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'agency_admin'))
            ->get();

        $this->notifyUsers($admins, $type, $title, $body, $actionUrl, $meta);
    }

    /**
     * Notify the portal user (client or candidate login) matching the given email.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function notifyPortalUser(int $agencyId, ?string $email, string $type, string $title, string $body, ?string $actionUrl = null, array $meta = []): void
    {
        if (! $email) {
            return;
        }

        $user = User::where('agency_id', $agencyId)->where('email', $email)->first();

        $this->notifyUsers([$user], $type, $title, $body, $actionUrl, $meta);
    }

    /**
     * Send a mail notification to a plain list of email addresses
     * (e.g. a document template's "Notification Emails").
     *
     * @param  array<int, string>|null  $emails
     */
    protected function notifyEmailRecipients(?array $emails, \Illuminate\Notifications\Notification $notification): void
    {
        $emails = collect($emails ?? [])->filter()->unique()->values();

        if ($emails->isEmpty()) {
            return;
        }

        Notification::route('mail', $emails->all())->notify($notification);
    }

    /**
     * Notify every client or candidate portal user of the agency.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function notifyPortalAudience(int $agencyId, string $userType, string $type, string $title, string $body, ?string $actionUrl = null, array $meta = []): void
    {
        $emails = $userType === 'client'
            ? Client::where('agency_id', $agencyId)->pluck('email')
            : Candidate::where('agency_id', $agencyId)->pluck('email');

        $emails = $emails->filter()->unique()->values();

        if ($emails->isEmpty()) {
            return;
        }

        $users = User::where('agency_id', $agencyId)->whereIn('email', $emails->all())->get();

        $this->notifyUsers($users, $type, $title, $body, $actionUrl, $meta);
    }
}
