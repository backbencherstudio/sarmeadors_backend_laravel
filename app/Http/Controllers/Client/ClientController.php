<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {

    }

    public function store(Request $request)
    {
        try {

            $agency = $request->current_agency;

            $validated = $request->validate([
                'first_name'  => 'required|string|max:255',
                'last_name'   => 'nullable|string|max:255',
                'email'       => 'required|email|unique:clients,email',
                'mobile'      => 'nullable|string|max:20',
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'hear_about_us' => 'nullable|string',
            ]);

            $client = Client::create([
                'agency_id'     => $agency->id,
                'first_name'    => $validated['first_name'],
                'last_name'     => $validated['last_name'] ?? null,
                'email'         => $validated['email'],
                'mobile'        => $validated['mobile'] ?? null,
                'location_id'   => $validated['location_id'] ?? null,
                'hear_about_us' => $validated['hear_about_us'] ?? null,
            ]);

            return $this->sendResponse($client, 'Client created successfully', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);

        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
