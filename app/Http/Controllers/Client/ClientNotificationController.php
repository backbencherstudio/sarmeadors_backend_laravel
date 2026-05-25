<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNotificationController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/notifications
    // filter=all|unread
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'unread_count' => 3,
            'notifications' => [
                [
                    'id' => 1,
                    'type' => 'login',
                    'title' => 'New login detected',
                    'body' => 'Login from Chrome on MacOS, New York',
                    'read_at' => null,
                    'created_at' => now()->subMinutes(7)->toDateTimeString(),
                ],
                [
                    'id' => 2,
                    'type' => 'job_posted',
                    'title' => 'New Job Posted',
                    'body' => 'A Nanny is needed for 5 interviews from Manhattan',
                    'read_at' => null,
                    'created_at' => now()->subDay()->toDateTimeString(),
                ],
                [
                    'id' => 3,
                    'type' => 'interview_scheduled',
                    'title' => 'Interview Scheduled',
                    'body' => 'Video Interview is scheduled for July 30, 2026',
                    'read_at' => null,
                    'created_at' => now()->subDay()->toDateTimeString(),
                ],
                [
                    'id' => 4,
                    'type' => 'payment_due',
                    'title' => 'Payment Due',
                    'body' => 'Invoice #C2025 is due Friday. Process payment promptly.',
                    'read_at' => now()->subDays(2)->toDateTimeString(),
                    'created_at' => now()->subDays(2)->toDateTimeString(),
                ],
            ],
        ], 'Notifications retrieved successfully.', 200);
    }

    // PUT /client/notifications/{id}/read
    public function markRead(Request $request, int $id): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'id' => $id,
            'read_at' => now()->toDateTimeString(),
        ], 'Notification marked as read.', 200);
    }

    // PUT /client/notifications/mark-all-read
    public function markAllRead(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'marked' => 3,
        ], 'All notifications marked as read.', 200);
    }

    // DELETE /client/notifications/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([], 'Notification deleted.', 200);
    }
}
