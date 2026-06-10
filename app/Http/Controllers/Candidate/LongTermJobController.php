<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobMessage;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use App\Models\LongTermJobNannyPayment;
use App\Traits\FormatsAssignedJob;
use App\Traits\FormatsTime;
use App\Traits\ResolvesCandidate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobController extends Controller
{
    use FormatsAssignedJob;
    use FormatsTime;
    use ResolvesCandidate;

    // GET /candidate/jobs/long-term?status=running
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $status = $request->query('status');

        $query = LongTermJob::with(['client', 'schedules', 'children', 'latestAttendance'])
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id);

        if ($status) {
            $query->where('status', $status);
        }

        $jobs = $query->latest()->get();

        $counts = LongTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->sendResponse([
            'counts' => $counts,
            'jobs' => $jobs,
        ], 'Jobs retrieved successfully', 200);
    }

    // GET /candidate/jobs/long-term/{longTermJob}
    public function show(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $longTermJob->candidate_id !== $candidate->id || $longTermJob->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found', [], 404);
        }

        $longTermJob->load([
            'client',
            'schedules',
            'children',
            'attendance' => fn ($query) => $query->where('candidate_id', $candidate->id),
            'nannyPayments' => fn ($query) => $query->where('candidate_id', $candidate->id),
        ]);

        return $this->sendResponse(
            $this->formatJobDetails($request, $longTermJob, $candidate),
            'Job retrieved successfully',
            200
        );
    }

    private function formatJobDetails(Request $request, LongTermJob $job, Candidate $candidate): array
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m').'-01'))->startOfMonth();
        $messagesCount = JobMessage::where('long_term_job_id', $job->id)
            ->where('thread', 'candidate')
            ->count();
        $unreadMessagesCount = JobMessage::where('long_term_job_id', $job->id)
            ->where('thread', 'candidate')
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $job->id,
            'job_type' => 'long_term',
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
                    'channel' => 'private-job-messages.'.$job->id,
                    'event' => '.new-message',
                ],
            ],
            'actions' => [
                'can_check_in' => $this->canCheckIn($job),
                'can_check_out' => $this->canCheckOut($job),
                'check_in_url' => "/api/candidate/jobs/long-term/{$job->id}/check-in",
                'check_out_url' => "/api/candidate/jobs/long-term/{$job->id}/check-out",
                'attendance_url' => "/api/candidate/jobs/long-term/{$job->id}/attendance",
                'messages_url' => "/api/candidate/jobs/long-term/{$job->id}/messages",
                'message_unread_counts_url' => "/api/candidate/jobs/long-term/{$job->id}/messages/unread-counts",
                'invoice_url' => null,
            ],
        ];
    }

    private function formatAttendanceCalendar(LongTermJob $job, Carbon $month): array
    {
        $attendanceByDate = $job->attendance->keyBy(fn (LongTermJobAttendance $attendance): string => $attendance->date->toDateString());

        $days = collect(range(1, $month->daysInMonth))
            ->map(function (int $day) use ($job, $month, $attendanceByDate): array {
                $date = $month->copy()->day($day);
                $attendance = $attendanceByDate->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'is_scheduled' => $this->isScheduledDate($job, $date),
                    'check_in' => $this->formatTime($attendance?->check_in),
                    'check_out' => $this->formatTime($attendance?->check_out),
                    'worked_minutes' => $attendance?->duration_minutes ?? 0,
                    'worked_label' => $attendance && $attendance->duration_minutes > 0
                        ? $this->formatDuration($attendance->duration_minutes)
                        : null,
                    'is_absent' => (bool) $attendance?->is_absent,
                ];
            });

        return [
            'month' => $month->format('Y-m'),
            'summary' => $this->formatAttendanceSummary($job),
            'today' => $days->firstWhere('date', now()->toDateString()),
            'days' => $days,
        ];
    }

    private function formatAttendanceSummary(LongTermJob $job): array
    {
        $totalWorkedMinutes = (int) $job->attendance->sum(fn (LongTermJobAttendance $attendance): int => $attendance->duration_minutes);
        $totalHours = round($totalWorkedMinutes / 60, 2);
        $totalEarning = round($totalHours * (float) $job->compensation_amount, 2);
        $totalPayment = (float) $job->nannyPayments->sum(fn (LongTermJobNannyPayment $payment): float => (float) $payment->amount);

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

    private function formatJobDetailsTab(LongTermJob $job): array
    {
        return [
            'booking_details' => [
                'start_date' => $job->start_date?->toDateString(),
                'end_date' => $job->end_date?->toDateString(),
                'schedules' => $job->schedules
                    ->map(fn ($schedule): array => [
                        'day_of_week' => $schedule->day_of_week,
                        'day_name' => $this->formatDayName((int) $schedule->day_of_week),
                        'start_time' => $this->formatTime($schedule->start_time),
                        'end_time' => $this->formatTime($schedule->end_time),
                    ])
                    ->values(),
                'description' => $job->description,
                'address' => $this->formatAddress($job),
                'budget' => [
                    'compensation' => $this->formatCompensation($job),
                ],
            ],
            'requirements' => [
                'interested_in_two_locations' => $job->bilingual_preference,
                'child_special_needs' => $job->special_needs,
                'after_school_activity' => $job->school_activity,
                'has_housekeeper' => $job->has_housekeeper,
                'prepare_meals' => $job->prepare_meals,
                'travel_with_family' => $job->travel_with_family,
                'paid_vacation' => $job->paid_vacation,
                'spouse_work_from_home' => $job->spouse_work_from_home,
                'nanny_own_car' => $job->nanny_own_car,
                'children' => $this->formatChildren($job->children),
            ],
            'additional_information' => [
                'family_schedule' => $job->family_schedule,
                'household_tasks' => $job->household_tasks,
                'family_philosophy' => $job->family_philosophy,
                'play_dates' => $job->play_dates,
                'addons' => $job->addons,
                'home_description' => $job->home_description,
                'neighborhood_description' => $job->neighborhood_description,
                'nanny_experience' => $job->nanny_experience,
                'payment_norms' => $job->payment_norms,
                'consent_description' => $job->consent_description,
                'child_attachment' => $job->child_attachment,
            ],
        ];
    }

    private function canCheckIn(LongTermJob $job): bool
    {
        $today = now();
        $attendance = $job->attendance->first(fn (LongTermJobAttendance $attendance): bool => $attendance->date?->toDateString() === $today->toDateString());

        return $job->status === 'running'
            && $this->isWithinJobWindow($job, $today)
            && $this->isScheduledDate($job, $today)
            && ! $attendance?->check_in;
    }

    private function canCheckOut(LongTermJob $job): bool
    {
        $today = now()->toDateString();
        $attendance = $job->attendance->first(fn (LongTermJobAttendance $attendance): bool => $attendance->date?->toDateString() === $today);

        return $job->status === 'running'
            && filled($attendance?->check_in)
            && blank($attendance?->check_out);
    }

    private function isWithinJobWindow(LongTermJob $job, Carbon $date): bool
    {
        if ($job->start_date && $date->lt(Carbon::parse($job->start_date))) {
            return false;
        }

        if ($job->end_date && $date->gt(Carbon::parse($job->end_date))) {
            return false;
        }

        return true;
    }

    private function isScheduledDate(LongTermJob $job, Carbon $date): bool
    {
        return $job->schedules->contains('day_of_week', $date->dayOfWeek);
    }

    private function formatDayName(int $day): string
    {
        return Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($day)->format('l');
    }
}
