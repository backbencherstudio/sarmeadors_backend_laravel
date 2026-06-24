<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\MessageTemplate;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StatusTemplateController extends Controller
{
    public function getProcessFlow(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;
        $type = $request->query('type', 'client');

        if (! in_array($type, ['client', 'candidate'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid type. Allowed: client, candidate.',
            ], 422);
        }

        $statuses = Status::where('agency_id', $agencyId)
            ->where('type', $type)
            ->orderBy('serial', 'asc')
            ->get();

        if ($statuses->isEmpty()) {
            return response()->json([
                'status' => 'info',
                'message' => 'No process flow found.',
                'data' => [],
            ], 200);
        }

        $formattedFlow = $statuses->map(function ($status) use ($agencyId, $type) {
            return [
                'status_id' => $status->id,
                'status_name' => $status->name,
                'status_color' => $status->color,
                'serial' => $status->serial,
                'status_type' => $status->type,
                'document_templates' => DocumentTemplate::where('agency_id', $agencyId)
                    ->where('trigger_status_id', $status->id)
                    ->where('user_type', $type)
                    ->get(['id', 'name', 'user_type', 'trigger_status_id', 'post_sign_status_id']),
                'email_templates' => MessageTemplate::where('agency_id', $agencyId)
                    ->where('status', $status->id)
                    ->get(['id', 'name', 'subject', 'status']),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Process flow retrieved successfully.',
            'data' => $formattedFlow,
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'template_type' => 'required|in:email_template,document_template',
        ]);

        $template = $this->findTemplate($request->template_type, $id, $agencyId);

        if (! $template) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found or unauthorized access',
            ], 404);
        }

        $statusId = $request->template_type === 'document_template'
            ? $template->trigger_status_id
            : $template->status;

        $status = $statusId ? Status::where('agency_id', $agencyId)->find($statusId) : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => [
                'template_id' => $template->id,
                'template_type' => $request->template_type,
                'status_id' => $status?->id,
                'status_name' => $status?->name,
                'template' => $template,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'status_id' => 'required|integer',
            'template_id' => 'required|integer',
            'template_type' => 'required|in:email_template,document_template',
        ]);

        $status = Status::where('id', $request->status_id)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $status) {
            return response()->json(['message' => 'Unauthorized Status ID'], 403);
        }

        $template = $this->findTemplate($request->template_type, $request->template_id, $agencyId);

        if (! $template) {
            return response()->json(['message' => 'Template not found or unauthorized'], 404);
        }

        if ($request->template_type === 'document_template') {
            $template->update(['trigger_status_id' => $status->id]);
        } else {
            $template->update(['status' => $status->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Template attached to status successfully',
            'data' => $template,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'status_id' => 'required|integer',
            'template_type' => 'required|in:email_template,document_template',
        ]);

        $status = Status::where('id', $request->status_id)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $status) {
            return response()->json(['message' => 'The selected Status is unauthorized'], 403);
        }

        $template = $this->findTemplate($request->template_type, $id, $agencyId);

        if (! $template) {
            return response()->json(['message' => 'Template not found or unauthorized'], 404);
        }

        if ($request->template_type === 'document_template') {
            $template->update(['trigger_status_id' => $status->id]);
        } else {
            $template->update(['status' => $status->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status template updated successfully',
            'data' => $template,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'template_type' => 'required|in:email_template,document_template',
        ]);

        $template = $this->findTemplate($request->template_type, $id, $agencyId);

        if (! $template) {
            return response()->json(['message' => 'Template not found or unauthorized access'], 404);
        }

        if ($request->template_type === 'document_template') {
            $template->update(['trigger_status_id' => null]);
        } else {
            $template->update(['status' => null]);
        }

        return response()->json(['status' => 'success', 'message' => 'Template detached from status']);
    }

    private function findTemplate(string $templateType, int $templateId, int $agencyId): DocumentTemplate|MessageTemplate|null
    {
        if ($templateType === 'document_template') {
            return DocumentTemplate::where('agency_id', $agencyId)->find($templateId);
        }

        return MessageTemplate::where('agency_id', $agencyId)->find($templateId);
    }

    // Create new status
    public function storeAfter(Request $request)
    {
        $authUser = auth('api')->user();

        $request->validate([
            'after_status_id' => 'required|exists:statuses,id',
            'type' => 'required|in:client,candidate|max:50',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('statuses')->where(function ($query) use ($authUser, $request) {
                    return $query->where('agency_id', $authUser->agency_id)
                        ->where('type', $request->type);
                }),
            ],
            'color' => 'required|string|max:50',
        ]);

        $referenceStatus = Status::where('id', $request->after_status_id)
            ->where('agency_id', $authUser->agency_id)
            ->firstOrFail();

        $newSerial = $referenceStatus->serial + 1;

        return DB::transaction(function () use ($authUser, $request, $newSerial) {

            Status::where('agency_id', $authUser->agency_id)
                ->where('serial', '>=', $newSerial)
                ->increment('serial');

            $status = Status::create([
                'agency_id' => $authUser->agency_id,
                'name' => $request->name,
                'color' => $request->color,
                'serial' => $newSerial,
                'type' => $request->type,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'New status inserted successfully at position '.$newSerial,
                'data' => $status,
            ]);
        });
    }
}
