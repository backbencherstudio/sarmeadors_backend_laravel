<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyCandidateGlobalSetting;
use App\Models\Candidate;
use App\Models\CheckList;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use App\Models\Location;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use App\Traits\PresentsCandidate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CandidateController extends Controller
{
    use PresentsCandidate;

    /**
     * Map global-setting column keys to actual `candidates` table columns.
     * Keys with no real, directly sortable/searchable column (e.g. JSON arrays
     * or data we don't track yet) are omitted here and handled in index().
     *
     * @var array<string,string>
     */
    private array $columnMap = [
        'name' => 'first_name',
        'email_address' => 'email',
        'phone_number' => 'mobile',
        'registration_date' => 'created_at',
        'status' => 'status_id',
        'hear_about_us' => 'hear_about_us',
    ];

    public function index(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $dashboard = AgencyCandidateGlobalSetting::where('agency_id', $agencyId)
            ->value('settings')['dashboard'] ?? [];

        $tableFields = $dashboard['table_fields'] ?? ['name', 'email_address', 'phone_number', 'registration_date', 'status'];
        $displayLabels = $dashboard['display_labels'] ?? [];
        $quickSearchField = $dashboard['quick_search_field'] ?? null;
        $defaultSortField = $dashboard['default_sort_field'] ?? 'registration_date';

        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $quickSearch = $request->query('quick_search');
        $sortField = $request->query('sort_field', $defaultSortField);
        $sortDirection = $request->query('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Candidate::where('agency_id', $agencyId);

        // Fixed "Search by Name, Email or Phone Number" box: always checks all
        // three, regardless of the agency's configured `quick_search_field`.
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Configurable "Search for keyword/phrase anywhere on user profile" box:
        // honors the agency's `quick_search_field` table setting, falling back to
        // the same broad name/email/phone match when no field is configured.
        if ($quickSearch) {
            if ($quickSearchField === 'name') {
                $query->where(function ($q) use ($quickSearch) {
                    $q->where('first_name', 'like', "%{$quickSearch}%")
                        ->orWhere('last_name', 'like', "%{$quickSearch}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$quickSearch}%"]);
                });
            } else {
                $searchColumn = $this->columnMap[$quickSearchField] ?? null;

                if ($searchColumn && $searchColumn !== 'status_id') {
                    $query->where($searchColumn, 'like', "%{$quickSearch}%");
                } else {
                    $query->where(function ($q) use ($quickSearch) {
                        $q->where('first_name', 'like', "%{$quickSearch}%")
                            ->orWhere('last_name', 'like', "%{$quickSearch}%")
                            ->orWhere('email', 'like', "%{$quickSearch}%")
                            ->orWhere('mobile', 'like', "%{$quickSearch}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$quickSearch}%"]);
                    });
                }
            }
        }

        $this->applyJsonInFilter($query, 'type_id', $this->normalizeIdList($request->query('type_ids')));
        $this->applyJsonInFilter($query, 'location_id', $this->normalizeIdList($request->query('location_ids')));
        $this->applyJsonInFilter($query, 'status_id', $this->normalizeIdList($request->query('status_ids')));

        foreach ($this->normalizeIdList($request->query('hide_status_ids')) as $hiddenStatusId) {
            $query->whereJsonDoesntContain('status_id', $hiddenStatusId);
        }

        $sortColumn = $this->columnMap[$sortField] ?? 'created_at';

        if ($sortColumn === 'status_id') {
            $sortColumn = 'created_at';
        }

        $candidates = $query->orderBy($sortColumn, $sortDirection)->paginate($perPage);

        $statusesById = Status::where('agency_id', $agencyId)
            ->where('type', 'candidate')
            ->get()
            ->keyBy('id');

        $rows = $candidates->getCollection()->map(function ($candidate) use ($tableFields, $statusesById) {
            $row = ['id' => $candidate->id, 'image_url' => $candidate->image_url];

            foreach ($tableFields as $field) {
                $row[$field] = match ($field) {
                    'name' => $candidate->full_name,
                    'email_address' => $candidate->email,
                    'phone_number' => $candidate->mobile,
                    'registration_date' => $candidate->created_at?->format('n/j/y'),
                    'status' => $this->resolveStatus($candidate->status_id, $statusesById),
                    'position_applying_for' => $this->candidateRoleNames($candidate),
                    'locations' => $this->candidateLocationLabel($candidate),
                    // Candidates have no authentication/session tracking yet, so this is
                    // always null until a "last login" mechanism exists for them.
                    'last_login' => null,
                    default => $candidate->{$field} ?? null,
                };
            }

            $row['type'] = $this->candidateRoleNames($candidate);
            $row['location'] = $this->candidateLocationLabel($candidate);

            return $row;
        })->values();

        $columns = collect($tableFields)->map(fn ($field) => [
            'key' => $field,
            'label' => $displayLabels[$field] ?? Str::title(str_replace('_', ' ', $field)),
        ])->values();

        return response()->json([
            'status' => true,
            'message' => 'Candidates retrieved successfully',
            'columns' => $columns,
            'data' => $rows,
            'meta' => [
                'current_page' => $candidates->currentPage(),
                'last_page' => $candidates->lastPage(),
                'per_page' => $candidates->perPage(),
                'total' => $candidates->total(),
                'next_page_url' => $candidates->nextPageUrl(),
                'prev_page_url' => $candidates->previousPageUrl(),
            ],
        ]);
    }

    private function resolveStatus(?array $statusIds, $statusesById): ?array
    {
        if (empty($statusIds)) {
            return null;
        }

        $status = $statusesById->get($statusIds[0]);

        if (! $status) {
            return null;
        }

        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
        ];
    }

    /**
     * Normalize a `type_ids`/`location_ids`/`status_ids` query value (comma-separated
     * string or array) into a list of unique integer ids.
     *
     * @return array<int,int>
     */
    private function normalizeIdList(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect($value)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Restrict the query to rows whose JSON array column contains at least
     * one of the given ids (OR semantics), when ids are provided.
     *
     * @param  array<int,int>  $ids
     */
    private function applyJsonInFilter($query, string $column, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $query->where(function ($q) use ($column, $ids) {
            foreach ($ids as $id) {
                $q->orWhereJsonContains($column, $id);
            }
        });
    }

    public function statusStatistics()
    {
        $agencyId = auth('api')->user()->agency_id;

        $statuses = Status::where('agency_id', $agencyId)
            ->where('type', 'candidate')
            ->orderBy('serial')
            ->get();

        $total = Candidate::where('agency_id', $agencyId)->count();

        $data = $statuses->map(function ($status) use ($agencyId, $total) {
            $count = Candidate::where('agency_id', $agencyId)
                ->whereJsonContains('status_id', $status->id)
                ->count();

            return [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Candidate status statistics retrieved successfully',
            'data' => $data,
            'meta' => ['total' => $total],
        ]);
    }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $form = Form::where('id', $request->form_id)
            ->where('agency_id', $agencyId)
            ->firstOrFail();

        $formFields = FormField::where('form_id', $form->id)
            ->where('status', 1)
            ->get();

        $rules = [
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|unique:candidates,email',
            'mobile' => 'nullable|string|max:20',
        ];

        foreach ($formFields as $field) {

            if ($field->validation_rules) {

                $rules["fields.$field->id"] = $field->validation_rules;
            }

            if ($field->is_required) {

                $rules["fields.$field->id"] = 'required';
            }
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {

            $candidate = Candidate::create([
                'agency_id' => $agencyId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'type_id' => $request->type_id,
                'location_id' => $request->location_id,
                'checklist_id' => $request->checklist_id,
                'tag_id' => $request->tag_id,
                'status_id' => $request->status_id,
                'status_changed_at' => $request->status_id ? now() : null,
            ]);

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'entity_id' => $candidate->id,
            ]);

            $allowedFields = $formFields->pluck('id')->toArray();

            $insertData = [];

            foreach ($request->fields ?? [] as $fieldId => $value) {

                if (! in_array($fieldId, $allowedFields)) {
                    continue;
                }

                $insertData[] = [
                    'submission_id' => $submission->id,
                    'form_field_id' => $fieldId,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($insertData)) {

                FormFieldValue::insert($insertData);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Candidate created successfully',
                'data' => $candidate,
            ]);

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
     * Candidate detail page header card: photo, name, resolved status (with how
     * long it's been in that status), email, phone, registration date.
     */
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)
            ->with(['submissions.values.field'])
            ->findOrFail($id);

        $status = Status::where('agency_id', $agencyId)
            ->where('type', 'candidate')
            ->find($candidate->status_id[0] ?? null);

        $statusSince = $candidate->status_changed_at ?? $candidate->created_at;

        return response()->json([
            'status' => true,
            'message' => 'Candidate retrieved successfully',
            'data' => [
                'id' => $candidate->id,
                'full_name' => $candidate->full_name,
                'image_url' => $candidate->image_url,
                'email' => $candidate->email,
                'mobile' => $candidate->mobile,
                'registration_date' => $candidate->created_at,
                'status' => $status ? [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color,
                ] : null,
                'status_changed_at' => $statusSince,
                'status_duration_label' => $this->formatStatusDuration($statusSince),
                'submissions' => $candidate->submissions,
            ],
        ]);
    }

    private function formatStatusDuration($since): string
    {
        if (! $since) {
            return 'just now';
        }

        $diff = $since->diff(now());

        $parts = [];

        if ($diff->d > 0) {
            $parts[] = $diff->d.' day'.($diff->d > 1 ? 's' : '');
        }

        if ($diff->h > 0) {
            $parts[] = $diff->h.' hour'.($diff->h > 1 ? 's' : '');
        }

        if ($diff->i > 0) {
            $parts[] = $diff->i.' minute'.($diff->i > 1 ? 's' : '');
        }

        return $parts ? implode(', ', $parts) : 'just now';
    }

    public function updateStatus(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $request->validate([
            'status_id' => [
                'required',
                'integer',
                Rule::exists('statuses', 'id')->where(function ($query) use ($agencyId) {
                    $query->where('agency_id', $agencyId)->where('type', 'candidate');
                }),
            ],
        ]);

        $candidate->update([
            'status_id' => [(int) $request->status_id],
            'status_changed_at' => now(),
        ]);

        $status = Status::find($request->status_id);

        return response()->json([
            'status' => true,
            'message' => 'Candidate status updated successfully',
            'data' => [
                'id' => $candidate->id,
                'status' => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color,
                ],
            ],
        ]);
    }

    /**
     * "Lists" tab on the candidate detail page: every agency-level Type/Checklist/
     * Location/Tag option, each flagged whether it's currently assigned to this
     * candidate (i.e. present in the candidate's `type_id`/`checklist_id`/`location_id`/`tag_id`).
     */
    public function lists($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Candidate lists retrieved successfully',
            'data' => [
                'types' => $this->buildAssignableList(
                    Type::where('agency_id', $agencyId)->where('type', 'candidate')->get(),
                    $candidate->type_id,
                    'name'
                ),
                'checklist' => $this->buildAssignableList(
                    CheckList::where('agency_id', $agencyId)->where('type', 'candidate')->get(),
                    $candidate->checklist_id,
                    'name'
                ),
                'locations' => $this->buildAssignableList(
                    Location::where('agency_id', $agencyId)->get(),
                    $candidate->location_id,
                    'location'
                ),
                'tags' => $this->buildAssignableList(
                    Tag::where('agency_id', $agencyId)->where('type', 'candidate')->get(),
                    $candidate->tag_id,
                    'name'
                ),
            ],
        ]);
    }

    /**
     * Toggle which Types/Checklist items/Locations/Tags are assigned to this
     * candidate. Each key is optional so a single checkbox toggle (e.g. just
     * `location_id`) doesn't require resending the other three lists.
     */
    public function updateLists(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $request->validate([
            'type_id' => 'sometimes|array',
            'type_id.*' => [
                'integer',
                Rule::exists('types', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'candidate')),
            ],
            'checklist_id' => 'sometimes|array',
            'checklist_id.*' => [
                'integer',
                Rule::exists('check_lists', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'candidate')),
            ],
            'location_id' => 'sometimes|array',
            'location_id.*' => [
                'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)),
            ],
            'tag_id' => 'sometimes|array',
            'tag_id.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'candidate')),
            ],
        ]);

        $candidate->update($request->only(['type_id', 'checklist_id', 'location_id', 'tag_id']));

        return response()->json([
            'status' => true,
            'message' => 'Candidate lists updated successfully',
            'data' => [
                'type_id' => $candidate->type_id,
                'checklist_id' => $candidate->checklist_id,
                'location_id' => $candidate->location_id,
                'tag_id' => $candidate->tag_id,
            ],
        ]);
    }

    /**
     * @param  Collection  $items
     * @param  array<int,int>|null  $assignedIds
     * @return array<int,array<string,mixed>>
     */
    private function buildAssignableList($items, ?array $assignedIds, string $nameField): array
    {
        $assignedIds = collect($assignedIds ?? [])->map(fn ($id) => (int) $id);

        return $items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->{$nameField},
            'assigned' => $assignedIds->contains($item->id),
        ])->values()->all();
    }
}
