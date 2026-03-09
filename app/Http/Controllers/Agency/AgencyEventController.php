<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyEvent;
use Illuminate\Http\Request;

class AgencyEventController extends Controller
{
    public function index(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');

        $events = AgencyEvent::with(['eventType', 'client', 'candidate', 'assignedUser'])
            ->where('agency_id', $agencyId)
            ->when($search, function ($query, $search) {
                return $query->where('event_title', 'like', '%' . $search . '%');
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Events retrieved successfully',
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ]
        ], 200);
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'event_title'    => 'required|string|max:255',
            'event_type_id'  => 'required|exists:agency_event_types,id',
            'event_date'     => 'required|date',
            'event_time'     => 'required',
            'event_time_zone' => 'required|string',
            'client_id'      => 'nullable|exists:clients,id',
            'candidate_id'   => 'nullable|exists:candidates,id',
            'location'       => 'nullable|string|max:255',
            'interview_link' => 'nullable|url',
            'note'           => 'nullable|string',
            'is_email_notify' => 'boolean',
            'assign_user_id' => 'nullable|exists:users,id',
        ]);

        // Validation: At least one must be present
        if (!$request->client_id && !$request->candidate_id) {
            return response()->json(['status' => false, 'message' => 'Client or Candidate must be selected.'], 422);
        }

        $location = $request->location;

        // If location is empty, fetch from profile
        if (empty($location)) {
            if ($request->filled('client_id')) {
                $client = Client::find($request->client_id);
                $location = $client?->address; // assuming 'address' column exists
            } elseif ($request->filled('candidate_id')) {
                $candidate = Candidate::find($request->candidate_id);
                $location = $candidate?->address;
            }
        }

        $event = AgencyEvent::create([
            'agency_id'       => $agencyId,
            'event_type_id'   => $request->event_type_id,
            'event_title'     => $request->event_title,
            'event_date'      => $request->event_date,
            'event_time'      => $request->event_time,
            'event_time_zone' => $request->event_time_zone,
            'client_id'       => $request->client_id,
            'candidate_id'    => $request->candidate_id,
            'location'        => $location,
            'interview_link'  => $request->interview_link,
            'note'            => $request->note,
            'is_email_notify' => $request->is_email_notify ?? 0,
            'assign_user_id'  => $request->assign_user_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Event created successfully',
            'data' => $event->load(['eventType', 'client', 'candidate', 'assignedUser'])
        ], 201);
    }

    /**
     * Display the specified event.
     */
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $event = AgencyEvent::with(['eventType', 'client', 'candidate', 'assignedUser'])
            ->where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$event) {
            return response()->json(['status' => false, 'message' => 'Event not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $event], 200);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $event = AgencyEvent::where('id', $id)->where('agency_id', $agencyId)->first();

        if (!$event) {
            return response()->json(['status' => false, 'message' => 'Event not found'], 404);
        }

        $request->validate([
            'event_title'    => 'sometimes|required|string|max:255',
            'event_type_id'  => 'sometimes|required|exists:agency_event_types,id',
            'event_date'     => 'sometimes|required|date',
            'event_time'     => 'sometimes|required',
            'client_id'      => 'nullable|exists:clients,id',
            'candidate_id'   => 'nullable|exists:candidates,id',
            'location'       => 'nullable|string|max:255',
            'is_email_notify' => 'boolean',
        ]);

        $data = $request->all();

        // Location update logic: if location is passed as empty, try to refresh from profile
        if ($request->has('location') && empty($request->location)) {
            $clientId = $request->client_id ?? $event->client_id;
            $candidateId = $request->candidate_id ?? $event->candidate_id;

            if ($clientId) {
                $data['location'] = Client::find($clientId)?->address;
            } elseif ($candidateId) {
                $data['location'] = Candidate::find($candidateId)?->address;
            }
        }

        $event->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Event updated successfully',
            'data' => $event->fresh(['eventType', 'client', 'candidate', 'assignedUser'])
        ], 200);
    }

    /**
     * Remove the specified event.
     */
    public function destroy($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $event = AgencyEvent::where('id', $id)->where('agency_id', $agencyId)->first();

        if (!$event) {
            return response()->json(['status' => false, 'message' => 'Event not found'], 404);
        }

        $event->delete();

        return response()->json([
            'status' => true,
            'message' => 'Event deleted successfully'
        ], 200);
    }
}
