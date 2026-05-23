<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use App\Models\LongTermJobNannyPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobAttendanceController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/long-term/{longTermJob}/attendance?month=2026-01
    public function calendar(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (!$client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $month = $request->query('month', Carbon::now()->format('Y-m'));

            try {
                $start = Carbon::parse($month . '-01')->startOfMonth();
                $end   = $start->copy()->endOfMonth();
            } catch (\Exception) {
                return $this->sendError('Invalid month format. Use Y-m (e.g. 2026-01).', [], 422);
            }

            $attendance = LongTermJobAttendance::where('long_term_job_id', $longTermJob->id)
                ->whereBetween('date', [$start, $end])
                ->orderBy('date')
                ->get()
                ->keyBy(fn ($a) => $a->date->toDateString());

            $summary = $this->buildSummary($longTermJob);

            return $this->sendResponse([
                'job'        => $longTermJob->load(['candidate', 'schedules']),
                'month'      => $month,
                'attendance' => $attendance,
                'summary'    => $summary,
            ], 'Attendance calendar retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function buildSummary(LongTermJob $job): array
    {
        $attendance = LongTermJobAttendance::where('long_term_job_id', $job->id)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get();

        $totalMinutes = $attendance->sum(fn ($a) => $a->duration_minutes);
        $totalHours   = round($totalMinutes / 60, 2);

        $totalPayable = 0;
        if ($job->compensation_type === 'per_hour') {
            $totalPayable = round($totalHours * (float) $job->compensation_amount, 2);
        }

        $totalPaid = (float) LongTermJobNannyPayment::where('long_term_job_id', $job->id)->sum('amount');
        $due       = max(0, round($totalPayable - $totalPaid, 2));

        return [
            'compensation_amount'   => (float) $job->compensation_amount,
            'compensation_type'     => $job->compensation_type,
            'compensation_currency' => $job->compensation_currency,
            'total_hours_worked'    => $totalHours,
            'total_payable'         => $totalPayable,
            'total_paid'            => $totalPaid,
            'due_payment'           => $due,
        ];
    }
}
