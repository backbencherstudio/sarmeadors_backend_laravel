<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\FormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    /**
     * Entities that self-register with their own linked user account and are
     * subject to an agency-wide capacity limit, mapped to the `agencies`
     * columns tracking that limit.
     *
     * @var array<string, array{max: string, total: string}>
     */
    private const AGENCY_CAPACITY_COLUMNS = [
        'client' => ['max' => 'max_clients', 'total' => 'total_clients'],
        'candidate' => ['max' => 'max_candidates', 'total' => 'total_candidates'],
    ];

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

        return $this->sendResponse($form, 'Form created successfully', 201);
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
     * Public: fetch an enabled builder form's schema by slug, scoped by the
     * `X-Subdomain` agency, so an unauthenticated visitor (client/candidate
     * applying on their own) can see what fields to fill in before submitting.
     */
    public function publicShow(Request $request, $slug)
    {
        $form = Form::where('slug', $slug)
            ->where('agency_id', $request->current_agency->id)
            ->where('status', true)
            ->firstOrFail();

        $agency = $request->current_agency;

        $admin = User::where('agency_id', $agency->id)
            ->where('is_owner', 1)
            ->first();

        $data = $form->toArray();
        $data['base_fields'] = $this->builder->baseFields($form->entity);
        $data['agency'] = [
            'id' => $agency->id,
            'name' => $agency->name,
            'logo' => $agency->logo,
            'email' => $agency->email,
            'mobile' => $agency->mobile,
            'website' => $agency->website,
            'admin_name' => $admin ? trim($admin->first_name.' '.$admin->last_name) : null,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Form retrieved successfully',
            'data' => $data,
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
     * Move a schema block to a new position (1-based `serial`) within the
     * form, renumbering the rest of the schema to match. Blocks are
     * identified by their `key` since they aren't separate rows.
     */
    public function reorderBlock(Request $request, $id, $key)
    {
        $form = Form::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        $request->validate([
            'serial' => 'required|integer|min:1',
        ]);

        $schema = $this->builder->moveBlock($form->schema ?? ['blocks' => []], $key, (int) $request->serial);

        if ($schema === null) {
            return response()->json([
                'status' => false,
                'message' => 'Block not found in this form',
            ], 404);
        }

        $form->schema = $schema;
        $form->save();

        return response()->json([
            'status' => true,
            'message' => 'Block reordered successfully',
            'data' => $form,
        ]);
    }

    /**
     * Move a schema field to a new position (1-based `serial`) within its
     * own section, renumbering the rest of that section to match. Fields
     * are identified by their `key` since they aren't separate rows.
     */
    public function reorderField(Request $request, $id, $key)
    {
        $form = Form::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        $request->validate([
            'serial' => 'required|integer|min:1',
        ]);

        $schema = $this->builder->moveField($form->schema ?? ['blocks' => []], $key, (int) $request->serial);

        if ($schema === null) {
            return response()->json([
                'status' => false,
                'message' => 'Field not found in this form',
            ], 404);
        }

        $form->schema = $schema;
        $form->save();

        return response()->json([
            'status' => true,
            'message' => 'Field reordered successfully',
            'data' => $form,
        ]);
    }

    /**
     * Move a schema section to a new position (1-based `serial`) within its
     * own block, renumbering the rest of that block to match. Sections are
     * identified by their `key` since they aren't separate rows.
     */
    public function reorderSection(Request $request, $id, $key)
    {
        $form = Form::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        $request->validate([
            'serial' => 'required|integer|min:1',
        ]);

        $schema = $this->builder->moveSection($form->schema ?? ['blocks' => []], $key, (int) $request->serial);

        if ($schema === null) {
            return response()->json([
                'status' => false,
                'message' => 'Section not found in this form',
            ], 404);
        }

        $form->schema = $schema;
        $form->save();

        return response()->json([
            'status' => true,
            'message' => 'Section reordered successfully',
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
        $agencyId = $request->current_agency->id;

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
        $rules = array_merge($rules, $this->baseRules($entity, $agencyId));

        $request->validate($rules);

        $answers = $request->input('answers', []);
        $answers = $this->builder->storeFileAnswers($request, $entity, $schema, $answers);

        DB::beginTransaction();

        try {
            $agency = null;
            $capacityColumns = self::AGENCY_CAPACITY_COLUMNS[$entity] ?? null;

            if ($capacityColumns) {
                $agency = Agency::where('id', $agencyId)->lockForUpdate()->first();

                if ($agency && $agency->{$capacityColumns['total']} >= $agency->{$capacityColumns['max']}) {
                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => ucfirst($entity).' limit exceeded for this agency.',
                    ], 403);
                }
            }

            $attributes = array_merge(
                $this->builder->requiredColumnDefaults($entity),
                $this->builder->mapAnswersToColumns($modelClass, $entity, $schema, $answers),
            );

            $attributes['agency_id'] = $agencyId;

            if ($entity === 'long_term_job') {
                $attributes['client_id'] = $request->input('client_id');
            }

            $record = $modelClass::create($attributes);

            if ($capacityColumns) {
                if ($agency) {
                    $agency->increment($capacityColumns['total']);
                }

                if (! empty($answers['password'])) {
                    $record->user?->update(['password' => $answers['password']]);
                }
            }

            $baseFieldNames = collect($this->builder->baseFields($entity))->pluck('name');

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'entity_id' => $record->id,
                'entity_type' => $entity,
                'data' => collect($answers)->except($baseFieldNames)->all(),
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
    private function baseRules(string $entity, int $agencyId): array
    {
        return match ($entity) {
            'client' => [
                'answers.first_name' => 'required|string|max:255',
                'answers.email' => 'required|email|unique:clients,email',
                'answers.password' => ['nullable', 'string', 'min:8', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/'],
                'answers.image' => 'nullable|file|mimes:jpeg,jpg,png,gif|max:10240',
                'answers.type_id' => 'nullable|array',
                'answers.type_id.*' => [
                    'integer',
                    Rule::exists('types', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'client')),
                ],
                'answers.location_id' => 'nullable|array',
                'answers.location_id.*' => [
                    'integer',
                    Rule::exists('locations', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)),
                ],
            ],
            'candidate' => [
                'answers.first_name' => 'required|string|max:255',
                'answers.email' => 'required|email|unique:candidates,email',
                'answers.password' => ['nullable', 'string', 'min:8', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/'],
                'answers.image' => 'nullable|file|mimes:jpeg,jpg,png,gif|max:10240',
                'answers.type_id' => 'nullable|array',
                'answers.type_id.*' => [
                    'integer',
                    Rule::exists('types', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'candidate')),
                ],
                'answers.location_id' => 'nullable|array',
                'answers.location_id.*' => [
                    'integer',
                    Rule::exists('locations', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)),
                ],
            ],
            'long_term_job' => [
                'client_id' => [
                    'required',
                    Rule::exists('clients', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)),
                ],
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
