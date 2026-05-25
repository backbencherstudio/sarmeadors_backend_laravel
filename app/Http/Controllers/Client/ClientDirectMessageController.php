<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDirectMessageController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/messages/threads
    // tab=admin|candidate
    public function threads(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $tab = $request->query('tab', 'admin');

        return $this->sendResponse([
            'tab' => $tab,
            'threads' => [
                [
                    'id' => 1,
                    'participant' => [
                        'id' => 5,
                        'name' => 'Davis Rosser',
                        'role' => $tab,
                        'avatar' => null,
                        'last_seen' => '09:40',
                    ],
                    'last_message' => 'Sure, let me tell you about what we...',
                    'unread_count' => 2,
                    'last_at' => now()->subMinutes(3)->toDateTimeString(),
                ],
            ],
        ], 'Threads retrieved successfully.', 200);
    }

    // GET /client/messages/threads/{thread}
    public function show(Request $request, int $thread): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'thread_id' => $thread,
            'participant' => ['id' => 5, 'name' => 'Marilyn George', 'last_seen' => '09:40'],
            'messages' => [
                ['id' => 1, 'from' => 'them', 'type' => 'text', 'body' => 'Hello Marilyn! consectetur adipiscing elit amet.', 'sent_at' => '09:10'],
                ['id' => 2, 'from' => 'them', 'type' => 'text', 'body' => 'Fames eros urna, felis morbi a est est.', 'sent_at' => '09:40'],
                ['id' => 3, 'from' => 'them', 'type' => 'audio', 'audio_url' => '/storage/audio/sample.mp3', 'duration' => 24, 'sent_at' => '09:40'],
                ['id' => 4, 'from' => 'me', 'type' => 'text', 'body' => 'How confident are we on presenting this?', 'sent_at' => '09:50'],
            ],
        ], 'Thread retrieved successfully.', 200);
    }

    // POST /client/messages/threads/{thread}
    public function store(Request $request, int $thread): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:5000',
            'type' => 'nullable|in:text,audio,image,file',
            'attachment_url' => 'nullable|string|max:1024',
        ]);

        return $this->sendResponse([
            'id' => rand(100, 999),
            'thread_id' => $thread,
            'from' => 'me',
            'type' => $validated['type'] ?? 'text',
            'body' => $validated['message'] ?? null,
            'attachment_url' => $validated['attachment_url'] ?? null,
            'sent_at' => now()->format('H:i'),
        ], 'Message sent.', 201);
    }

    // GET /client/messages/unread-counts
    public function unreadCounts(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'admin' => 2,
            'candidate' => 1,
            'total' => 3,
        ], 'Unread counts retrieved.', 200);
    }
}
