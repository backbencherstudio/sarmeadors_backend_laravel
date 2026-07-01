<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\SetInterviewMeetingRequest;
use App\Http\Requests\Agency\StoreAgencyInterviewRequest;
use App\Models\LongTermJobInterview;
use App\Traits\FormatsTime;
use App\Traits\SendsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central interview scheduling for the agency. All interviews are set,
 * rescheduled and cancelled here — clients and candidates cannot schedule
 * directly, they only request and view.
 */
class AgencyInterviewController extends Controller
{
    use FormatsTime;
    use SendsNotifications;

    // GET /agency/interviews
    // view=list|calendar, status=requested|scheduled|rescheduled|completed|cancelled (tab), search=
    public function index(Request $request): JsonResponse
    {
        $query = LongTermJobInterview::with(['job.client', 'client', 'candidate', 'application'])
            ->where('agency_id', $request->current_agency->id);

        $this->applyTabFilter($query, (string) $request->query('status', 'all'));

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            $query->where(function (Builder $scope) use ($search): void {
                $scope->where('title', 'like', "%{$search}%")
                    ->orWhereHas('job', function (Builder $jobQuery) use ($search): void {
                        $jobQuery->where('title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('candidate', function (Builder $candidateQuery) use ($search): void {
                        $candidateQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $query->orderBy('scheduled_date')->orderBy('available_from');

        if ($request->query('view') === 'calendar') {
            $month = (int) $request->query('month', now()->month);
            $year = (int) $request->query('year', now()->year);

            $grouped = $query
                ->whereMonth('scheduled_date', $month)
                ->whereYear('scheduled_date', $year)
                ->get()
                ->map(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview))
                ->groupBy('date');

            return $this->sendResponse([
                'view' => 'calendar',
                'month' => $month,
                'year' => $year,
                'interviews' => $grouped,
            ], 'Interviews retrieved successfully.', 200);
        }

        $interviews = $query->paginate(15);
        $interviews->getCollection()->transform(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview));

        return $this->sendResponse([
            'view' => 'list',
            'interviews' => $interviews,
        ], 'Interviews retrieved successfully.', 200);
    }

    // GET /agency/interviews/{interview}
    public function show(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        if (! $this->owns($request, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        return $this->sendResponse(
            $this->formatInterview($interview->load(['job.client', 'client', 'candidate', 'application'])),
            'Interview retrieved successfully.',
            200
        );
    }

    // POST /agency/interviews
    // Agency schedules an interview from scratch, one per selected candidate.
    public function store(StoreAgencyInterviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $agencyId = $request->current_agency->id;

        $created = collect($validated['candidate_ids'])->map(function (int $candidateId) use ($validated, $agencyId): LongTermJobInterview {
            return LongTermJobInterview::create([
                'agency_id' => $agencyId,
                'candidate_id' => $candidateId,
                'client_id' => $validated['client_id'] ?? null,
                'title' => $validated['title'],
                'scheduled_date' => $validated['scheduled_date'],
                'available_from' => $validated['available_from'],
                'available_to' => $validated['available_to'],
                'timezone' => $validated['timezone'] ?? null,
                'location' => $validated['location'] ?? null,
                'interview_link' => $validated['interview_link'] ?? null,
                'interview_type' => $validated['interview_type'] ?? 'in_person',
                'description' => $validated['description'] ?? null,
                'special_note' => $validated['special_note'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'status' => 'scheduled',
            ]);
        });

        if ($request->boolean('send_email')) {
            $created->each(function (LongTermJobInterview $interview) use ($agencyId): void {
                $interview->load(['client', 'candidate']);
                $this->notifyParticipants($agencyId, $interview, 'interview_scheduled', 'Interview Scheduled', $this->scheduledBody($interview));
            });
        }

        $payload = $created
            ->map(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview->load(['job.client', 'client', 'candidate'])))
            ->all();

        return $this->sendResponse($payload, 'Interview scheduled successfully.', 201);
    }

    // PUT /agency/interviews/{interview}/schedule
    // Create the meeting for a request, approve a client's reschedule, or
    // reschedule a confirmed meeting — always issuing/refreshing the link.
    public function schedule(SetInterviewMeetingRequest $request, LongTermJobInterview $interview): JsonResponse
    {
        if (! $this->owns($request, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        if (! in_array($interview->status, ['requested', 'scheduled'], true)) {
            return $this->sendError('Only requested or scheduled interviews can be set.', [], 422);
        }

        $validated = $request->validated();

        $update = [
            'interview_link' => $validated['interview_link'],
            'interview_type' => $validated['interview_type'] ?? $interview->interview_type,
            'title' => $validated['title'] ?? $interview->title,
            'location' => $validated['location'] ?? $interview->location,
            'timezone' => $validated['timezone'] ?? $interview->timezone,
            'special_note' => $validated['special_note'] ?? $interview->special_note,
            'status' => 'scheduled',
        ];

        // Final date/time: explicit input wins, else the client's proposed
        // reschedule slot, else the existing schedule stays as-is.
        $date = $validated['scheduled_date'] ?? $interview->reschedule_date?->toDateString();
        if ($date) {
            $update['scheduled_date'] = $date;
        }

        $from = $validated['available_from'] ?? $interview->reschedule_from;
        $to = $validated['available_to'] ?? $interview->reschedule_to;
        if ($from && $to) {
            $update['available_from'] = $from;
            $update['available_to'] = $to;
        }

        $update = array_merge($update, $this->clearedReschedule());

        $interview->update($update);
        $interview = $interview->fresh()->load(['job.client', 'client', 'candidate']);

        $this->notifyParticipants($request->current_agency->id, $interview, 'interview_scheduled', 'Interview Scheduled', $this->scheduledBody($interview));

        return $this->sendResponse($this->formatInterview($interview), 'Interview scheduled successfully.', 200);
    }

    // PUT /agency/interviews/{interview}/decline
    // Reject a brand-new request (cancels it) or reject a pending reschedule
    // (keeps the confirmed meeting as it was).
    public function decline(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        if (! $this->owns($request, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        if ($interview->status === 'requested') {
            $interview->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'] ?? null,
            ]);
            $message = 'Interview request declined.';
        } elseif ($interview->hasPendingReschedule()) {
            $interview->update($this->clearedReschedule());
            $message = 'Reschedule request declined; the interview keeps its original time.';
        } else {
            return $this->sendError('There is no pending request to decline for this interview.', [], 422);
        }

        $interview = $interview->fresh()->load(['job.client', 'client', 'candidate']);

        $this->notifyParticipants(
            $request->current_agency->id,
            $interview,
            'interview_declined',
            'Interview Update',
            sprintf('The agency declined a %s for the interview%s.', $interview->status === 'cancelled' ? 'request' : 'reschedule request', $this->jobLabel($interview))
        );

        return $this->sendResponse($this->formatInterview($interview), $message, 200);
    }

    // PUT /agency/interviews/{interview}/cancel
    public function cancel(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        if (! $this->owns($request, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        if (! in_array($interview->status, ['requested', 'scheduled'], true)) {
            return $this->sendError('Only requested or scheduled interviews can be cancelled.', [], 422);
        }

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $interview->update(array_merge([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['reason'] ?? null,
        ], $this->clearedReschedule()));

        $interview = $interview->fresh()->load(['job.client', 'client', 'candidate']);

        $this->notifyParticipants(
            $request->current_agency->id,
            $interview,
            'interview_cancelled',
            'Interview Cancelled',
            sprintf('The agency cancelled the interview%s.', $this->jobLabel($interview))
        );

        return $this->sendResponse($this->formatInterview($interview), 'Interview cancelled successfully.', 200);
    }

    // PUT /agency/interviews/{interview}/complete
    public function complete(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        if (! $this->owns($request, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        if ($interview->status !== 'scheduled') {
            return $this->sendError('Only scheduled interviews can be completed.', [], 422);
        }

        $interview->update(array_merge(['status' => 'completed'], $this->clearedReschedule()));

        return $this->sendResponse(
            $this->formatInterview($interview->fresh()->load(['job.client', 'client', 'candidate'])),
            'Interview marked as completed.',
            200
        );
    }

    private function owns(Request $request, LongTermJobInterview $interview): bool
    {
        return $interview->agency_id === $request->current_agency->id;
    }

    private function applyTabFilter(Builder $query, string $tab): void
    {
        match ($tab) {
            'requested' => $query->where('status', 'requested'),
            'scheduled' => $query->where('status', 'scheduled')->whereNull('reschedule_requested_at'),
            'rescheduled' => $query->where('status', 'scheduled')->whereNotNull('reschedule_requested_at'),
            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->whereIn('status', ['cancelled', 'declined']),
            default => $query,
        };
    }

    /**
     * @return array<string, null>
     */
    private function clearedReschedule(): array
    {
        return [
            'reschedule_requested_at' => null,
            'reschedule_date' => null,
            'reschedule_from' => null,
            'reschedule_to' => null,
            'reschedule_reason' => null,
        ];
    }

    private function scheduledBody(LongTermJobInterview $interview): string
    {
        return sprintf(
            'The interview%s is scheduled for %s (%s).',
            $this->jobLabel($interview),
            $interview->scheduled_date?->format('M d, Y'),
            $this->formatTime($interview->available_from).' - '.$this->formatTime($interview->available_to)
        );
    }

    private function jobLabel(LongTermJobInterview $interview): string
    {
        $title = $interview->displayTitle();

        return $title ? ' for "'.$title.'"' : '';
    }

    private function notifyParticipants(int $agencyId, LongTermJobInterview $interview, string $type, string $title, string $body): void
    {
        $client = $interview->client ?? $interview->job?->client;
        $meta = [
            'interview_id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
        ];

        $this->notifyPortalUser($agencyId, $client?->email, $type, $title, $body, null, $meta);
        $this->notifyPortalUser($agencyId, $interview->candidate?->email, $type, $title, $body, null, $meta);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInterview(LongTermJobInterview $interview): array
    {
        $job = $interview->job;
        $candidate = $interview->candidate;
        $client = $interview->client ?? $job?->client;

        return [
            'id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
            'application_id' => $interview->long_term_job_application_id,
            'title' => $interview->displayTitle(),
            'date' => $interview->scheduled_date?->toDateString(),
            'time' => [
                'from' => $this->formatTime($interview->available_from),
                'to' => $this->formatTime($interview->available_to),
                'range' => $this->formatTimeRange($interview->available_from, $interview->available_to),
            ],
            'timezone' => $interview->timezone,
            'location' => $interview->location,
            'status' => $interview->status,
            'awaiting_agency' => $interview->isAwaitingAgency(),
            'pending_reschedule' => $interview->hasPendingReschedule(),
            'reschedule_request' => $interview->hasPendingReschedule() ? [
                'date' => $interview->reschedule_date?->toDateString(),
                'from' => $this->formatTime($interview->reschedule_from),
                'to' => $this->formatTime($interview->reschedule_to),
                'reason' => $interview->reschedule_reason,
            ] : null,
            'cancellation_reason' => $interview->cancellation_reason,
            'meeting' => [
                'type' => $interview->interview_type,
                'link' => $interview->interview_link,
            ],
            'special_note' => $interview->special_note,
            'assigned_to' => $interview->assigned_to,
            'client' => [
                'id' => $client?->id,
                'name' => trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: null,
                'email' => $client?->email,
                'mobile' => $client?->mobile,
            ],
            'candidate' => [
                'id' => $candidate?->id,
                'name' => trim(($candidate?->first_name ?? '').' '.($candidate?->last_name ?? '')) ?: null,
                'email' => $candidate?->email,
                'mobile' => $candidate?->mobile,
            ],
        ];
    }

    private function formatTimeRange(?string $from, ?string $to): ?string
    {
        if (! $from || ! $to) {
            return null;
        }

        return $this->formatTime($from).' - '.$this->formatTime($to);
    }
}
