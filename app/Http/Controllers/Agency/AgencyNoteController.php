<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyNote;
use App\Models\User;
use Illuminate\Http\Request;

class AgencyNoteController extends Controller
{
    /**
     * Display a listing of the agency note.
     */
    public function index(User $user)
    {
        $notes = $user->notes()
            ->orderByDesc('pin_note')
            ->orderBy('created_at')
            ->get();

        return $this->sendResponse($notes);
    }

    /**
     * Store a newly created agency note in storage.
     */
    public function store(Request $request, User $user)
    {
        $agency_id = request()->current_agency->id;

        $note = AgencyNote::create([
            'agency_id' => $agency_id,
            'user_id' => $user->id,
            'note' => 'Untitled',
            'pin_note' => false,
        ]);

        return $this->sendResponse($note);
    }

    /**
     * Display the specified agency note.
     */
    public function show(AgencyNote $agencyNote)
    {
        return $this->sendResponse($agencyNote);
    }

    /**
     * Update the specified agency note in storage.
     */
    public function update(Request $request, AgencyNote $agencyNote)
    {
        $data = $request->validate([
            'note' => 'required',
        ]);

        $agencyNote->update($data);

        return $this->sendResponse($agencyNote, 'Note updated successfully');
    }

    /**
     * Remove the specified agency note from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:agency_notes,id',
        ]);

        AgencyNote::whereIn('id', $request->ids)->delete();

        return $this->sendResponse([], 'Notes deleted successfully');
    }

    /**
     * Pin the specified agency note from storage.
     */
    public function pin_note(AgencyNote $agencyNote)
    {
        $agencyNote->pin_note = ! $agencyNote->pin_note;
        $agencyNote->save();

        return $this->sendResponse($agencyNote, 'Pin status updated successfully');
    }

    /**
     * Mark the specified agency note from storage.
     */
    public function mark_read(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:agency_notes,id',
        ]);

        AgencyNote::whereIn('id', $request->ids)->update([
            'is_read' => true,
        ]);

        return $this->sendResponse([], 'Selected notes marked as read');
    }
}
