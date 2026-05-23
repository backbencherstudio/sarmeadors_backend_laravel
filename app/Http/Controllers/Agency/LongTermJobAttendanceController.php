<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use App\Models\LongTermJobNannyPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobAttendanceController extends Controller
{
    private function resolveJob(Request $request, LongTermJob $job): bool
    {
        return $job->agency_id === $request->current_agency->id;
    }

    // GET /agency/jobs/long-term/{longTermJob}/attendance?month=2026-01
    public function calendar(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (!$this->resolveJob($request, $longTermJob)) {
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

    // POST /agency/jobs/long-term/{longTermJob}/attendance
    // Add or update a single day's attendance record
    public function upsert(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (!$this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            if (!$longTermJob->candidate_id) {
                return $this->sendError('No nanny assigned to this job yet.', [], 422);
            }

            $validated = $request->validate([
                'date'       => 'required|date',
                'check_in'   => 'nullable|date_format:H:i|required_unless:is_absent,true',
                'check_out'  => 'nullable|date_format:H:i|after:check_in',
                'is_absent'  => 'nullable|boolean',
                'notes'      => 'nullable|string|max:500',
            ]);

            $isAbsent = $validated['is_absent'] ?? false;

            $record = LongTermJobAttendance::updateOrCreate(
                [
                    'long_term_job_id' => $longTermJob->id,
                    'candidate_id'     => $longTermJob->candidate_id,
                    'date'             => $validated['date'],
                ],
                [
                    'check_in'  => $isAbsent ? null : ($validated['check_in'] ?? null),
                    'check_out' => $isAbsent ? null : ($validated['check_out'] ?? null),
                    'is_absent' => $isAbsent,
                    'notes'     => $validated['notes'] ?? null,
                ]
            );

            return $this->sendResponse($record, 'Attendance saved.', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/long-term/{longTermJob}/assign-nanny
    public function assignNanny(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (!$this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $validated = $request->validate([
                'candidate_id' => 'required|integer|exists:candidates,id',
            ]);

            $longTermJob->update([
                'candidate_id' => $validated['candidate_id'],
                'status'       => 'running',
            ]);

            return $this->sendResponse(
                $longTermJob->load('candidate'),
                'Nanny assigned and job is now running.',
                200
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /agency/jobs/long-term/{longTermJob}/nanny-payments
    public function listNannyPayments(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (!$this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $payments = $longTermJob->nannyPayments()->orderByDesc('payment_date')->get();

            return $this->sendResponse($payments, 'Nanny payments retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /agency/jobs/long-term/{longTermJob}/nanny-payments
    public function recordNannyPayment(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (!$this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            if (!$longTermJob->candidate_id) {
                return $this->sendError('No nanny assigned to this job.', [], 422);
            }

            $validated = $request->validate([
                'amount'       => 'required|numeric|min:0.01',
                'currency'     => 'nullable|string|size:3',
                'payment_date' => 'required|date',
                'notes'        => 'nullable|string|max:500',
            ]);

            $payment = LongTermJobNannyPayment::create([
                'long_term_job_id' => $longTermJob->id,
                'candidate_id'     => $longTermJob->candidate_id,
                'agency_id'        => $longTermJob->agency_id,
                'amount'           => $validated['amount'],
                'currency'         => $validated['currency'] ?? $longTermJob->compensation_currency,
                'payment_date'     => $validated['payment_date'],
                'notes'            => $validated['notes'] ?? null,
            ]);

            return $this->sendResponse($payment, 'Payment recorded.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
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
            'compensation_amount' => (float) $job->compensation_amount,
            'compensation_type'   => $job->compensation_type,
            'compensation_currency' => $job->compensation_currency,
            'total_hours_worked'  => $totalHours,
            'total_payable'       => $totalPayable,
            'total_paid'          => $totalPaid,
            'due_payment'         => $due,
        ];
    }
}
