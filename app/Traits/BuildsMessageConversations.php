<?php

namespace App\Traits;

use App\Models\JobMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds dashboard inbox conversation rows (one per job thread) with the
 * last message preview and unread count, as shown in the messaging panel.
 */
trait BuildsMessageConversations
{
    /**
     * @param  Collection<int, Model>  $jobs
     * @param  callable(Model): array{name: ?string, image_url: ?string}  $counterpart
     * @return Collection<int, array<string, mixed>>
     */
    protected function conversationsForJobs(Request $request, Collection $jobs, string $jobType, string $thread, callable $counterpart): Collection
    {
        if ($jobs->isEmpty()) {
            return collect();
        }

        $jobKey = $jobType === 'short_term' ? 'short_term_job_id' : 'long_term_job_id';

        $messagesByJob = JobMessage::with('sender')
            ->whereIn($jobKey, $jobs->pluck('id'))
            ->where('thread', $thread)
            ->latest()
            ->get()
            ->groupBy($jobKey);

        return $jobs->map(function ($job) use ($request, $messagesByJob, $jobType, $thread, $counterpart): array {
            $messages = $messagesByJob->get($job->id, collect());
            $last = $messages->first();

            return [
                'job_id' => $job->id,
                'job_type' => $jobType,
                'thread' => $thread,
                'title' => $job->title,
                'counterpart' => $counterpart($job),
                'last_message' => $last ? [
                    'id' => $last->id,
                    'message' => $last->message,
                    'file_name' => $last->file_name,
                    'sender_name' => $last->sender?->name,
                    'is_mine' => $last->sender_id === $request->user()->id,
                    'created_at' => $last->created_at?->toDateTimeString(),
                    'time_ago' => $last->created_at?->shortRelativeToNowDiffForHumans(),
                ] : null,
                'unread_count' => $messages
                    ->filter(fn (JobMessage $message): bool => $message->sender_id !== $request->user()->id && $message->read_at === null)
                    ->count(),
                'last_activity_at' => $last?->created_at?->toDateTimeString(),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $conversations
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterConversations(Collection $conversations, string $search): Collection
    {
        if ($search === '') {
            return $conversations->values();
        }

        $needle = mb_strtolower($search);

        return $conversations
            ->filter(fn (array $conversation): bool => str_contains(mb_strtolower($conversation['title'] ?? ''), $needle)
                || str_contains(mb_strtolower($conversation['counterpart']['name'] ?? ''), $needle))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $conversations
     * @return Collection<int, array<string, mixed>>
     */
    protected function sortConversationsByActivity(Collection $conversations): Collection
    {
        return $conversations
            ->sortByDesc(fn (array $conversation): string => $conversation['last_activity_at'] ?? '')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $conversations
     * @return array{conversations: int, unread: int}
     */
    protected function tabSummary(Collection $conversations): array
    {
        return [
            'conversations' => $conversations->count(),
            'unread' => $conversations->sum('unread_count'),
        ];
    }
}
