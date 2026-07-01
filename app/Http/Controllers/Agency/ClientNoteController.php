<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\User;
use App\Traits\SendsNotifications;
use Illuminate\Http\Request;

/**
 * Agency-side "Notes" tab on a client's detail page. Any agency admin/staff
 * can write a note about the client, optionally notifying specific other
 * admins in the same agency. Notes can be pinned and bulk-deleted.
 */
class ClientNoteController extends Controller
{
    use SendsNotifications;

    // GET /agency/clients/{id}/notes
    public function index($clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $notes = ClientNote::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->with('user')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Client notes retrieved successfully',
            'data' => $notes->map(fn (ClientNote $note) => $this->formatNote($note))->values(),
        ]);
    }

    // GET /agency/clients/{id}/notes/admins
    public function availableAdmins($clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $admins = User::where('agency_id', $agencyId)
            ->where('id', '!=', auth('api')->id())
            ->whereHas('roles', fn ($query) => $query->where('name', 'agency_admin'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json([
            'status' => true,
            'data' => $admins->map(fn (User $admin) => $this->formatAdmin($admin))->values(),
        ]);
    }

    // POST /agency/clients/{id}/notes
    public function store(Request $request, $clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'notify_admin_ids' => 'nullable|array',
            'notify_admin_ids.*' => 'integer|exists:users,id',
        ]);

        $note = ClientNote::create([
            'agency_id' => $agencyId,
            'client_id' => $client->id,
            'user_id' => auth('api')->id(),
            'title' => $data['title'] ?? 'Untitled',
            'body' => $data['body'] ?? null,
            'is_pinned' => false,
            'notify_admin_ids' => $data['notify_admin_ids'] ?? [],
        ]);

        $this->notifyAboutNote($note, $client->full_name);

        return response()->json([
            'status' => true,
            'message' => 'Note created successfully',
            'data' => $this->formatNote($note->load('user')),
        ]);
    }

    // GET /agency/clients/{id}/notes/{noteId}
    public function show($clientId, $noteId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $note = ClientNote::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->with('user')
            ->findOrFail($noteId);

        return response()->json([
            'status' => true,
            'data' => $this->formatNote($note),
        ]);
    }

    // PATCH /agency/clients/{id}/notes/{noteId}
    public function update(Request $request, $clientId, $noteId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $note = ClientNote::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->findOrFail($noteId);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'notify_admin_ids' => 'nullable|array',
            'notify_admin_ids.*' => 'integer|exists:users,id',
        ]);

        $note->update([
            'title' => $data['title'] ?? $note->title,
            'body' => array_key_exists('body', $data) ? $data['body'] : $note->body,
            'notify_admin_ids' => $data['notify_admin_ids'] ?? $note->notify_admin_ids,
        ]);

        if (array_key_exists('notify_admin_ids', $data)) {
            $this->notifyAboutNote($note, $client->full_name);
        }

        return response()->json([
            'status' => true,
            'message' => 'Note updated successfully',
            'data' => $this->formatNote($note->fresh()->load('user')),
        ]);
    }

    /**
     * Flip whether this note is pinned — hitting this route just toggles the
     * current state.
     */
    // PATCH /agency/clients/{id}/notes/{noteId}/pin
    public function togglePin($clientId, $noteId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $note = ClientNote::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->findOrFail($noteId);

        $note->update(['is_pinned' => ! $note->is_pinned]);

        return response()->json([
            'status' => true,
            'message' => 'Pin status updated successfully',
            'data' => $this->formatNote($note->fresh()->load('user')),
        ]);
    }

    // DELETE /agency/clients/{id}/notes
    public function destroy(Request $request, $clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:client_notes,id',
        ]);

        ClientNote::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->whereIn('id', $request->ids)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Notes deleted successfully',
            'data' => [],
        ]);
    }

    private function notifyAboutNote(ClientNote $note, string $clientName): void
    {
        if (empty($note->notify_admin_ids)) {
            return;
        }

        $author = auth('api')->user();

        $admins = User::where('agency_id', $note->agency_id)
            ->whereIn('id', $note->notify_admin_ids)
            ->get();

        $this->notifyUsers(
            $admins,
            'client_note_mention',
            'You were mentioned in a note',
            sprintf('%s mentioned you in a note about %s: "%s"', $author?->first_name ?? 'An admin', $clientName, $note->title),
            null,
            ['client_id' => $note->client_id, 'note_id' => $note->id]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNote(ClientNote $note): array
    {
        $notifyAdmins = empty($note->notify_admin_ids)
            ? collect()
            : User::whereIn('id', $note->notify_admin_ids)->get(['id', 'first_name', 'last_name', 'email']);

        return [
            'id' => $note->id,
            'title' => $note->title,
            'body' => $note->body,
            'is_pinned' => $note->is_pinned,
            'notify_admin_ids' => $note->notify_admin_ids ?? [],
            'notify_admins' => $notifyAdmins->map(fn (User $admin) => $this->formatAdmin($admin))->values(),
            'created_by' => $note->user ? $this->formatAdmin($note->user) : null,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAdmin(User $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => trim($admin->first_name.' '.$admin->last_name),
            'email' => $admin->email,
        ];
    }
}
