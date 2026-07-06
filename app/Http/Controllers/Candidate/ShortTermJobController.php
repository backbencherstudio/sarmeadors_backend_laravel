<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobMessage;
use App\Models\Payment;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use App\Traits\FormatsAssignedJob;
use App\Traits\FormatsTime;
use App\Traits\ResolvesCandidate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobController extends Controller
{
    use FormatsAssignedJob;
    use FormatsTime;
    use ResolvesCandidate;

    // GET /candidate/jobs/short-term
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $status = $request->query('status');

        $query = ShortTermJob::with(['dates', 'children', 'location', 'client', 'latestAttendance'])
            ->where('candidate_id', $candidate->id);

        if ($status) {
            $query->where('status', $status);
        }

        $jobs = $query->latest()->get()
            ->map(fn (ShortTermJob $job): array => $this->formatAssignedJobCard($job));

        $counts = ShortTermJob::where('candidate_id', $candidate->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->sendResponse(['counts' => $counts, 'jobs' => $jobs], 'Jobs retrieved successfully.', 200);
    }

    // GET /candidate/jobs/short-term/{shortTermJob}
    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $shortTermJob->candidate_id !== $candidate->id || $shortTermJob->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $shortTermJob->load([
            'dates',
            'children',
            'location',
            'client',
            'attendance' => fn ($query) => $query->where('candidate_id', $candidate->id),
        ]);

        return $this->sendResponse(
            $this->formatJobDetails($request, $shortTermJob, $candidate),
            'Job retrieved successfully.',
            200
        );
    }

    // GET /candidate/jobs/short-term/{shortTermJob}/invoice
    public function invoice(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $shortTermJob->candidate_id !== $candidate->id || $shortTermJob->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $shortTermJob->load([
            'client',
            'attendance' => fn ($query) => $query->where('candidate_id', $candidate->id)->orderBy('booking_date'),
        ]);

        return $this->sendResponse(
            $this->formatInvoice($shortTermJob, $candidate),
            'Invoice retrieved successfully.',
            200
        );
    }

    private function formatInvoice(ShortTermJob $job, Candidate $candidate): array
    {
        $rate = (float) $job->compensation_amount;

        $lineItems = $job->attendance
            ->filter(fn (ShortTermJobAttendance $attendance): bool => $attendance->duration_minutes > 0)
            ->map(function (ShortTermJobAttendance $attendance) use ($rate): array {
                $hours = round($attendance->duration_minutes / 60, 2);

                return [
                    'date' => $attendance->booking_date->toDateString(),
                    'check_in' => $this->formatTime($attendance->check_in),
                    'check_out' => $this->formatTime($attendance->check_out),
                    'worked_minutes' => $attendance->duration_minutes,
                    'worked_label' => $this->formatDuration($attendance->duration_minutes),
                    'amount' => round($hours * $rate, 2),
                ];
            })
            ->values();

        $summary = $this->formatAttendanceSummary($job);

        return [
            'job_id' => $job->id,
            'job_type' => 'short_term',
            'title' => $job->title,
            'candidate' => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
            ],
            'client' => [
                'id' => $job->client?->id,
                'name' => $this->formatClientName($job),
            ],
            'compensation' => $summary['compensation'],
            'line_items' => $lineItems,
            'totals' => [
                'total_worked_minutes' => $summary['total_worked_minutes'],
                'total_worked_label' => $summary['total_worked_label'],
                'total_earning' => $summary['total_earning'],
                'total_payment' => $summary['total_payment'],
                'due_payment' => $summary['due_payment'],
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    private function formatJobDetails(Request $request, ShortTermJob $job, Candidate $candidate): array
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m').'-01'))->startOfMonth();
        $messagesCount = JobMessage::where('short_term_job_id', $job->id)
            ->where('thread', 'candidate')
            ->count();
        $unreadMessagesCount = JobMessage::where('short_term_job_id', $job->id)
            ->where('thread', 'candidate')
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $job->id,
            'job_type' => 'short_term',
            'title' => $job->title,
            'status' => $job->status,
            'client' => [
                'id' => $job->client?->id,
                'name' => $this->formatClientName($job),
                'image_url' => $job->client?->image_url,
            ],
            'tabs' => [
                'attendance_calendar' => $this->formatAttendanceCalendar($job, $month),
                'job_details' => $this->formatJobDetailsTab($job),
                'messages' => [
                    'total' => $messagesCount,
                    'unread' => $unreadMessagesCount,
                    'channel' => 'private-short-term-job-messages.'.$job->id,
                    'event' => '.new-message',
                ],
            ],
            'actions' => [
                'can_check_in' => $this->canCheckIn($job),
                'can_check_out' => $this->canCheckOut($job),
                'check_in_url' => "/api/candidate/jobs/short-term/{$job->id}/check-in",
                'check_out_url' => "/api/candidate/jobs/short-term/{$job->id}/check-out",
                'attendance_url' => "/api/candidate/jobs/short-term/{$job->id}/attendance",
                'messages_url' => "/api/candidate/jobs/short-term/{$job->id}/messages",
                'message_unread_counts_url' => "/api/candidate/jobs/short-term/{$job->id}/messages/unread-counts",
                'invoice_url' => "/api/candidate/jobs/short-term/{$job->id}/invoice",
            ],
        ];
    }

    private function formatAttendanceCalendar(ShortTermJob $job, Carbon $month): array
    {
        $summary = $this->formatAttendanceSummary($job);
        $attendanceByDate = $job->attendance->keyBy(fn (ShortTermJobAttendance $attendance): string => $attendance->booking_date->toDateString());
        $bookingsByDate = $job->dates->keyBy(fn ($date): string => Carbon::parse($date->booking_date)->toDateString());

        $days = collect(range(1, $month->daysInMonth))
            ->map(function (int $day) use ($month, $attendanceByDate, $bookingsByDate): array {
                $date = $month->copy()->day($day)->toDateString();
                $attendance = $attendanceByDate->get($date);
                $booking = $bookingsByDate->get($date);

                return [
                    'date' => $date,
                    'is_booked' => $booking !== null,
                    'booking_time' => $booking ? [
                        'from' => $this->formatTime($booking->start_time),
                        'to' => $this->formatTime($booking->end_time),
                        'range' => $this->formatTime($booking->start_time).' - '.$this->formatTime($booking->end_time),
                    ] : null,
                    'check_in' => $this->formatTime($attendance?->check_in),
                    'check_out' => $this->formatTime($attendance?->check_out),
                    'worked_minutes' => $attendance?->duration_minutes ?? 0,
                    'worked_label' => $attendance && $attendance->duration_minutes > 0
                        ? $this->formatDuration($attendance->duration_minutes)
                        : null,
                    'is_absent' => false,
                ];
            });

        return [
            'month' => $month->format('Y-m'),
            'summary' => $summary,
            'today' => $days->firstWhere('date', now()->toDateString()),
            'days' => $days,
        ];
    }

    private function formatAttendanceSummary(ShortTermJob $job): array
    {
        $totalWorkedMinutes = (int) $job->attendance->sum(fn (ShortTermJobAttendance $attendance): int => $attendance->duration_minutes);
        $totalHours = round($totalWorkedMinutes / 60, 2);
        $totalEarning = round($totalHours * (float) $job->compensation_amount, 2);
        $totalPayment = (float) Payment::where('short_term_job_id', $job->id)
            ->where('status', 'paid')
            ->sum('amount');

        return [
            'compensation' => [
                'amount' => (float) $job->compensation_amount,
                'currency' => $job->compensation_currency,
                'type' => $job->compensation_type,
                'label' => $this->formatCompensation($job),
            ],
            'total_worked_minutes' => $totalWorkedMinutes,
            'total_worked_label' => $this->formatDuration($totalWorkedMinutes),
            'total_earning' => $totalEarning,
            'total_payment' => $totalPayment,
            'due_payment' => max(0, round($totalEarning - $totalPayment, 2)),
        ];
    }

    private function formatJobDetailsTab(ShortTermJob $job): array
    {
        return [
            'booking_details' => [
                'dates' => $job->dates
                    ->sortBy(['booking_date', 'start_time'])
                    ->map(fn ($date): array => [
                        'date' => Carbon::parse($date->booking_date)->toDateString(),
                        'start_time' => $this->formatTime($date->start_time),
                        'end_time' => $this->formatTime($date->end_time),
                    ])
                    ->values(),
                'description' => $job->description,
                'address' => $this->formatAddress($job),
                'budget' => [
                    'compensation' => $this->formatCompensation($job),
                ],
            ],
            'requirements' => [
                'children' => $this->formatChildren($job->children),
            ],
            'additional_information' => [],
        ];
    }

    private function canCheckIn(ShortTermJob $job): bool
    {
        $today = now()->toDateString();
        $attendance = $job->attendance->first(fn (ShortTermJobAttendance $attendance): bool => $attendance->booking_date?->toDateString() === $today);

        return $job->status === 'running'
            && $job->dates->contains(fn ($date): bool => Carbon::parse($date->booking_date)->toDateString() === $today)
            && ! $attendance?->check_in;
    }

    private function canCheckOut(ShortTermJob $job): bool
    {
        $today = now()->toDateString();
        $attendance = $job->attendance->first(fn (ShortTermJobAttendance $attendance): bool => $attendance->booking_date?->toDateString() === $today);

        return $job->status === 'running'
            && filled($attendance?->check_in)
            && blank($attendance?->check_out);
    }
}
