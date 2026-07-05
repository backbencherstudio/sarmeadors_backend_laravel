<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientSecondaryLogin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientPasswordController extends Controller
{
    private const PASSWORD_RULES = ['required', 'string', 'min:8', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/'];

    // GET /agency/clients/{id}/password
    public function show($clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $secondaryLogins = ClientSecondaryLogin::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'has_account' => true,
                'secondary_logins' => $secondaryLogins->map(fn (ClientSecondaryLogin $login) => $this->formatSecondaryLogin($login))->values(),
            ],
        ]);
    }

    /**
     * Manually set the client's portal password. Every client already has a
     * portal account (auto-created with a default password when the client
     * was created), so this simply overwrites that account's password.
     */
    // PATCH /agency/clients/{id}/password
    public function updatePassword(Request $request, $clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $data = $request->validate(['password' => self::PASSWORD_RULES]);

        $client->user->update(['password' => $data['password']]);

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Add a secondary email+password credential for this client.
     * No new User row is created — the credential points to the client's
     * existing primary User so that logging in with the secondary email
     * still returns the primary user's JWT.
     */
    // POST /agency/clients/{id}/secondary-logins
    public function storeSecondaryLogin(Request $request, $clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $data = $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email'),
                Rule::unique('client_secondary_logins', 'email'),
                Rule::unique('candidate_secondary_logins', 'email'),
            ],
            'password' => self::PASSWORD_RULES,
        ]);

        $secondaryLogin = ClientSecondaryLogin::create([
            'agency_id' => $agencyId,
            'client_id' => $client->id,
            'user_id' => $client->user_id,
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Secondary login added successfully',
            'data' => $this->formatSecondaryLogin($secondaryLogin),
        ]);
    }

    // DELETE /agency/clients/{id}/secondary-logins/{loginId}
    public function destroySecondaryLogin($clientId, $loginId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $secondaryLogin = ClientSecondaryLogin::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->findOrFail($loginId);

        $secondaryLogin->delete();

        return response()->json([
            'status' => true,
            'message' => 'Secondary login removed successfully',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSecondaryLogin(ClientSecondaryLogin $secondaryLogin): array
    {
        return [
            'id' => $secondaryLogin->id,
            'email' => $secondaryLogin->email,
            'created_at' => $secondaryLogin->created_at,
        ];
    }
}
