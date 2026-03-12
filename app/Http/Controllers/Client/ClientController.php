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
            // dd($agency);

            $validated = $request->validate([
                'first_name'  => 'required|string|max:255',
                'last_name'   => 'nullable|string|max:255',
                'email'       => 'required|email|unique:clients,email',
                'mobile'      => 'nullable|string|max:20',
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'about_us'    => 'nullable|string|max:1000',
            ]);

            $client = Client::create([
                'agency_id'   => Auth::user()->agency_id,
                'first_name'  => $validated['first_name'],
                'last_name'   => $validated['last_name'] ?? null,
                'email'       => $validated['email'],
                'mobile'      => $validated['mobile'] ?? null,
                'location_id' => $validated['location_id'] ?? null,
                'about_us'    => $validated['about_us'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client created successfully',
                'data'    => $client
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
