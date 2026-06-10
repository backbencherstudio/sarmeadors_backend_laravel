<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ShortTermJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobPaymentController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/short-term/{shortTermJob}/invoice
    public function invoice(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $shortTermJob->load('dates');

            $totalHours = $shortTermJob->dates->sum(function ($date) {
                return Carbon::parse($date->start_time)->diffInMinutes(Carbon::parse($date->end_time)) / 60;
            });

            $totalPayable = $totalHours * $shortTermJob->compensation_amount;

            $totalPaid = Payment::where('short_term_job_id', $shortTermJob->id)
                ->where('status', 'succeeded')
                ->sum('amount');

            return $this->sendResponse([
                'compensation' => $shortTermJob->compensation_amount,
                'compensation_type' => $shortTermJob->compensation_type,
                'currency' => $shortTermJob->compensation_currency,
                'total_hours' => round($totalHours, 2),
                'total_payable' => round($totalPayable, 2),
                'total_paid' => round($totalPaid, 2),
                'due_payment' => round(max(0, $totalPayable - $totalPaid), 2),
            ], 'Invoice retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/jobs/short-term/{shortTermJob}/payments
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $payments = Payment::where('short_term_job_id', $shortTermJob->id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($payments, 'Payments retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/short-term/{shortTermJob}/payments
    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        return $this->sendError('Payments for short-term jobs are processed at booking time.', [], 422);
    }

    // GET /client/jobs/short-term/{shortTermJob}/payments/{paymentId}/download
    public function download(Request $request, ShortTermJob $shortTermJob, int $paymentId): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $payment = Payment::where('id', $paymentId)
                ->where('short_term_job_id', $shortTermJob->id)
                ->first();

            if (! $payment) {
                return $this->sendError('Payment not found.', [], 404);
            }

            return $this->sendResponse([
                'job_title' => $shortTermJob->title,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'paid_at' => $payment->updated_at,
                'compensation' => $shortTermJob->compensation_amount,
                'compensation_type' => $shortTermJob->compensation_type,
            ], 'Payment details retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
