<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function __construct(private FormBuilderService $builder) {}

    /**
     * List the agency's builder forms.
     */
    public function index(Request $request)
    {
        $query = Form::where('agency_id', auth('api')->user()->agency_id);

        if ($request->filled('application_type')) {
            $query->where('application_type', $request->application_type);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('entity')) {
            $query->where('entity', $request->entity);
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $this->sendResponse($query->latest()->get(), 'Forms retrieved successfully');
    }

    /**
     * Create a dynamic builder form (client/candidate registration or long-term job posting).
     */
    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'application_type' => 'required|in:registration,job_posting',
            'user_type' => 'required_if:application_type,registration|in:client,candidate',
            'job_type' => 'required_if:application_type,job_posting|in:long_term',
            'schema' => 'nullable|array',
        ], $this->schemaRules()));

        $entity = $this->builder->resolveEntity(
            $request->application_type,
            $request->user_type,
            $request->job_type,
        );

        if (! $entity) {
            return $this->sendError('Unsupported form type', [], 422);
        }

        $form = Form::create([
            'agency_id' => $agencyId,
            'name' => $request->name,
            'slug' => $this->uniqueSlug($request->name, $agencyId),
            'entity' => $entity,
            'application_type' => $request->application_type,
            'user_type' => $request->application_type === 'registration' ? $request->user_type : null,
            'job_type' => $request->application_type === 'job_posting' ? $request->job_type : null,
            'schema' => $this->builder->normalizeSchema($request->input('schema', ['blocks' => []])),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Form created successfully',
            'data' => $form,
        ], 201);
    }

    /**
     * Show a builder form (with its full schema) by slug.
     */
    public function show($slug)
    {
        $form = Form::where('slug', $slug)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Form retrieved successfully',
            'data' => $form,
        ]);
    }

    /**
     * Update an existing builder form's name, schema or status.
     */
    public function update(Request $request, $id)
    {
        $form = Form::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        $request->validate(array_merge([
            'name' => 'sometimes|required|string|max:255',
            'schema' => 'sometimes|array',
            'status' => 'sometimes|boolean',
        ], $this->schemaRules()));

        if ($request->filled('name')) {
            $form->name = $request->name;
            $form->slug = $this->uniqueSlug($request->name, $form->agency_id, $form->id);
        }

        if ($request->has('schema')) {
            $form->schema = $this->builder->normalizeSchema($request->input('schema', ['blocks' => []]));
        }

        if ($request->has('status')) {
            $form->status = $request->boolean('status');
        }

        $form->save();

        return response()->json([
            'status' => true,
            'message' => 'Form updated successfully',
            'data' => $form,
        ]);
    }

    /**
     * Enable or disable a form. Sends the desired `status`, or toggles when omitted.
     * A disabled form stops accepting submissions.
     */
    public function toggleStatus(Request $request, $id)
    {
        $form = Form::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        $request->validate([
            'status' => 'sometimes|boolean',
        ]);

        $form->status = $request->has('status')
            ? $request->boolean('status')
            : ! $form->status;

        $form->save();

        return response()->json([
            'status' => true,
            'message' => $form->status ? 'Form enabled' : 'Form disabled',
            'data' => $form,
        ]);
    }

    /**
     * Submit a builder form: validate dynamically, create the target entity
     * (client, candidate or long-term job) and store the answers.
     */
    public function submit(Request $request, $slug)
    {
        $agencyId = auth('api')->user()->agency_id;

        $form = Form::where('slug', $slug)
            ->where('agency_id', $agencyId)
            ->firstOrFail();

        if (! $form->status) {
            return response()->json([
                'status' => false,
                'message' => 'This form is currently disabled',
            ], 422);
        }

        $entity = $form->entity;
        $modelClass = $this->builder->modelClassFor($entity);

        if (! $modelClass) {
            return response()->json([
                'status' => false,
                'message' => 'This form is not linked to a submittable entity',
            ], 422);
        }

        $schema = $form->schema ?? ['blocks' => []];

        $rules = $this->builder->validationRules($schema);
        $rules = array_merge($rules, $this->baseRules($entity));

        $request->validate($rules);

        $answers = $request->input('answers', []);

        DB::beginTransaction();

        try {
            $attributes = array_merge(
                $this->builder->requiredColumnDefaults($entity),
                $this->builder->mapAnswersToColumns($modelClass, $answers),
            );

            $attributes['agency_id'] = $agencyId;

            if ($entity === 'long_term_job') {
                $attributes['client_id'] = $request->input('client_id');
            }

            $record = $modelClass::create($attributes);

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'entity_id' => $record->id,
                'entity_type' => $entity,
                'data' => $answers,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Form submitted successfully',
                'data' => [
                    'submission_id' => $submission->id,
                    'entity' => $entity,
                    'record' => $record,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Base, entity-specific rules layered on top of the dynamic schema rules.
     *
     * @return array<string, string>
     */
    private function baseRules(string $entity): array
    {
        return match ($entity) {
            'client' => [
                'answers.first_name' => 'required|string|max:255',
                'answers.email' => 'required|email|unique:clients,email',
            ],
            'candidate' => [
                'answers.first_name' => 'required|string|max:255',
                'answers.email' => 'required|email|unique:candidates,email',
            ],
            'long_term_job' => [
                'client_id' => 'required|exists:clients,id',
                'answers.title' => 'required|string|max:255',
            ],
            default => [],
        };
    }

    /**
     * Generate a slug that is unique within the agency, optionally ignoring an existing form id.
     */
    private function uniqueSlug(string $name, int $agencyId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'form';
        $slug = $base;
        $suffix = 1;

        while (
            Form::where('agency_id', $agencyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Structural validation rules for the nested blocks -> sections -> fields builder definition.
     * Field "type" and "label" are required (mirroring the required markers in the builder UI),
     * and the type must be one the builder supports.
     *
     * @return array<string, mixed>
     */
    private function schemaRules(): array
    {
        return [
            'schema.blocks' => 'sometimes|array',
            'schema.blocks.*.name' => 'required|string|max:255',
            'schema.blocks.*.description' => 'nullable|string',
            'schema.blocks.*.sections' => 'nullable|array',
            'schema.blocks.*.sections.*.name' => 'nullable|string|max:255',
            'schema.blocks.*.sections.*.fields' => 'nullable|array',
            'schema.blocks.*.sections.*.fields.*.type' => ['required', Rule::in($this->builder->allowedFieldTypes())],
            'schema.blocks.*.sections.*.fields.*.label' => 'required|string|max:255',
            'schema.blocks.*.sections.*.fields.*.name' => 'nullable|string|max:255',
            'schema.blocks.*.sections.*.fields.*.placeholder' => 'nullable|string',
            'schema.blocks.*.sections.*.fields.*.is_required' => 'sometimes|boolean',
            'schema.blocks.*.sections.*.fields.*.width' => 'sometimes|integer|min:1|max:12',
            'schema.blocks.*.sections.*.fields.*.options' => 'nullable|array',
        ];
    }
}
