<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CandidateMyJobController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs?view=calendar|list
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $filters = $this->resolveFilters($request);
            $jobs = $this->loadJobs($request, $candidate, $filters);

            if ($filters['view'] === 'calendar') {
                return $this->sendResponse(
                    $this->formatCalendar($jobs, $filters),
                    'Calendar jobs retrieved.',
                    200
                );
            }

            return $this->sendResponse(
                $this->formatList($jobs, $filters),
                'Jobs retrieved.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function resolveFilters(Request $request): array
    {
        $requestFilters = $request->query('filter', []);
        $queryFilters = is_array($requestFilters) ? $requestFilters : [];

        return [
            'view' => $request->query('view', 'calendar'),
            'month' => (int) $request->query('month', now()->month),
            'year' => (int) $request->query('year', now()->year),
            'job_type' => $request->query('job_type', $queryFilters['job_type'] ?? 'all'),
            'status' => $request->query('status', $queryFilters['status'] ?? 'running'),
            'search' => trim((string) $request->query('search', $queryFilters['search'] ?? '')),
        ];
    }

    private function loadJobs(Request $request, Candidate $candidate, array $filters): Collection
    {
        $jobs = collect();

        if (in_array($filters['job_type'], ['all', 'short_term'])) {
            $shortTermJobs = ShortTermJob::with([
                'client',
                'children',
                'dates',
                'attendance' => fn ($query) => $query->where('candidate_id', $candidate->id),
                'reviews' => fn ($query) => $query->where('candidate_id', $candidate->id),
            ])
                ->where('agency_id', $request->current_agency->id)
                ->where('candidate_id', $candidate->id)
                ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
                ->get()
                ->map(fn (ShortTermJob $job): array => [
                    'type' => 'short_term',
                    'job' => $job,
                ]);

            $jobs = $jobs->concat($shortTermJobs);
        }

        if (in_array($filters['job_type'], ['all', 'long_term'])) {
            $longTermJobs = LongTermJob::with([
                'client',
                'children',
                'schedules',
                'attendance' => fn ($query) => $query->where('candidate_id', $candidate->id),
                'reviews' => fn ($query) => $query->where('candidate_id', $candidate->id),
            ])
                ->where('agency_id', $request->current_agency->id)
                ->where('candidate_id', $candidate->id)
                ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
                ->get()
                ->map(fn (LongTermJob $job): array => [
                    'type' => 'long_term',
                    'job' => $job,
                ]);

            $jobs = $jobs->concat($longTermJobs);
        }

        if ($filters['search'] !== '') {
            $jobs = $jobs->filter(function (array $item) use ($filters): bool {
                $job = $item['job'];
                $client = $job->client;
                $haystack = collect([
                    $job->title,
                    $job->description,
                    $job->home_city,
                    $job->home_province,
                    $job->country,
                    $job->job_address,
                    $client?->first_name,
                    $client?->last_name,
                ])->filter()->implode(' ');

                return Str::contains(Str::lower($haystack), Str::lower($filters['search']));
            });
        }

        return $jobs->values();
    }

    private function formatCalendar(Collection $jobs, array $filters): array
    {
        $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $events = $jobs
            ->flatMap(fn (array $item): Collection => $this->formatCalendarEvents($item['type'], $item['job'], $start, $end))
            ->sortBy(['date', 'time.from'])
            ->values();

        return [
            'view' => 'calendar',
            'month' => $filters['month'],
            'year' => $filters['year'],
            'filters' => [
                'job_type' => $filters['job_type'],
                'status' => $filters['status'],
            ],
            'events' => $events,
            'events_by_date' => $events->groupBy('date'),
        ];
    }

    private function formatCalendarEvents(string $type, ShortTermJob|LongTermJob $job, Carbon $start, Carbon $end): Collection
    {
        if ($type === 'short_term') {
            return $job->dates
                ->filter(fn ($date): bool => Carbon::parse($date->booking_date)->betweenIncluded($start, $end))
                ->map(fn ($date): array => $this->formatJobCard($type, $job, [
                    'date' => Carbon::parse($date->booking_date)->toDateString(),
                    'time_from' => $date->start_time,
                    'time_to' => $date->end_time,
                ]));
        }

        return $this->longTermOccurrences($job, $start, $end)
            ->map(fn (array $occurrence): array => $this->formatJobCard($type, $job, $occurrence));
    }

    private function longTermOccurrences(LongTermJob $job, Carbon $start, Carbon $end): Collection
    {
        $effectiveStart = $job->start_date ? max(Carbon::parse($job->start_date), $start) : $start;
        $effectiveEnd = $job->end_date ? min(Carbon::parse($job->end_date), $end) : $end;

        if ($effectiveStart->gt($effectiveEnd)) {
            return collect();
        }

        $dates = collect(CarbonPeriod::create($effectiveStart, $effectiveEnd));

        return $job->schedules->flatMap(function ($schedule) use ($dates): Collection {
            return $dates
                ->filter(fn (Carbon $date): bool => $date->dayOfWeek === (int) $schedule->day_of_week)
                ->map(fn (Carbon $date): array => [
                    'date' => $date->toDateString(),
                    'time_from' => $schedule->start_time,
                    'time_to' => $schedule->end_time,
                ]);
        });
    }

    private function formatList(Collection $jobs, array $filters): array
    {
        $cards = $jobs
            ->flatMap(fn (array $item): Collection => $this->formatListCards($item['type'], $item['job']))
            ->sortBy([['date', 'asc'], ['time.from', 'asc']])
            ->values();

        return [
            'view' => 'list',
            'filters' => [
                'job_type' => $filters['job_type'],
                'status' => $filters['status'],
            ],
            'title' => $this->formatStatusHeading($filters['status']),
            'jobs' => $cards,
            'jobs_by_date' => $cards->groupBy(fn (array $card): string => $card['date'] ?? 'unscheduled'),
        ];
    }

    private function formatListCards(string $type, ShortTermJob|LongTermJob $job): Collection
    {
        if ($type === 'short_term') {
            return $job->dates
                ->sortBy(['booking_date', 'start_time'])
                ->map(fn ($date): array => $this->formatJobCard($type, $job, [
                    'date' => Carbon::parse($date->booking_date)->toDateString(),
                    'time_from' => $date->start_time,
                    'time_to' => $date->end_time,
                ]));
        }

        $windowStart = now()->copy()->startOfMonth();
        $windowEnd = now()->copy()->addMonths(2)->endOfMonth();

        return $this->longTermOccurrences($job, $windowStart, $windowEnd)
            ->map(fn (array $occurrence): array => $this->formatJobCard($type, $job, $occurrence));
    }

    private function formatJobCard(string $type, ShortTermJob|LongTermJob $job, array $occurrence): array
    {
        $attendance = $this->attendanceForDate($type, $job, $occurrence['date']);
        $review = $job->reviews->first();

        return [
            'id' => $type.'_'.$job->id.'_'.$occurrence['date'],
            'job_id' => $job->id,
            'job_type' => $type,
            'job_type_label' => $type === 'short_term' ? 'Short-term' : 'Long-term',
            'title' => $job->title,
            'client' => [
                'id' => $job->client?->id,
                'name' => $this->formatClientName($job),
                'image_url' => $job->client?->image_url,
            ],
            'cover_image_url' => $job->cover_image_url,
            'description' => $job->description,
            'description_preview' => $job->description ? Str::limit($job->description, 120) : null,
            'location' => $this->formatLocation($job),
            'date' => $occurrence['date'],
            'date_label' => Carbon::parse($occurrence['date'])->format('M d, Y'),
            'time' => [
                'from' => $this->formatTime($occurrence['time_from']),
                'to' => $this->formatTime($occurrence['time_to']),
                'range' => $this->formatTime($occurrence['time_from']).' - '.$this->formatTime($occurrence['time_to']),
            ],
            'compensation' => $this->formatCompensation($job),
            'status' => $job->status,
            'status_label' => $this->formatStatusLabel($job->status),
            'attendance' => $this->formatAttendance($attendance),
            'cancellation' => $job->status === 'cancelled' ? [
                'reason' => $job->cancellation_reason,
                'cancelled_at' => $job->cancelled_at ? Carbon::parse($job->cancelled_at)->toISOString() : null,
            ] : null,
            'review' => $review ? [
                'id' => $review->id,
                'rating' => $review->rating,
                'review' => $review->review,
            ] : null,
            'actions' => [
                'can_view_details' => true,
                'can_check_in' => $this->canCheckIn($job, $occurrence['date'], $attendance),
                'can_check_out' => $this->canCheckOut($job, $occurrence['date'], $attendance),
                'can_report_client' => in_array($job->status, ['completed', 'cancelled']),
                'can_leave_review' => $job->status === 'completed' && ! $review,
                'can_view_review' => $review !== null,
            ],
            'modal' => [
                'title' => $job->title,
                'subtitle' => $this->formatClientName($job),
                'date' => Carbon::parse($occurrence['date'])->format('d M, D'),
                'time_range' => $this->formatTime($occurrence['time_from']).' - '.$this->formatTime($occurrence['time_to']),
                'can_check_in' => $this->canCheckIn($job, $occurrence['date'], $attendance),
            ],
        ];
    }

    private function attendanceForDate(string $type, ShortTermJob|LongTermJob $job, string $date): ShortTermJobAttendance|LongTermJobAttendance|null
    {
        return $type === 'short_term'
            ? $job->attendance->first(fn (ShortTermJobAttendance $attendance): bool => $attendance->booking_date?->toDateString() === $date)
            : $job->attendance->first(fn (LongTermJobAttendance $attendance): bool => $attendance->date?->toDateString() === $date);
    }

    private function formatAttendance(ShortTermJobAttendance|LongTermJobAttendance|null $attendance): array
    {
        return [
            'checked_in_at' => $this->formatTime($attendance?->check_in),
            'checked_out_at' => $this->formatTime($attendance?->check_out),
            'total_minutes' => $attendance?->duration_minutes ?? 0,
            'total_label' => $attendance ? $this->formatDuration($attendance->duration_minutes) : null,
        ];
    }

    private function canCheckIn(ShortTermJob|LongTermJob $job, string $date, ShortTermJobAttendance|LongTermJobAttendance|null $attendance): bool
    {
        return $job->status === 'running'
            && $date === now()->toDateString()
            && ! $attendance?->check_in;
    }

    private function canCheckOut(ShortTermJob|LongTermJob $job, string $date, ShortTermJobAttendance|LongTermJobAttendance|null $attendance): bool
    {
        return $job->status === 'running'
            && $date === now()->toDateString()
            && filled($attendance?->check_in)
            && blank($attendance?->check_out);
    }

    private function formatLocation(ShortTermJob|LongTermJob $job): array
    {
        return [
            'label' => collect([$job->job_address, $job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
            'street_address' => $job->job_address,
            'city' => $job->home_city,
            'province' => $job->home_province,
            'postal_code' => $job->home_postal_code,
            'country' => $job->country,
        ];
    }

    private function formatCompensation(ShortTermJob|LongTermJob $job): array
    {
        return [
            'amount' => $job->compensation_amount,
            'currency' => $job->compensation_currency,
            'type' => $job->compensation_type,
            'label' => '$'.rtrim(rtrim((string) $job->compensation_amount, '0'), '.').'/hr',
        ];
    }

    private function formatClientName(ShortTermJob|LongTermJob $job): ?string
    {
        return $job->client ? trim($job->client->first_name.' '.$job->client->last_name) : null;
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : null;
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return trim($hours.'h '.$remainingMinutes.'m');
    }

    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
            'running' => 'Running Job',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => Str::headline($status),
        };
    }

    private function formatStatusHeading(string $status): string
    {
        return match ($status) {
            'running' => 'Running Job',
            'completed' => 'Completed Job',
            'cancelled' => 'Cancelled Job',
            'all' => 'All Jobs',
            default => Str::headline($status).' Job',
        };
    }
}
