<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyClientGlobalSetting;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use App\Models\Status;
use Illuminate\Http\Request;
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
        $sortField = $request->query('sort_field', $defaultSortField);
        $sortDirection = $request->query('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Client::where('agency_id', $agencyId);

        if ($search) {
            $searchColumn = $this->columnMap[$quickSearchField] ?? null;

            if ($searchColumn && $searchColumn !== 'status_id') {
                $query->where($searchColumn, 'like', "%{$search}%");
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            }
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

        $rows = $clients->getCollection()->map(function ($client) use ($tableFields, $statusesById) {
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

    public function show($id)
    {
        $client = Client::with([
            'submissions.values.field',
        ])->findOrFail($id);

        return response()->json($client);
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

        $client->update(['status_id' => [(int) $request->status_id]]);

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
}
