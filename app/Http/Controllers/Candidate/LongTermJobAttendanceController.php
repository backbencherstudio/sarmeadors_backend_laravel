<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobAttendanceController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/long-term/{longTermJob}/attendance?month=2026-01
    public function calendar(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $longTermJob->candidate_id !== $candidate->id || $longTermJob->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            $month = $request->query('month', Carbon::now()->format('Y-m'));

            try {
                $start = Carbon::parse($month.'-01')->startOfMonth();
                $end = $start->copy()->endOfMonth();
            } catch (\Exception) {
                return $this->sendError('Invalid month format. Use Y-m (e.g. 2026-01).', [], 422);
            }

            $attendance = LongTermJobAttendance::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->whereBetween('date', [$start, $end])
                ->orderBy('date')
                ->get()
                ->keyBy(fn ($a) => $a->date->toDateString());

            return $this->sendResponse([
                'job' => $longTermJob->load('schedules'),
                'month' => $month,
                'attendance' => $attendance,
            ], 'Attendance retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/long-term/{longTermJob}/check-in
    public function checkIn(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $longTermJob->candidate_id !== $candidate->id || $longTermJob->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'running') {
                return $this->sendError('Job is not currently active.', [], 422);
            }

            $today = Carbon::today()->toDateString();

            if (! $this->hasScheduleToday($longTermJob)) {
                return $this->sendError('No schedule found for today.', [], 422);
            }

            $existing = LongTermJobAttendance::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->whereDate('date', $today)
                ->first();

            if ($existing && $existing->check_in) {
                return $this->sendError('Already checked in today.', [], 422);
            }

            if ($existing) {
                $existing->update([
                    'check_in' => Carbon::now()->format('H:i'),
                    'is_absent' => false,
                ]);
                $record = $existing->fresh();
            } else {
                $record = LongTermJobAttendance::create([
                    'long_term_job_id' => $longTermJob->id,
                    'candidate_id' => $candidate->id,
                    'date' => $today,
                    'check_in' => Carbon::now()->format('H:i'),
                    'is_absent' => false,
                ]);
            }

            return $this->sendResponse($record, 'Checked in successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/long-term/{longTermJob}/check-out
    public function checkOut(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $longTermJob->candidate_id !== $candidate->id || $longTermJob->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'running') {
                return $this->sendError('Job is not currently active.', [], 422);
            }

            $today = Carbon::today()->toDateString();

            $record = LongTermJobAttendance::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->whereDate('date', $today)
                ->first();

            if (! $record || ! $record->check_in) {
                return $this->sendError('You have not checked in today.', [], 422);
            }

            if ($record->check_out) {
                return $this->sendError('Already checked out today.', [], 422);
            }

            $record->update(['check_out' => Carbon::now()->format('H:i')]);

            return $this->sendResponse(
                $record->fresh(),
                'Checked out successfully. Duration: '.$record->fresh()->duration_minutes.' minutes.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function hasScheduleToday(LongTermJob $longTermJob): bool
    {
        $today = Carbon::today();

        if ($longTermJob->start_date && $today->lt(Carbon::parse($longTermJob->start_date))) {
            return false;
        }

        if ($longTermJob->end_date && $today->gt(Carbon::parse($longTermJob->end_date))) {
            return false;
        }

        return $longTermJob->schedules()
            ->where('day_of_week', $today->dayOfWeek)
            ->exists();
    }
}
