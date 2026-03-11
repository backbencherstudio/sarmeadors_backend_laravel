<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Status;
use App\Models\StatusTemplate;
use Illuminate\Http\Request;

class StatusTemplateController extends Controller
{
    public function getProcessFlow()
    {
        $agencyId = auth()->user()->agency_id;

        $flow = Status::where('agency_id', $agencyId)
            ->with('statusTemplates.template')
            ->orderBy('serial', 'asc')
            ->get();

        if ($flow->isEmpty()) {
            return response()->json([
                'status'  => 'info',
                'message' => 'No process flow found.',
                'data'    => []
            ], 200);
        }

        $formattedFlow = $flow->map(function ($status) {
            return [
                'id'        => $status->id,
                'name'      => $status->name,
                'color'     => $status->color,
                'serial'    => $status->serial,
                'templates' => $status->statusTemplates->groupBy('template_type')->map(function ($items) {
                    return $items->map(function ($item) {
                        return [
                            'status_template_id' => $item->id,
                            'id'                 => $item->template->id,
                            'title'              => $item->template->title,
                            'content'            => $item->template->content,
                            'type'               => $item->template->type,
                        ];
                    });
                })
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Process flow retrieved successfully.',
            'data'    => $formattedFlow
        ], 200);
    }

    // public function getProcessFlow()
    // {
    //     $agencyId = auth()->user()->agency_id;

    //     $flow = ClientStatus::where('agency_id', $agencyId)
    //         ->with('statusTemplates.template')
    //         ->orderBy('serial', 'asc')
    //         ->get();

    //     if ($flow->isEmpty()) {
    //         return response()->json([
    //             'status'  => 'info',
    //             'message' => 'No process flow found for this agency. Please setup your client statuses first.',
    //             'data'    => []
    //         ], 200);
    //     }

    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => 'Process flow retrieved successfully.',
    //         'data'    => $flow
    //     ], 200);
    // }

    public function show($id)
    {
        $data = StatusTemplate::whereHas('status', function ($q) {
            $q->where('agency_id', auth()->user()->agency_id);
        })
            ->with(['status', 'template'])
            ->find($id);

        if (!$data) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Status template not found or Unauthorized access'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data retrieved successfully',
            'data'    => [
                'status_template_id' => $data->id,
                'status_id'          => $data->status_id,
                'status_name'        => $data->status->name,
                'template_id'        => $data->template_id,
                'template_title'     => $data->template->title,
                'template_type'      => $data->template_type,
                'template_content'   => $data->template->content,
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'status_id'     => 'required|integer',
            'template_id'   => 'required|integer',
            'template_type' => 'required|string',
        ]);

        $status = Status::where('id', $request->status_id)
            ->where('agency_id', auth()->user()->agency_id)
            ->first();

        if (!$status) {
            return response()->json(['message' => 'Unauthorized Status ID'], 403);
        }


        $statusTemplate = StatusTemplate::create([
            'status_id'     => $request->status_id,
            'template_id'   => $request->template_id,
            'template_type' => $request->template_type,
        ]);

        return response()->json(['status' => 'success', 'data' => $statusTemplate], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status_id'     => 'required|integer',
            'template_id'   => 'required|integer',
            'template_type' => 'required|string',
        ]);

        $statusTemplate = StatusTemplate::whereHas('status', function ($q) {
            $q->where('agency_id', auth()->user()->agency_id);
        })->find($id);

        if (!$statusTemplate) {
            return response()->json(['message' => 'Status Template not found or Unauthorized'], 404);
        }

        $validStatus = Status::where('id', $request->status_id)
            ->where('agency_id', auth()->user()->agency_id)
            ->exists();

        if (!$validStatus) {
            return response()->json(['message' => 'The selected Status is unauthorized'], 403);
        }

        $statusTemplate->update([
            'status_id'     => $request->status_id,
            'template_id'   => $request->template_id,
            'template_type' => $request->template_type,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status template updated successfully',
            'data'    => $statusTemplate->load('template')
        ], 200);
    }

    public function destroy($id)
    {
        $statusTemplate = StatusTemplate::whereHas('status', function ($q) {
            $q->where('agency_id', auth()->user()->agency_id);
        })->find($id);

        if (!$statusTemplate) {
            return response()->json(['message' => 'Status template not found or Unauthorized access'], 404);
        }

        $statusTemplate->delete();
        return response()->json(['status' => 'success', 'message' => 'Deleted']);
    }
}
