<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/payments
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'stripe_connected' => false,
            'pending_invoice_total' => 100,
            'payments' => [
                ['invoice' => 'GSV5074892430I', 'description' => 'Cash payment received for job placement service fee', 'amount' => 11000.00, 'method' => 'Cash', 'date' => '2026-02-07 06:50:00'],
                ['invoice' => 'GSV5074892390I', 'description' => 'Manual payment recorded for recruitment subscription plan', 'amount' => 10800.00, 'method' => 'Bank Transfer', 'date' => '2026-02-18 16:02:00'],
                ['invoice' => 'GSV5074892430I', 'description' => 'Check payment received for candidate sourcing services', 'amount' => 37500.00, 'method' => 'Cheque', 'date' => '2026-02-23 09:00:00'],
            ],
            'pagination' => ['current_page' => 1, 'last_page' => 10, 'per_page' => 8, 'total' => 50],
        ], 'Payments retrieved successfully.', 200);
    }

    // GET /client/payments/invoices
    public function invoices(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'invoices' => [
                ['invoice_id' => '5833', 'name' => 'Invoice#', 'total' => 2018.25, 'status' => 'scheduled', 'created_at' => '2025-11-29 14:58:20', 'due_date' => '2025-12-30'],
                ['invoice_id' => '5833', 'name' => 'Bill sanders', 'total' => 2018.25, 'status' => 'scheduled', 'created_at' => '2025-11-29 15:27:14', 'due_date' => '2025-12-30'],
                ['invoice_id' => '5833', 'name' => 'Georgia young', 'total' => 2018.25, 'status' => 'paid', 'created_at' => '2025-11-29 15:27:14', 'due_date' => '2025-12-30'],
            ],
            'pagination' => ['current_page' => 1, 'last_page' => 10, 'per_page' => 8, 'total' => 50],
        ], 'Invoices retrieved successfully.', 200);
    }

    // GET /client/payments/invoices/{invoice}
    public function showInvoice(Request $request, int $invoice): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'status' => 'Not Sent',
            'invoice_no' => $invoice,
            'invoice_name' => 'Invoice #1',
            'date' => '2025-11-30',
            'bill_to' => [
                'name' => 'Sabrina Sultana',
                'email' => 'sabrina.sultana.1929@gmail.com',
            ],
            'items' => [
                ['item' => 'Felicia red', 'rate' => 293, 'quantity' => 5, 'discount' => 458, 'tax' => 20, 'total' => 2018],
                ['item' => 'Felicia red', 'rate' => 369, 'quantity' => 4, 'discount' => 829, 'tax' => 30, 'total' => 2018],
                ['item' => 'Felicia red', 'rate' => 46, 'quantity' => 1, 'discount' => 508, 'tax' => 33, 'total' => 2058],
            ],
            'subtotal' => 7592.40,
            'paid_amount' => 0.00,
            'balance_due' => 7592.40,
        ], 'Invoice retrieved successfully.', 200);
    }

    // POST /client/payments/invoices/{invoice}/pay
    public function payInvoice(Request $request, int $invoice): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $validated = $request->validate([
            'card_number' => 'required|string',
            'expiry' => 'required|string',
            'cvc' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        return $this->sendResponse([
            'invoice_id' => $invoice,
            'amount_paid' => $validated['amount'],
            'transaction_id' => 'pi_'.uniqid(),
            'status' => 'paid',
            'paid_at' => now()->toDateTimeString(),
        ], 'Payment successful.', 200);
    }

    // GET /client/payments/{paymentId}/download
    public function download(Request $request, int $paymentId): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        return $this->sendResponse([
            'download_url' => url("/storage/invoices/{$paymentId}.pdf"),
            'expires_at' => now()->addMinutes(15)->toDateTimeString(),
        ], 'Download URL generated.', 200);
    }
}
