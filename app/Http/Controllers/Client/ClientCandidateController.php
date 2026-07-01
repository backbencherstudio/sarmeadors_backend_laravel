<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreCandidateReviewRequest;
use App\Http\Requests\Client\StoreHireRequestRequest;
use App\Http\Requests\Client\StoreInterviewRequestRequest;
use App\Http\Resources\Client\CandidateCardResource;
use App\Http\Resources\Client\CandidateDetailResource;
use App\Http\Resources\Client\CandidateReviewResource;
use App\Http\Resources\Client\HireRequestResource;
use App\Models\Candidate;
use App\Models\CandidateJobRequest;
use App\Models\ClientCandidate;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use App\Models\LongTermJobReview;
use App\Models\Payment;
use App\Models\ShortTermJob;
use App\Services\StripeService;
use App\Traits\FormatsTime;
use App\Traits\LinksClientCandidate;
use App\Traits\PresentsCandidate;
use App\Traits\ResolvesClient;
use App\Traits\SendsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientCandidateController extends Controller
{
    use FormatsTime;
    use LinksClientCandidate;
    use PresentsCandidate;
    use ResolvesClient;
    use SendsNotifications;

    public function __construct(
        private StripeService $stripeService
    ) {}

    // GET /client/candidates?tab=new|previous
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $candidateIds = $request->query('tab') === 'previous'
            ? $this->previousCandidateIds($client->id)
            : $this->newCandidateIds($client->id);

        $candidates = Candidate::with('reviews')
            ->where('agency_id', $request->current_agency->id)
            ->whereIn('id', $candidateIds)
            ->paginate(12);

        $this->attachCandidateLinks($client->id, $candidates->getCollection());

        return $this->sendResponse(
            CandidateCardResource::collection($candidates),
            'Candidates retrieved successfully.',
            200
        );
    }

    // GET /client/candidates/discover
    public function discover(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $candidates = Candidate::with('reviews')
            ->where('agency_id', $request->current_agency->id)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('search'));

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->paginate(12);

        return $this->sendResponse(
            CandidateCardResource::collection($candidates),
            'Candidates retrieved successfully.',
            200
        );
    }

    // GET /client/candidates/{candidate}
    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $candidate->load('reviews');

        $reviews = LongTermJobReview::with('client')
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id)
            ->latest()
            ->get();

        $myReview = $reviews->firstWhere('client_id', $client->id);

        $link = ClientCandidate::where('client_id', $client->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        return $this->sendResponse([
            'candidate' => new CandidateDetailResource($candidate),
            'reviews' => [
                'average' => $candidate->average_rating,
                'count' => $reviews->count(),
                'my_review' => $myReview ? new CandidateReviewResource($myReview) : null,
                'items' => CandidateReviewResource::collection($reviews),
            ],
            'application' => $this->latestApplicationContext($client->id, $candidate->id),
            'link' => $this->formatLink($link),
            'actions' => [
                'can_review' => true,
                'can_hire_request' => true,
            ],
        ], 'Candidate retrieved successfully.', 200);
    }

    // GET /client/candidates/{candidate}/reviews
    public function reviews(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $reviews = LongTermJobReview::with('client')
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id)
            ->latest()
            ->get();

        return $this->sendResponse([
            'average' => round((float) $reviews->avg('rating'), 1) ?: null,
            'count' => $reviews->count(),
            'my_review' => ($mine = $reviews->firstWhere('client_id', $client->id))
                ? new CandidateReviewResource($mine)
                : null,
            'items' => CandidateReviewResource::collection($reviews),
        ], 'Reviews retrieved successfully.', 200);
    }

    // POST /client/candidates/{candidate}/reviews
    public function storeReview(StoreCandidateReviewRequest $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validated();

        $existingJob = LongTermJob::where('client_id', $client->id)
            ->where('candidate_id', $candidate->id)
            ->latest()
            ->first();

        if (! $existingJob) {
            return $this->sendError('You can only review a candidate you have worked with on a long-term job.', [], 422);
        }

        $review = LongTermJobReview::updateOrCreate(
            [
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'long_term_job_id' => $existingJob->id,
            ],
            [
                'agency_id' => $request->current_agency->id,
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        return $this->sendResponse(
            new CandidateReviewResource($review->load('client')),
            'Review submitted.',
            201
        );
    }

    // POST /client/candidates/{candidate}/hire-request
    // Posts a private short-term job for a specific candidate and sends them a
    // pending request they can approve/reject from their Requested Jobs page.
    // Long-term hires go through the interview flow first.
    public function hireRequest(StoreHireRequestRequest $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validated();

        if ($validated['job_type'] === 'long-term') {
            return $this->sendError('Long-term hires require scheduling an interview first.', [], 422);
        }

        $agency = $request->current_agency;
        $paymentRequired = (bool) $agency->short_term_payment_required
            && $agency->hasStripeKeys()
            && $agency->short_term_job_fee > 0;

        if ($paymentRequired && empty($validated['payment_method_id'])) {
            return $this->sendError('Payment information is required to send this hire request.', [], 422);
        }

        $fee = $paymentRequired ? (float) $agency->short_term_job_fee : 0.0;
        $feeCurrency = $agency->short_term_job_fee_currency ?? 'usd';
        $savePaymentMethod = (bool) ($validated['save_payment_method'] ?? false);
        $paymentIntentId = null;
        $savedPaymentMethod = null;

        // Charge the agency fee up front — nothing is created if it fails.
        if ($paymentRequired) {
            try {
                $customerId = $this->stripeService->ensureCustomer($client, $agency);

                $intent = $this->stripeService->createJobPaymentIntent(
                    $client,
                    $agency,
                    null,
                    $fee,
                    $feeCurrency,
                    $customerId,
                    $savePaymentMethod
                );

                $confirmed = $this->stripeService->confirmPaymentIntent($agency, $intent->id, $validated['payment_method_id']);

                if ($confirmed->status !== 'succeeded') {
                    return $this->sendError('Payment failed. Status: '.$confirmed->status, [], 422);
                }

                $paymentIntentId = $confirmed->id;

                if ($savePaymentMethod) {
                    $savedPaymentMethod = $this->stripeService->storePaymentMethod(
                        $client,
                        $agency,
                        $validated['payment_method_id'],
                        $validated['cardholder_name'] ?? null
                    );
                }
            } catch (\Throwable $e) {
                return $this->sendError('Payment could not be processed.', $e->getMessage(), 422);
            }
        }

        $jobRequest = DB::transaction(function () use ($agency, $client, $candidate, $validated, $paymentRequired, $fee, $feeCurrency, $paymentIntentId, $savedPaymentMethod): CandidateJobRequest {
            $job = ShortTermJob::create([
                'agency_id' => $agency->id,
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'location_id' => $validated['location_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'job_address' => $validated['job_address'],
                'home_city' => $validated['home_city'],
                'home_province' => $validated['home_province'],
                'home_postal_code' => $validated['home_postal_code'],
                'country' => $validated['country'],
                'compensation_amount' => $validated['compensation_amount'],
                'compensation_currency' => $validated['compensation_currency'] ?? 'usd',
                'compensation_type' => $validated['compensation_type'] ?? 'per_hour',
                'status' => 'pending_approval',
                'stripe_payment_intent_id' => $paymentIntentId,
            ]);

            foreach ($validated['dates'] as $date) {
                $job->dates()->create($date);
            }

            Payment::create([
                'agency_id' => $agency->id,
                'client_id' => $client->id,
                'short_term_job_id' => $job->id,
                'client_payment_method_id' => $savedPaymentMethod?->id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'cardholder_name' => $validated['cardholder_name'] ?? null,
                'billing_country' => $validated['billing_country'] ?? null,
                'billing_postal_code' => $validated['billing_postal_code'] ?? null,
                'amount' => $fee,
                'tax' => 0,
                'currency' => $feeCurrency,
                'note' => $validated['note'] ?? null,
                'status' => $paymentRequired ? 'succeeded' : 'pending',
            ]);

            $this->linkClientCandidate($client, $candidate->id, 'hired', $validated['note'] ?? null);

            return CandidateJobRequest::create([
                'agency_id' => $agency->id,
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'short_term_job_id' => $job->id,
                'job_type' => 'short_term',
                'status' => 'pending',
                'message' => $validated['note'] ?? null,
            ]);
        });

        return $this->sendResponse(
            new HireRequestResource($jobRequest->load(['candidate', 'shortTermJob'])),
            'Hire request sent to candidate.',
            201
        );
    }

    // POST /client/candidates/{candidate}/interview-request
    // Long-term hires schedule an interview straight from the candidate's
    // profile. It is persisted (no posted job yet) so both the candidate and
    // the agency see it; admin posts the long-term job after the interview.
    public function interviewRequest(StoreInterviewRequestRequest $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validated();

        if ($validated['job_type'] !== 'long-term') {
            return $this->sendError('Only long-term hires schedule an interview. Short-term hires use the hire request flow.', [], 422);
        }

        $interview = DB::transaction(function () use ($request, $client, $candidate, $validated): LongTermJobInterview {
            $interview = LongTermJobInterview::create([
                'agency_id' => $request->current_agency->id,
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'scheduled_date' => $validated['scheduled_date'],
                'available_from' => $validated['available_from'],
                'available_to' => $validated['available_to'],
                'interview_type' => $validated['interview_type'],
                'description' => $validated['description'] ?? null,
                'special_note' => $validated['special_note'] ?? null,
                // Awaiting the candidate's response; the agency issues the link on confirmation.
                'status' => 'requested',
            ]);

            $this->linkClientCandidate($client, $candidate->id, 'interview_scheduled');

            return $interview;
        });

        $body = sprintf(
            '%s requested a %s interview with %s on %s (%s).',
            trim($client->first_name.' '.$client->last_name),
            str_replace('_', ' ', $interview->interview_type),
            $this->candidateFullName($candidate),
            $interview->scheduled_date?->format('M d, Y'),
            $this->formatTime($interview->available_from).' - '.$this->formatTime($interview->available_to)
        );
        $meta = [
            'interview_id' => $interview->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
        ];

        $this->notifyAgencyAdmins($request->current_agency->id, 'interview_requested', 'Interview Requested', $body, null, $meta);
        $this->notifyPortalUser($request->current_agency->id, $candidate->email, 'interview_requested', 'Interview Requested', $body, null, $meta);

        return $this->sendResponse($this->formatInterviewResponse($interview, $candidate), 'Interview request sent to the candidate.', 201);
    }

    // PUT /client/candidates/{candidate}/link
    // Update the My Candidates link (status / notes) for a candidate.
    public function updateLink(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'in:interested,interview_scheduled,hired,declined'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $link = $this->linkClientCandidate(
            $client,
            $candidate->id,
            $validated['status'] ?? null,
            $validated['notes'] ?? null
        );

        return $this->sendResponse($this->formatLink($link), 'Candidate link updated.', 200);
    }

    /**
     * Attach each candidate's "My Candidates" link (status / notes / linked_at)
     * so the card resource can present it without an extra query per row.
     *
     * @param  Collection<int, Candidate>  $candidates
     */
    private function attachCandidateLinks(int $clientId, Collection $candidates): void
    {
        $links = ClientCandidate::where('client_id', $clientId)
            ->whereIn('candidate_id', $candidates->pluck('id'))
            ->get()
            ->keyBy('candidate_id');

        $candidates->each(function (Candidate $candidate) use ($links): void {
            $candidate->setRelation('clientLink', $links->get($candidate->id));
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatLink(?ClientCandidate $link): ?array
    {
        if (! $link) {
            return null;
        }

        return [
            'candidate_id' => $link->candidate_id,
            'status' => $link->status,
            'status_label' => Str::headline($link->status),
            'notes' => $link->notes,
            'linked_at' => $link->linked_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInterviewResponse(LongTermJobInterview $interview, Candidate $candidate): array
    {
        return [
            'id' => $interview->id,
            'candidate' => [
                'id' => $candidate->id,
                'name' => $this->candidateFullName($candidate),
                'image_url' => $candidate->image_url,
            ],
            'job_type' => 'long-term',
            'interview_type' => $interview->interview_type,
            'description' => $interview->description,
            'special_note' => $interview->special_note,
            'scheduled_date' => $interview->scheduled_date?->toDateString(),
            'available_from' => $this->formatTime($interview->available_from),
            'available_to' => $this->formatTime($interview->available_to),
            'status' => $interview->status,
            'requested_at' => $interview->created_at?->toIso8601String(),
        ];
    }

    /**
     * Candidate ids who applied to the client's active long-term jobs.
     *
     * @return Collection<int, int>
     */
    private function newCandidateIds(int $clientId): Collection
    {
        $jobIds = LongTermJob::where('client_id', $clientId)->pluck('id');

        return LongTermJobApplication::whereIn('long_term_job_id', $jobIds)
            ->pluck('candidate_id')
            ->unique()
            ->values();
    }

    /**
     * Candidate ids the client has previously completed jobs with.
     *
     * @return Collection<int, int>
     */
    private function previousCandidateIds(int $clientId): Collection
    {
        return LongTermJob::where('client_id', $clientId)
            ->where('status', 'completed')
            ->whereNotNull('candidate_id')
            ->pluck('candidate_id')
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestApplicationContext(int $clientId, int $candidateId): ?array
    {
        $application = LongTermJobApplication::whereHas('job', fn (Builder $query) => $query->where('client_id', $clientId))
            ->where('candidate_id', $candidateId)
            ->with(['job', 'interview'])
            ->latest()
            ->first();

        if (! $application) {
            return null;
        }

        $interview = $application->interview;

        return [
            'id' => $application->id,
            'status' => $application->status,
            'job' => [
                'id' => $application->job?->id,
                'title' => $application->job?->title,
            ],
            'interview' => $interview ? [
                'id' => $interview->id,
                'status' => $interview->status,
                'scheduled_date' => $interview->scheduled_date?->toDateString(),
                'type' => $interview->interview_type,
                'link' => $interview->interview_link,
            ] : null,
        ];
    }
}
