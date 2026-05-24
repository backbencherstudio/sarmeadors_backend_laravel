<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobAttendanceController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/short-term/{shortTermJob}/attendance
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $attendance = ShortTermJobAttendance::where('short_term_job_id', $shortTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->orderBy('booking_date')
                ->get()
                ->keyBy(fn ($a) => $a->booking_date->toDateString());

            return $this->sendResponse([
                'job' => $shortTermJob->load('dates'),
                'attendance' => $attendance,
            ], 'Attendance retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/short-term/{shortTermJob}/check-in
    public function checkIn(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($shortTermJob->status !== 'running') {
                return $this->sendError('Job is not currently active.', [], 422);
            }

            $today = Carbon::today()->toDateString();

            $bookingDate = $shortTermJob->dates()
                ->where('booking_date', $today)
                ->first();

            if (! $bookingDate) {
                return $this->sendError('No booking date found for today.', [], 422);
            }

            $existing = ShortTermJobAttendance::where('short_term_job_id', $shortTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->where('booking_date', $today)
                ->first();

            if ($existing && $existing->check_in) {
                return $this->sendError('Already checked in for today.', [], 422);
            }

            $record = ShortTermJobAttendance::updateOrCreate(
                [
                    'short_term_job_id' => $shortTermJob->id,
                    'candidate_id' => $candidate->id,
                    'booking_date' => $today,
                ],
                ['check_in' => Carbon::now()->format('H:i')]
            );

            return $this->sendResponse($record, 'Checked in successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/short-term/{shortTermJob}/check-out
    public function checkOut(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($shortTermJob->status !== 'running') {
                return $this->sendError('Job is not currently active.', [], 422);
            }

            $today = Carbon::today()->toDateString();

            $record = ShortTermJobAttendance::where('short_term_job_id', $shortTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->where('booking_date', $today)
                ->first();

            if (! $record || ! $record->check_in) {
                return $this->sendError('You have not checked in today.', [], 422);
            }

            if ($record->check_out) {
                return $this->sendError('Already checked out for today.', [], 422);
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
}
