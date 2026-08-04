<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Services\FormBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LongTermJobController extends Controller
{
    public function __construct(private FormBuilderService $builder) {}

    /**
     * Agency list of long-term job requests (card feed for "Requested Long Term Jobs").
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'client_id' => 'nullable|integer|exists:clients,id',
                'status' => 'nullable|string',
                'search' => 'nullable|string|max:255',
            ]);

            $agency = $request->current_agency;
            $clientId = $request->query('client_id');
            $status = $request->query('status');
            $search = $request->query('search');

            $query = LongTermJob::with(['client', 'formSubmission.form', 'location'])
                ->where('agency_id', $agency->id);

            if ($clientId) {
                $query->where('client_id', $clientId);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            }

            $jobs = $query->latest()->get()->map(fn (LongTermJob $job) => $this->formatRequestCard($job));

            $countsQuery = LongTermJob::where('agency_id', $agency->id);
            if ($clientId) {
                $countsQuery->where('client_id', $clientId);
            }

            $counts = $countsQuery
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->sendResponse([
                'counts' => $counts,
                'total' => $jobs->count(),
                'jobs' => $jobs,
            ], 'Jobs retrieved successfully', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Requested job details: sidebar client summary + schema blocks with answers.
     */
    public function show(LongTermJob $longTermJob, Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            $blockSlug = $request->query('block');

            $longTermJob->load(['client', 'candidate', 'schedules', 'children', 'location', 'formSubmission.form']);

            $submission = $longTermJob->formSubmission;
            $answers = (array) ($submission?->data ?? []);
            $schema = $submission?->form?->schema ?? ['blocks' => []];

            $allBlocks = $this->builder->submissionBlocks($schema, $answers, $agency->id);
            $blockTabs = collect($allBlocks)
                ->map(fn ($block) => [
                    'name' => $block['name'],
                    'slug' => $block['slug'],
                    'description' => $block['description'],
                    'service_id' => $block['service_id'],
                ])
                ->values()
                ->all();

            $blocks = $blockSlug
                ? collect($allBlocks)->where('slug', $blockSlug)->values()->all()
                : $allBlocks;

            return $this->sendResponse([
                'job' => [
                    'id' => $longTermJob->id,
                    'title' => $longTermJob->title,
                    'status' => $longTermJob->status,
                    'status_label' => $this->statusLabel($longTermJob->status),
                    'rejection_reason' => $longTermJob->rejection_reason,
                    'submitted_at' => optional($submission?->created_at ?? $longTermJob->created_at)?->toIso8601String(),
                    'in_status_since' => $this->inStatusSince($longTermJob),
                    'actions' => [
                        'can_publish' => $longTermJob->status === 'pending_approval',
                        'can_reject' => $longTermJob->status === 'pending_approval',
                    ],
                ],
                'client' => $this->formatClientSidebar($longTermJob),
                'block_tabs' => $blockTabs,
                'blocks' => $blocks,
                'answers' => $answers,
                'form' => $submission?->form ? [
                    'id' => $submission->form->id,
                    'name' => $submission->form->name,
                    'slug' => $submission->form->slug,
                ] : null,
            ], 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function approve(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'pending_approval') {
                return $this->sendError('Only pending jobs can be approved.', [], 422);
            }

            $longTermJob->update(['status' => 'marketplace']);

            return $this->sendResponse($longTermJob->fresh(), 'Job approved and moved to marketplace.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function complete(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'running') {
                return $this->sendError('Only running jobs can be marked as completed.', [], 422);
            }

            $longTermJob->update(['status' => 'completed']);

            return $this->sendResponse($longTermJob->fresh(), 'Job marked as completed.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function reject(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            if ($longTermJob->agency_id !== $agency->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'pending_approval') {
                return $this->sendError('Only pending jobs can be rejected.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $longTermJob->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['reason'],
            ]);

            return $this->sendResponse($longTermJob->fresh(), 'Job rejected.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRequestCard(LongTermJob $job): array
    {
        $client = $job->client;
        $answers = (array) ($job->formSubmission?->data ?? []);
        $description = $job->description
            ?? ($answers['description'] ?? null)
            ?? ($answers['backup_reason'] ?? null);

        $location = $answers['location']
            ?? collect([
                $job->job_address,
                $job->home_city,
                $job->home_province,
                $job->home_postal_code,
            ])->filter()->implode(', ');

        $rate = null;
        if ($job->compensation_amount) {
            $rate = trim(($job->compensation_currency ?: '$').' '.$job->compensation_amount.'/hr');
        } elseif (isset($answers['hourly_rate'])) {
            $rate = '$ '.$answers['hourly_rate'].'/hr';
        } elseif (is_array($answers['salary'] ?? null)) {
            $salary = $answers['salary'];
            $rate = trim(($salary['currency'] ?? '$').' '.($salary['min'] ?? '').'-'.($salary['max'] ?? ''));
        }

        return [
            'id' => $job->id,
            'title' => $job->title,
            'description_preview' => $description ? Str::limit(strip_tags((string) $description), 140) : null,
            'location' => $location ?: null,
            'rate_label' => $rate,
            'status' => $job->status,
            'status_label' => $this->statusLabel($job->status),
            'submitted_at' => optional($job->formSubmission?->created_at ?? $job->created_at)?->toIso8601String(),
            'client' => [
                'id' => $client?->id,
                'name' => trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: null,
                'email' => $client?->email,
                'mobile' => $client?->mobile,
                'image_url' => $client?->image_url,
            ],
            'selected_services' => $answers['selected_services'] ?? [],
            'actions' => [
                'can_publish' => $job->status === 'pending_approval',
                'can_view_details' => true,
                'can_reject' => $job->status === 'pending_approval',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatClientSidebar(LongTermJob $job): array
    {
        $client = $job->client;
        $submittedAt = $job->formSubmission?->created_at ?? $job->created_at;

        return [
            'id' => $client?->id,
            'name' => trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: null,
            'email' => $client?->email,
            'mobile' => $client?->mobile,
            'image_url' => $client?->image_url,
            'submitted_at' => optional($submittedAt)?->toIso8601String(),
            'submitted_at_label' => optional($submittedAt)?->format('D M j Y'),
            'in_status_since' => $this->inStatusSince($job),
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending_approval' => 'Pending Review',
            'marketplace' => 'Application Approved',
            'running' => 'Running',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            default => $status ? Str::headline($status) : 'Pending',
        };
    }

    /**
     * @return array{label: string, since: string|null}
     */
    private function inStatusSince(LongTermJob $job): array
    {
        $since = $job->updated_at ?? $job->created_at;

        return [
            'since' => optional($since)?->toIso8601String(),
            'label' => $since
                ? 'In status since '.$since->format('j M Y').' ('.$since->diffForHumans(null, true).')'
                : null,
        ];
    }
}
