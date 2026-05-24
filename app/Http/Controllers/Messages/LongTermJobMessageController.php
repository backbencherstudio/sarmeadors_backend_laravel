<?php

namespace App\Http\Controllers\Messages;

use App\Events\NewJobMessage;
use App\Http\Controllers\Controller;
use App\Models\JobMessage;
use App\Models\LongTermJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

abstract class LongTermJobMessageController extends Controller
{
    abstract protected function resolveActor(Request $request): ?Model;

    abstract protected function isAuthorized(Model $actor, LongTermJob $job): bool;

    abstract protected function thread(): string;

    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $actor = $this->resolveActor($request);

            if (! $actor || ! $this->isAuthorized($actor, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $thread = $this->thread();

            $messages = JobMessage::with('sender')
                ->where('long_term_job_id', $longTermJob->id)
                ->where('thread', $thread)
                ->oldest()
                ->paginate(50);

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

    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $actor = $this->resolveActor($request);

            if (! $actor || ! $this->isAuthorized($actor, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $validated = $request->validate([
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
                'thread' => $this->thread(),
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

    public function unreadCounts(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $actor = $this->resolveActor($request);

            if (! $actor || ! $this->isAuthorized($actor, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $thread = $this->thread();

            $unread = JobMessage::where('long_term_job_id', $longTermJob->id)
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
