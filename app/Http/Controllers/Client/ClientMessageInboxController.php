<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use App\Traits\BuildsMessageConversations;
use App\Traits\ResolvesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientMessageInboxController extends Controller
{
    use BuildsMessageConversations;
    use ResolvesClient;

    // GET /client/messages
    // tab=admin | tab=candidate
    public function index(Request $request): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        $tab = $request->query('tab', 'admin');
        $search = trim((string) $request->query('search', ''));

        $longTermJobs = LongTermJob::with('candidate')->where('client_id', $client->id)->get();
        $shortTermJobs = ShortTermJob::where('client_id', $client->id)->get();

        $agencyCounterpart = fn (): array => [
            'name' => $request->current_agency->name,
            'image_url' => null,
        ];

        $adminConversations = $this->sortConversationsByActivity(
            $this->conversationsForJobs($request, $longTermJobs, 'long_term', 'client', $agencyCounterpart)
                ->concat($this->conversationsForJobs($request, $shortTermJobs, 'short_term', 'client', $agencyCounterpart))
        );

        $candidateConversations = $this->sortConversationsByActivity(
            $this->conversationsForJobs(
                $request,
                $longTermJobs->whereNotNull('candidate_id')->values(),
                'long_term',
                'client_candidate',
                fn (LongTermJob $job): array => [
                    'name' => trim(($job->candidate?->first_name ?? '').' '.($job->candidate?->last_name ?? '')) ?: null,
                    'image_url' => $job->candidate?->image_url,
                ]
            )
        );

        $selected = $tab === 'candidate' ? $candidateConversations : $adminConversations;

        return $this->sendResponse([
            'tab' => $tab === 'candidate' ? 'candidate' : 'admin',
            'tabs' => [
                'admin' => $this->tabSummary($adminConversations),
                'candidate' => $this->tabSummary($candidateConversations),
            ],
            'conversations' => $this->filterConversations($selected, $search),
        ], 'Conversations retrieved successfully.', 200);
    }
}
