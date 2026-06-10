<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $all_templates = MessageTemplate::where('agency_id', request()->current_agency->id)->latest()->paginate();

        return $this->sendResponse($all_templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'subject' => 'required|string',
            'body' => 'required|string',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $attachments = [];

        if ($request->hasFile('attachments')) {

            foreach ($request->file('attachments') as $file) {

                $path = $file->store('attachments', 'public');

                $attachments[] = $path;
            }
        }

        $message = MessageTemplate::create([
            'name' => $request->name,
            'subject' => $request->subject,
            'body' => $request->body,
            'user_type' => $request->user_type,
            'status' => $request->status,
            'type' => $request->type,
            'sender_email' => $request->sender_email,
            'receiver_email' => $request->receiver_email,
            'cc' => $request->cc,
            'bcc' => $request->bcc,
            'location' => $request->location,
            'attachments' => $attachments,
            'agency_id' => request()->current_agency->id,
        ]);

        return $this->sendResponse($message);
    }

    /**
     * Display the specified resource.
     */
    public function show(MessageTemplate $messageTemplate)
    {
        return $this->sendResponse($messageTemplate);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MessageTemplate $messageTemplate)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'subject' => 'sometimes|required|string',
            'body' => 'sometimes|required|string',
            'user_type' => 'nullable|integer',
            'status' => 'nullable|integer',
            'type' => 'nullable|string',
            'sender_email' => 'nullable|email',
            'receiver_email' => 'nullable|email',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'location' => 'nullable|string',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('attachments', 'public');
            }
            $data['attachments'] = $attachments;
        }

        $messageTemplate->update($data);

        return $this->sendResponse($messageTemplate, 'Template updated successfully');
    }

    /**
     * Remove the specified resources from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:message_templates,id',
        ]);

        MessageTemplate::whereIn('id', $request->ids)->delete();

        return $this->sendResponse([], 'Templates deleted successfully');
    }
}
