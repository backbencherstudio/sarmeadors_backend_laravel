<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyClientGlobalSetting;
use App\Models\CheckList;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use App\Models\Location;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Map global-setting column keys to actual `clients` table columns.
     *
     * @var array<string,string>
     */
    private array $columnMap = [
        'name' => 'first_name',
        'email_address' => 'email',
        'phone_number' => 'mobile',
        'registration_date' => 'created_at',
        'status' => 'status_id',
        'payment_status' => 'payment_status',
        'hear_about_us' => 'hear_about_us',
    ];

    public function index(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $dashboard = AgencyClientGlobalSetting::where('agency_id', $agencyId)
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

        $query = Client::where('agency_id', $agencyId);

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

        $clients = $query->orderBy($sortColumn, $sortDirection)->paginate($perPage);

        $statusesById = Status::where('agency_id', $agencyId)
            ->where('type', 'client')
            ->get()
            ->keyBy('id');

        $typesById = Type::where('agency_id', $agencyId)->get()->keyBy('id');
        $locationsById = Location::where('agency_id', $agencyId)->get()->keyBy('id');

        $rows = $clients->getCollection()->map(function ($client) use ($tableFields, $statusesById, $typesById, $locationsById) {
            $row = ['id' => $client->id];

            foreach ($tableFields as $field) {
                $row[$field] = match ($field) {
                    'name' => $client->full_name,
                    'email_address' => $client->email,
                    'phone_number' => $client->mobile,
                    'registration_date' => $client->created_at?->format('n/j/y'),
                    'status' => $this->resolveStatus($client->status_id, $statusesById),
                    default => $client->{$field} ?? null,
                };
            }

            $row['type'] = $this->resolveNames($client->type_id, $typesById, 'name');
            $row['location'] = $this->resolveNames($client->location_id, $locationsById, 'location');

            return $row;
        })->values();

        $columns = collect($tableFields)->map(fn ($field) => [
            'key' => $field,
            'label' => $displayLabels[$field] ?? Str::title(str_replace('_', ' ', $field)),
        ])->values();

        return response()->json([
            'status' => true,
            'message' => 'Clients retrieved successfully',
            'columns' => $columns,
            'data' => $rows,
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'next_page_url' => $clients->nextPageUrl(),
                'prev_page_url' => $clients->previousPageUrl(),
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
     * Resolve a JSON id array (`type_id`/`location_id`) into display names using
     * an agency-scoped, id-keyed collection of `Type`/`Location` records.
     *
     * @param  array<int,int>|null  $ids
     * @return array<int,string>
     */
    private function resolveNames(?array $ids, $byId, string $nameField): array
    {
        return collect($ids ?? [])
            ->map(fn ($id) => $byId->get($id)?->{$nameField})
            ->filter()
            ->values()
            ->all();
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
            ->where('type', 'client')
            ->orderBy('serial')
            ->get();

        $total = Client::where('agency_id', $agencyId)->count();

        $data = $statuses->map(function ($status) use ($agencyId, $total) {
            $count = Client::where('agency_id', $agencyId)
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
            'message' => 'Client status statistics retrieved successfully',
            'data' => $data,
            'meta' => ['total' => $total],
        ]);
    }
    // {
    // "form_id": 1,

    // "first_name": "John",
    // "last_name": "Doe",
    // "email": "john@example.com",
    // "mobile": "01700000000",

    // "type_id": [1, 2],
    // "location_id": [3],
    // "checklist_id": [5, 6],
    // "tag_id": [2],
    // "status_id": [1],

    // "fields": {
    //     "1": "Male",
    //     "2": "1996-10-10",
    // }
    // }

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
            'email' => 'required|email|unique:clients,email',
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

            $client = Client::create([
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
                'entity_id' => $client->id,
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
                'message' => 'Client created successfully',
                'data' => $client,
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
     * Client detail page header card: photo, name, resolved status (with how
     * long it's been in that status), email, phone, registration date.
     */
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)
            ->with(['submissions.values.field'])
            ->findOrFail($id);

        $status = Status::where('agency_id', $agencyId)
            ->where('type', 'client')
            ->find($client->status_id[0] ?? null);

        $statusSince = $client->status_changed_at ?? $client->created_at;

        return response()->json([
            'status' => true,
            'message' => 'Client retrieved successfully',
            'data' => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'image_url' => $client->image_url,
                'email' => $client->email,
                'mobile' => $client->mobile,
                'registration_date' => $client->created_at,
                'status' => $status ? [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color,
                ] : null,
                'status_changed_at' => $statusSince,
                'status_duration_label' => $this->formatStatusDuration($statusSince),
                'submissions' => $client->submissions,
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

        $client = Client::where('agency_id', $agencyId)->findOrFail($id);

        $request->validate([
            'status_id' => [
                'required',
                'integer',
                Rule::exists('statuses', 'id')->where(function ($query) use ($agencyId) {
                    $query->where('agency_id', $agencyId)->where('type', 'client');
                }),
            ],
        ]);

        $client->update([
            'status_id' => [(int) $request->status_id],
            'status_changed_at' => now(),
        ]);

        $status = Status::find($request->status_id);

        return response()->json([
            'status' => true,
            'message' => 'Client status updated successfully',
            'data' => [
                'id' => $client->id,
                'status' => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color,
                ],
            ],
        ]);
    }

    /**
     * "Lists" tab on the client detail page: every agency-level Type/Checklist/
     * Location/Tag option, each flagged whether it's currently assigned to this
     * client (i.e. present in the client's `type_id`/`checklist_id`/`location_id`/`tag_id`).
     */
    public function lists($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Client lists retrieved successfully',
            'data' => [
                'types' => $this->buildAssignableList(
                    Type::where('agency_id', $agencyId)->where('type', 'client')->get(),
                    $client->type_id,
                    'name'
                ),
                'checklist' => $this->buildAssignableList(
                    CheckList::where('agency_id', $agencyId)->where('type', 'client')->get(),
                    $client->checklist_id,
                    'name'
                ),
                'locations' => $this->buildAssignableList(
                    Location::where('agency_id', $agencyId)->get(),
                    $client->location_id,
                    'location'
                ),
                'tags' => $this->buildAssignableList(
                    Tag::where('agency_id', $agencyId)->where('type', 'client')->get(),
                    $client->tag_id,
                    'name'
                ),
            ],
        ]);
    }

    /**
     * Toggle which Types/Checklist items/Locations/Tags are assigned to this
     * client. Each key is optional so a single checkbox toggle (e.g. just
     * `location_id`) doesn't require resending the other three lists.
     */
    public function updateLists(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($id);

        $request->validate([
            'type_id' => 'sometimes|array',
            'type_id.*' => [
                'integer',
                Rule::exists('types', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'client')),
            ],
            'checklist_id' => 'sometimes|array',
            'checklist_id.*' => [
                'integer',
                Rule::exists('check_lists', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'client')),
            ],
            'location_id' => 'sometimes|array',
            'location_id.*' => [
                'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)),
            ],
            'tag_id' => 'sometimes|array',
            'tag_id.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'client')),
            ],
        ]);

        $client->update($request->only(['type_id', 'checklist_id', 'location_id', 'tag_id']));

        return response()->json([
            'status' => true,
            'message' => 'Client lists updated successfully',
            'data' => [
                'type_id' => $client->type_id,
                'checklist_id' => $client->checklist_id,
                'location_id' => $client->location_id,
                'tag_id' => $client->tag_id,
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
