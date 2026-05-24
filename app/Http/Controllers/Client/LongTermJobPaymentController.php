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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LongTermJobPaymentController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/long-term/{longTermJob}/invoice
    public function invoice(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            // Calculate total worked hours from attendance
            $attendances = LongTermJobAttendance::where('long_term_job_id', $longTermJob->id)
                ->whereNotNull('check_in')
                ->whereNotNull('check_out')
                ->get();

            $totalMinutes = $attendances->sum(function ($a) {
                return Carbon::parse($a->check_in)->diffInMinutes(Carbon::parse($a->check_out));
            });
            $totalHours = round($totalMinutes / 60, 2);

            $totalPayable = $totalHours * $longTermJob->compensation_amount;

            $totalPaid = LongTermJobNannyPayment::where('long_term_job_id', $longTermJob->id)->sum('amount');

            $due = max(0, $totalPayable - $totalPaid);

            return $this->sendResponse([
                'compensation' => $longTermJob->compensation_amount,
                'compensation_type' => $longTermJob->compensation_type,
                'currency' => $longTermJob->compensation_currency,
                'total_worked_hours' => $totalHours,
                'total_payable' => round($totalPayable, 2),
                'total_paid' => round($totalPaid, 2),
                'due_payment' => round($due, 2),
            ], 'Invoice retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/long-term/{longTermJob}/payments
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $payments = LongTermJobNannyPayment::where('long_term_job_id', $longTermJob->id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($payments, 'Payments retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/long-term/{longTermJob}/payments
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if (! $longTermJob->candidate_id) {
                return $this->sendError('No candidate assigned to this job.', [], 422);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string|max:100',
                'payment_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
            ]);

            $payment = LongTermJobNannyPayment::create([
                'long_term_job_id' => $longTermJob->id,
                'candidate_id' => $longTermJob->candidate_id,
                'agency_id' => $longTermJob->agency_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'amount' => $validated['amount'],
                'currency' => $longTermJob->compensation_currency,
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]);

            return $this->sendResponse($payment, 'Payment saved.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/long-term/{longTermJob}/payments/{payment}/download
    public function download(Request $request, LongTermJob $longTermJob, LongTermJobNannyPayment $payment): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id || $payment->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            return $this->sendResponse([
                'invoice_number' => $payment->invoice_number,
                'job_title' => $longTermJob->title,
                'candidate_id' => $payment->candidate_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'payment_date' => $payment->payment_date,
                'notes' => $payment->notes,
                'compensation' => $longTermJob->compensation_amount,
                'compensation_type' => $longTermJob->compensation_type,
            ], 'Invoice details retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = strtoupper(Str::random(14));
        } while (LongTermJobNannyPayment::where('invoice_number', $number)->exists());

        return $number;
    }
}
