<?php

namespace App\Http\Controllers\Agency;

use App\Events\NewJobMessage;
use App\Http\Controllers\Controller;
use App\Models\JobMessage;
use App\Models\LongTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JobMessageController extends Controller
{
    private function resolveJob(Request $request, LongTermJob $job): bool
    {
        return $job->agency_id === $request->current_agency->id;
    }

    // GET /agency/jobs/long-term/{longTermJob}/messages?thread=client|candidate&page=1
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $thread = $request->query('thread', 'client');

            if (! in_array($thread, ['client', 'candidate'])) {
                return $this->sendError('Invalid thread. Use client or candidate.', [], 422);
            }

            $messages = JobMessage::with('sender')
                ->where('long_term_job_id', $longTermJob->id)
                ->where('thread', $thread)
                ->oldest()
                ->paginate(50);

            // Mark unread as read (messages not sent by the agency user)
            JobMessage::where('long_term_job_id', $longTermJob->id)
                ->where('thread', $thread)
                ->where('sender_id', '!=', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return $this->sendResponse($messages, 'Messages retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /agency/jobs/long-term/{longTermJob}/messages
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $validated = $request->validate([
                'thread' => 'required|in:client,candidate',
                'message' => 'nullable|string|max:5000',
                'file' => 'nullable|file|max:10240',
            ]);

            if (empty($validated['message']) && ! $request->hasFile('file')) {
                return $this->sendError('Message or file is required.', [], 422);
            }

            $filePath = null;
            $fileName = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store('job-messages', 'public');
                $fileName = $file->getClientOriginalName();
            }

            $msg = JobMessage::create([
                'long_term_job_id' => $longTermJob->id,
                'sender_id' => $request->user()->id,
                'thread' => $validated['thread'],
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

    // GET /agency/jobs/long-term/{longTermJob}/messages/unread-counts
    public function unreadCounts(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $counts = JobMessage::where('long_term_job_id', $longTermJob->id)
                ->where('sender_id', '!=', $request->user()->id)
                ->whereNull('read_at')
                ->selectRaw('thread, count(*) as unread')
                ->groupBy('thread')
                ->pluck('unread', 'thread');

            return $this->sendResponse($counts, 'Unread counts retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
