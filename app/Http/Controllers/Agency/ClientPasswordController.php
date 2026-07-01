<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientSecondaryLogin;
use App\Models\User;
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

        $primaryUser = User::where('email', $client->email)->where('agency_id', $agencyId)->first();

        $secondaryLogins = ClientSecondaryLogin::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'has_account' => (bool) $primaryUser,
                'secondary_logins' => $secondaryLogins->map(fn (ClientSecondaryLogin $login) => $this->formatSecondaryLogin($login))->values(),
            ],
        ]);
    }

    /**
     * Manually set the client's portal password — creates the portal
     * account (using the client's existing email) if one doesn't exist yet.
     */
    // PATCH /agency/clients/{id}/password
    public function updatePassword(Request $request, $clientId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($clientId);

        $data = $request->validate(['password' => self::PASSWORD_RULES]);

        $user = User::where('email', $client->email)->where('agency_id', $agencyId)->first();

        if ($user) {
            $user->update(['password' => $data['password']]);
        } else {
            $user = User::create([
                'agency_id' => $agencyId,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'mobile' => $client->mobile,
                'password' => $data['password'],
            ]);
            $user->assignRole('client');
            $client->update(['user_id' => $user->id]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Add a secondary email+password credential for this client.
     * No new User row is created — the credential points to the client's
     * existing primary User (auto-created if absent) so that logging in
     * with the secondary email still returns the primary user's JWT.
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

        // Ensure a primary portal User exists so user_id FK can be set.
        $primaryUser = User::where('email', $client->email)->where('agency_id', $agencyId)->first();

        if (! $primaryUser) {
            $primaryUser = User::create([
                'agency_id' => $agencyId,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'mobile' => $client->mobile,
                'password' => str()->random(24),
            ]);
            $primaryUser->assignRole('client');
            $client->update(['user_id' => $primaryUser->id]);
        }

        $secondaryLogin = ClientSecondaryLogin::create([
            'agency_id' => $agencyId,
            'client_id' => $client->id,
            'user_id' => $primaryUser->id,
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
