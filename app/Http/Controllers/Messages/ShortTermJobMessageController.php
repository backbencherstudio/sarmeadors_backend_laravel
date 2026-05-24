<?php

namespace App\Http\Controllers\Messages;

use App\Events\NewJobMessage;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobMessage;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShortTermJobMessageController extends Controller
{
    private function resolveThread(Request $request): ?string
    {
        if ($request->user()->hasRole('agency_admin')) {
            $thread = $request->query('thread', 'client');

            return in_array($thread, ['client', 'candidate']) ? $thread : null;
        }

        if ($request->user()->hasRole('client')) {
            return 'client';
        }

        if ($request->user()->hasRole('candidate')) {
            return 'candidate';
        }

        return null;
    }

    private function isAuthorized(Request $request, ShortTermJob $job): bool
    {
        if ($request->user()->hasRole('agency_admin')) {
            return $job->agency_id === $request->current_agency->id;
        }

        if ($request->user()->hasRole('client')) {
            $client = Client::where('email', $request->user()->email)
                ->where('agency_id', $request->current_agency->id)
                ->first();

            return $client && $job->client_id === $client->id;
        }

        if ($request->user()->hasRole('candidate')) {
            $candidate = Candidate::where('email', $request->user()->email)
                ->where('agency_id', $request->current_agency->id)
                ->first();

            return $candidate && $job->candidate_id === $candidate->id;
        }

        return false;
    }

    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $thread = $this->resolveThread($request);

            if (! $thread) {
                return $this->sendError('Invalid thread.', [], 422);
            }

            if (! $this->isAuthorized($request, $shortTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $messages = JobMessage::with('sender')
                ->where('short_term_job_id', $shortTermJob->id)
                ->where('thread', $thread)
                ->oldest()
                ->paginate(50);

            JobMessage::where('short_term_job_id', $shortTermJob->id)
                ->where('thread', $thread)
                ->where('sender_id', '!=', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return $this->sendResponse($messages, 'Messages retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if (! $this->isAuthorized($request, $shortTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $threadRules = $request->user()->hasRole('agency_admin')
                ? 'required|in:client,candidate'
                : 'nullable|in:client,candidate';

            $validated = $request->validate([
                'thread' => $threadRules,
                'message' => 'nullable|string|max:5000',
                'file' => 'nullable|file|max:10240',
            ]);

            if (empty($validated['message']) && ! $request->hasFile('file')) {
                return $this->sendError('Message or file is required.', [], 422);
            }

            $thread = $request->user()->hasRole('agency_admin')
                ? $validated['thread']
                : $this->resolveThread($request);

            $filePath = null;
            $fileName = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store('job-messages', 'public');
                $fileName = $file->getClientOriginalName();
            }

            $msg = JobMessage::create([
                'short_term_job_id' => $shortTermJob->id,
                'sender_id' => $request->user()->id,
                'thread' => $thread,
                'message' => $validated['message'] ?? null,
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);

            $msg->load('sender');

            broadcast(new NewJobMessage($msg))->toOthers();

            return $this->sendResponse($msg, 'Message sent.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function unreadCounts(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if (! $this->isAuthorized($request, $shortTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            if ($request->user()->hasRole('agency_admin')) {
                $counts = JobMessage::where('short_term_job_id', $shortTermJob->id)
                    ->where('sender_id', '!=', $request->user()->id)
                    ->whereNull('read_at')
                    ->selectRaw('thread, count(*) as unread')
                    ->groupBy('thread')
                    ->pluck('unread', 'thread');

                return $this->sendResponse($counts, 'Unread counts retrieved.', 200);
            }

            $thread = $this->resolveThread($request);

            $unread = JobMessage::where('short_term_job_id', $shortTermJob->id)
                ->where('thread', $thread)
                ->where('sender_id', '!=', $request->user()->id)
                ->whereNull('read_at')
                ->count();

            return $this->sendResponse([$thread => $unread], 'Unread counts retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
