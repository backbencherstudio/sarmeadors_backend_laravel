<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use App\Traits\BuildsMessageConversations;
use App\Traits\ResolvesCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateMessageInboxController extends Controller
{
    use BuildsMessageConversations;
    use ResolvesCandidate;

    // GET /candidate/messages
    // tab=admin | tab=client
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $tab = $request->query('tab', 'admin');
        $search = trim((string) $request->query('search', ''));

        $longTermJobs = LongTermJob::with('client')->where('candidate_id', $candidate->id)->get();
        $shortTermJobs = ShortTermJob::where('candidate_id', $candidate->id)->get();

        $agencyCounterpart = fn (): array => [
            'name' => $request->current_agency->name,
            'image_url' => null,
        ];

        $adminConversations = $this->sortConversationsByActivity(
            $this->conversationsForJobs($request, $longTermJobs, 'long_term', 'candidate', $agencyCounterpart)
                ->concat($this->conversationsForJobs($request, $shortTermJobs, 'short_term', 'candidate', $agencyCounterpart))
        );

        $clientConversations = $this->sortConversationsByActivity(
            $this->conversationsForJobs(
                $request,
                $longTermJobs,
                'long_term',
                'client_candidate',
                fn (LongTermJob $job): array => [
                    'name' => trim(($job->client?->first_name ?? '').' '.($job->client?->last_name ?? '')) ?: null,
                    'image_url' => $job->client?->image_url,
                ]
            )
        );

        $selected = $tab === 'client' ? $clientConversations : $adminConversations;

        return $this->sendResponse([
            'tab' => $tab === 'client' ? 'client' : 'admin',
            'tabs' => [
                'admin' => $this->tabSummary($adminConversations),
                'client' => $this->tabSummary($clientConversations),
            ],
            'conversations' => $this->filterConversations($selected, $search),
        ], 'Conversations retrieved successfully.', 200);
    }
}
