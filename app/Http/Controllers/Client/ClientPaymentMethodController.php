<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientPaymentMethod;
use App\Traits\ResolvesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPaymentMethodController extends Controller
{
    use ResolvesClient;

    // GET /client/payment-methods
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $methods = ClientPaymentMethod::where('client_id', $client->id)
            ->where('agency_id', $request->current_agency->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(fn (ClientPaymentMethod $method): array => $this->format($method));

        return $this->sendResponse($methods, 'Saved cards retrieved.', 200);
    }

    // DELETE /client/payment-methods/{paymentMethod}
    public function destroy(Request $request, ClientPaymentMethod $paymentMethod): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($paymentMethod->client_id !== $client->id || $paymentMethod->agency_id !== $request->current_agency->id) {
            return $this->sendError('Saved card not found.', [], 404);
        }

        $paymentMethod->delete();

        return $this->sendResponse([], 'Saved card removed.', 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ClientPaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'brand' => $method->brand,
            'last4' => $method->last4,
            'exp_month' => $method->exp_month,
            'exp_year' => $method->exp_year,
            'cardholder_name' => $method->cardholder_name,
            'is_default' => $method->is_default,
        ];
    }
}
