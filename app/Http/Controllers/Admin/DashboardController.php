<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;

class DashboardController extends Controller
{
    public function index()
    {
        $recentAgencies = Agency::latest()->take(10)->get(['id', 'name', 'email', 'status', 'created_at']);

        $totalAgencies = Agency::count();
        $activeAgencies = Agency::where('status', 'active')->count();
        $suspendedAgencies = Agency::where('status', 'suspended')->count();
        $totalClients = Client::count();
        $totalCandidates = Candidate::count();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data fetched successfully.',
            'data' => [
                'recent_agencies' => $recentAgencies,
                'total_agencies' => $totalAgencies,
                'active_agencies' => $activeAgencies,
                'suspended_agencies' => $suspendedAgencies,
                'total_clients' => $totalClients,
                'total_candidates' => $totalCandidates,
            ],
        ]);
    }
}
