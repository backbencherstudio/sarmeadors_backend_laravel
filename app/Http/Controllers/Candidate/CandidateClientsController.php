<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateClientsController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/clients
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $shortTermClientIds = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->pluck('client_id');

            $longTermClientIds = LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->pluck('client_id');

            $allClientIds = $shortTermClientIds->merge($longTermClientIds)->unique()->values();

            $search = $request->query('search');

            $query = Client::whereIn('id', $allClientIds);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            }

            $clients = $query->paginate(10);

            $clients->getCollection()->transform(function (Client $client) use ($candidate, $agencyId) {
                $shortTermJobs = ShortTermJob::where('candidate_id', $candidate->id)
                    ->where('client_id', $client->id)
                    ->where('agency_id', $agencyId)
                    ->latest()
                    ->first();

                $longTermJobs = LongTermJob::where('candidate_id', $candidate->id)
                    ->where('client_id', $client->id)
                    ->where('agency_id', $agencyId)
                    ->latest()
                    ->first();

                $latestJob = $shortTermJobs ?? $longTermJobs;

                $client->job_type = $latestJob
                    ? ($latestJob instanceof ShortTermJob ? 'Short-Term Jobs' : 'Long-Term Jobs')
                    : null;

                $client->job_status = $latestJob?->status;
                $client->image_url = $client->image ? asset('storage/'.$client->image) : null;

                return $client;
            });

            return $this->sendResponse($clients, 'Clients retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/clients/{client}
    public function show(Request $request, Client $client): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $hasWorkedWith = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->exists()
                || LongTermJob::where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->exists();

            if (! $hasWorkedWith || $client->agency_id !== $agencyId) {
                return $this->sendError('Client not found.', [], 404);
            }

            $shortTermJobs = ShortTermJob::with(['dates', 'review' => fn ($q) => $q->where('candidate_id', $candidate->id)])
                ->where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->latest()
                ->get();

            $longTermJobs = LongTermJob::with(['schedules', 'review' => fn ($q) => $q->where('candidate_id', $candidate->id)])
                ->where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->latest()
                ->get();

            $client->image_url = $client->image ? asset('storage/'.$client->image) : null;

            return $this->sendResponse([
                'client' => $client,
                'short_term_jobs' => $shortTermJobs,
                'long_term_jobs' => $longTermJobs,
            ], 'Client details retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
