<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DocumentTemplateController extends Controller
{
    private $uploadPath = 'uploads/agency/document';

    public function index(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $query = DocumentTemplate::where('agency_id', $agencyId)
            ->with(['fields', 'signers', 'category', 'triggerStatus']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('triggerStatus', function ($statusQuery) use ($searchTerm) {
                        $statusQuery->where('name', 'LIKE', "%{$searchTerm}%");
                    });
            });
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('status_id')) {
            $query->where('trigger_status_id', $request->status_id);
        }

        $perPage = $request->get('per_page', 10);
        $data = $query->latest()->paginate($perPage);

        if ($data->isEmpty()) {
            return response()->json([
                'status'  => true,
                'message' => 'No document templates found.',
                'data'    => []
            ], 200);
        }

        $data->getCollection()->transform(function ($item) {
            $item->file_url = $item->file_path ? asset($item->file_path) : null;
            return $item;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Document templates retrieved successfully.',
            'data'    => $data
        ], 200);
    }

    public function show($id)
    {
        $template = DocumentTemplate::with(['fields', 'signers'])->find($id);

        if (!$template) {
            return response()->json([
                'status' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $template->file_url = $template->file_path ? asset($template->file_path) : null;

        return response()->json([
            'status' => true,
            'data' => $template
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'user_type' => 'required|in:client,candidate',
            'content_type' => 'required|in:text,image',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        return DB::transaction(function () use ($request) {
            $agencyId = auth('api')->user()->agency_id;
            $fileName = null;

            if ($request->content_type === 'image' && $request->hasFile('image_file')) {
                $file = $request->file('image_file');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($this->uploadPath), $fileName);
            }

            $template = DocumentTemplate::create([
                'agency_id'           => $agencyId,
                'name'                => $request->name,
                'user_type'           => $request->user_type,
                'category_id'         => $request->category_id,
                'trigger_status_id'   => $request->trigger_status_id,
                'post_sign_status_id' => $request->post_sign_status_id,
                'content_type'        => $request->content_type,
                'content'             => $request->content,
                'file_path'           => $fileName ? $this->uploadPath . '/' . $fileName : null,
                'org_signer_name'     => $request->org_signer_name,
                'org_name'            => $request->org_name,
                'notification_emails' => json_decode($request->notification_emails, true),
            ]);

            $this->saveFieldsAndSigners($template, $request);

            return response()->json(['status' => true, 'message' => 'Template Created Successfully'], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $template = DocumentTemplate::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'user_type' => 'required|in:client,candidate',
            'content_type' => 'required|in:text,image',
        ]);

        return DB::transaction(function () use ($request, $template) {
            $fileName = $template->getRawOriginal('file_path');

            if ($request->content_type === 'image' && $request->hasFile('image_file')) {

                if ($template->file_path && File::exists(public_path($template->file_path))) {
                    File::delete(public_path($template->file_path));
                }

                $file = $request->file('image_file');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($this->uploadPath), $fileName);
                $fileName = $this->uploadPath . '/' . $fileName;
            }

            $template->update([
                'name'                => $request->name,
                'user_type'           => $request->user_type,
                'category_id'         => $request->category_id,
                'trigger_status_id'   => $request->trigger_status_id,
                'post_sign_status_id' => $request->post_sign_status_id,
                'content_type'        => $request->content_type,
                'content'             => $request->content,
                'file_path'           => $fileName,
                'org_signer_name'     => $request->org_signer_name,
                'org_name'            => $request->org_name,
                'notification_emails' => json_decode($request->notification_emails, true),
            ]);

            $template->fields()->delete();
            $template->signers()->delete();
            $this->saveFieldsAndSigners($template, $request);

            return response()->json(['status' => true, 'message' => 'Template Updated Successfully']);
        });
    }

    private function saveFieldsAndSigners($template, $request)
    {
        $fields = json_decode($request->fields, true);
        if (!empty($fields)) {
            foreach ($fields as $index => $field) {
                $template->fields()->create([
                    'field_type'  => $field['field_type'],
                    'field_label' => $field['field_label'],
                    'placeholder' => $field['placeholder'] ?? '',
                    'field_tag'   => "[[field_" . ($index + 1) . "]]",
                    'is_required' => $field['is_required'] ?? false,
                    'admin_only'  => $field['admin_only'] ?? false,
                ]);
            }
        }

        $signers = json_decode($request->signers, true);
        if (!empty($signers)) {
            foreach ($signers as $signer) {
                $template->signers()->create([
                    'signer_label' => $signer['signer_label']
                ]);
            }
        }
    }


    public function destroy($id)
    {
        $template = DocumentTemplate::findOrFail($id);
        if ($template->file_path && File::exists(public_path($template->file_path))) {
            File::delete(public_path($template->file_path));
        }
        $template->delete();
        return response()->json(['status' => true, 'message' => 'Template Deleted']);
    }
}
