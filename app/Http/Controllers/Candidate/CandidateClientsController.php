<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Candidate\ClientDetailResource;
use App\Http\Resources\Candidate\ClientJobHistoryResource;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use App\Traits\ResolvesCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CandidateClientsController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/clients
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);
        $agencyId = $request->current_agency->id;

        $shortTermClientIds = ShortTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->pluck('client_id');

        $longTermClientIds = LongTermJob::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->pluck('client_id');

        $allClientIds = $shortTermClientIds->merge($longTermClientIds)->unique()->values();

        if ($request->filled('search') && ! $request->has('filter.search')) {
            $request->merge([
                'filter' => array_merge((array) $request->query('filter', []), [
                    'search' => $request->query('search'),
                ]),
            ]);
        }

        $query = QueryBuilder::for(Client::query()->whereIn('id', $allClientIds), $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = collect(is_array($value) ? $value : [$value])
                        ->map(fn (mixed $term): string => trim((string) $term))
                        ->filter()
                        ->implode(' ');

                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
                })
            );

        $clients = $query->paginate($request->integer('per_page', 10));
        $pageClientIds = $clients->getCollection()->pluck('id');

        $shortTermJobs = ShortTermJob::where('candidate_id', $candidate->id)
            ->whereIn('client_id', $pageClientIds)
            ->where('agency_id', $agencyId)
            ->latest()
            ->get()
            ->groupBy('client_id');

        $longTermJobs = LongTermJob::where('candidate_id', $candidate->id)
            ->whereIn('client_id', $pageClientIds)
            ->where('agency_id', $agencyId)
            ->latest()
            ->get()
            ->groupBy('client_id');

        $clients->getCollection()->transform(function (Client $client) use ($shortTermJobs, $longTermJobs) {
            $latestJob = $shortTermJobs->get($client->id, collect())
                ->concat($longTermJobs->get($client->id, collect()))
                ->sortByDesc('created_at')
                ->first();

            return $this->formatClientListItem($client, $latestJob);
        });

        return $this->sendResponse($clients, 'Clients retrieved successfully.', 200);
    }

    // GET /candidate/clients/{client}
    public function show(Request $request, Client $client): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);
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

        $jobHistory = $shortTermJobs->concat($longTermJobs)->sortByDesc('created_at')->values();

        return $this->sendResponse([
            'client' => new ClientDetailResource($client),
            'job_history' => ClientJobHistoryResource::collection($jobHistory),
        ], 'Client details retrieved successfully.', 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatClientListItem(Client $client, ShortTermJob|LongTermJob|null $latestJob): array
    {
        return [
            'id' => $client->id,
            'name' => trim($client->first_name.' '.$client->last_name),
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'mobile' => $client->mobile,
            'image_url' => $client->image_url,
            'job_type' => $latestJob ? ($latestJob instanceof ShortTermJob ? 'Short-Term Jobs' : 'Long-Term Jobs') : null,
            'job_status' => $latestJob?->status,
        ];
    }
}
