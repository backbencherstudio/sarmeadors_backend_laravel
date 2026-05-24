<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobAttendanceController extends Controller
{
    // GET /agency/jobs/short-term/{shortTermJob}/attendance
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if ($shortTermJob->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $attendance = ShortTermJobAttendance::where('short_term_job_id', $shortTermJob->id)
                ->with('candidate')
                ->orderBy('booking_date')
                ->get();

            return $this->sendResponse([
                'job' => $shortTermJob->load(['dates', 'candidate']),
                'attendance' => $attendance,
            ], 'Attendance retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
