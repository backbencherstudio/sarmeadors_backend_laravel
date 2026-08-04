<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientFormController extends Controller
{
    public function __construct(private FormBuilderService $builder) {}

    /**
     * List the agency's enabled long-term job posting forms a client can fill.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $forms = Form::query()
            ->where('agency_id', $client->agency_id)
            ->where('status', true)
            ->where('application_type', 'job_posting')
            ->where('job_type', 'long_term')
            ->latest()
            ->get(['id', 'name', 'slug', 'entity', 'application_type', 'job_type', 'status', 'created_at', 'updated_at']);

        return $this->sendResponse($forms, 'Forms retrieved successfully');
    }

    /**
     * Show one enabled long-term job form schema (options hydrated) for the client.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $form = $this->findEnabledLongTermJobForm($client->agency_id, $slug);

        if (! $form) {
            return $this->sendError('Form not found.', [], 404);
        }

        $data = $form->toArray();
        $data['schema'] = $this->builder->presentSchema(
            $data['schema'] ?? ['blocks' => []],
            $client->agency_id,
        );

        return $this->sendResponse($data, 'Form retrieved successfully');
    }

    /**
     * Client submits a long-term job posting form. Creates a LongTermJob in
     * pending_approval so the agency admin can approve or reject with a reason.
     */
    public function submit(Request $request, string $slug): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $form = $this->findEnabledLongTermJobForm($client->agency_id, $slug);

        if (! $form) {
            return $this->sendError('Form not found.', [], 404);
        }

        $schema = $form->schema ?? ['blocks' => []];
        $agencyId = $client->agency_id;

        $rules = $this->builder->validationRules($schema, $agencyId);
        $rules = array_merge($rules, [
            'answers.title' => 'required|string|max:255',
        ]);

        $request->validate($rules);

        $answers = $request->input('answers', []);
        $answers = $this->builder->storeFileAnswers($request, 'long_term_job', $schema, $answers);

        DB::beginTransaction();

        try {
            $modelClass = $this->builder->modelClassFor('long_term_job');

            $attributes = array_merge(
                $this->builder->requiredColumnDefaults('long_term_job'),
                $this->builder->mapAnswersToColumns($modelClass, 'long_term_job', $schema, $answers),
            );

            $attributes['agency_id'] = $agencyId;
            $attributes['client_id'] = $client->id;
            $attributes['status'] = 'pending_approval';
            $attributes['rejection_reason'] = null;

            $job = $modelClass::create($attributes);

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'entity_id' => $job->id,
                'entity_type' => 'long_term_job',
                'data' => $answers,
            ]);

            DB::commit();

            return $this->sendResponse([
                'submission_id' => $submission->id,
                'entity' => 'long_term_job',
                'status' => $job->status,
                'record' => $job,
            ], 'Form submitted successfully. Waiting for agency approval.', 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->sendError('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();
        $agency = $request->current_agency;

        return Client::where('email', $user->email)
            ->where('agency_id', $agency->id)
            ->first();
    }

    private function findEnabledLongTermJobForm(int $agencyId, string $slug): ?Form
    {
        return Form::query()
            ->where('agency_id', $agencyId)
            ->where('slug', $slug)
            ->where('status', true)
            ->where('application_type', 'job_posting')
            ->where('job_type', 'long_term')
            ->first();
    }
}
