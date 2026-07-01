<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateSecondaryLogin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CandidatePasswordController extends Controller
{
    private const PASSWORD_RULES = ['required', 'string', 'min:8', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/'];

    // GET /agency/candidates/{id}/password
    public function show($candidateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $primaryUser = User::where('email', $candidate->email)->where('agency_id', $agencyId)->first();

        $secondaryLogins = CandidateSecondaryLogin::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'has_account' => (bool) $primaryUser,
                'secondary_logins' => $secondaryLogins->map(fn (CandidateSecondaryLogin $login) => $this->formatSecondaryLogin($login))->values(),
            ],
        ]);
    }

    /**
     * Manually set the candidate's portal password — creates the portal
     * account (using the candidate's existing email) if one doesn't exist yet.
     */
    // PATCH /agency/candidates/{id}/password
    public function updatePassword(Request $request, $candidateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $data = $request->validate(['password' => self::PASSWORD_RULES]);

        $user = User::where('email', $candidate->email)->where('agency_id', $agencyId)->first();

        if ($user) {
            $user->update(['password' => $data['password']]);
        } else {
            $user = User::create([
                'agency_id' => $agencyId,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'mobile' => $candidate->mobile,
                'password' => $data['password'],
            ]);
            $user->assignRole('candidate');
            $candidate->update(['user_id' => $user->id]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Add a secondary email+password credential for this candidate.
     * No new User row is created — the credential points to the candidate's
     * existing primary User (auto-created if absent) so that logging in
     * with the secondary email still returns the primary user's JWT.
     */
    // POST /agency/candidates/{id}/secondary-logins
    public function storeSecondaryLogin(Request $request, $candidateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

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
        $primaryUser = User::where('email', $candidate->email)->where('agency_id', $agencyId)->first();

        if (! $primaryUser) {
            $primaryUser = User::create([
                'agency_id' => $agencyId,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'mobile' => $candidate->mobile,
                'password' => str()->random(24),
            ]);
            $primaryUser->assignRole('candidate');
            $candidate->update(['user_id' => $primaryUser->id]);
        }

        $secondaryLogin = CandidateSecondaryLogin::create([
            'agency_id' => $agencyId,
            'candidate_id' => $candidate->id,
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

    // DELETE /agency/candidates/{id}/secondary-logins/{loginId}
    public function destroySecondaryLogin($candidateId, $loginId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $secondaryLogin = CandidateSecondaryLogin::where('candidate_id', $candidate->id)
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
    private function formatSecondaryLogin(CandidateSecondaryLogin $secondaryLogin): array
    {
        return [
            'id' => $secondaryLogin->id,
            'email' => $secondaryLogin->email,
            'created_at' => $secondaryLogin->created_at,
        ];
    }
}
