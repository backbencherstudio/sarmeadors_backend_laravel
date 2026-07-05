<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateSecondaryLogin;
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

        $secondaryLogins = CandidateSecondaryLogin::where('candidate_id', $candidate->id)
            ->where('agency_id', $agencyId)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'has_account' => true,
                'secondary_logins' => $secondaryLogins->map(fn (CandidateSecondaryLogin $login) => $this->formatSecondaryLogin($login))->values(),
            ],
        ]);
    }

    /**
     * Manually set the candidate's portal password. Every candidate already has
     * a portal account (auto-created with a default password when the
     * candidate was created), so this simply overwrites that account's password.
     */
    // PATCH /agency/candidates/{id}/password
    public function updatePassword(Request $request, $candidateId)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($candidateId);

        $data = $request->validate(['password' => self::PASSWORD_RULES]);

        $candidate->user->update(['password' => $data['password']]);

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Add a secondary email+password credential for this candidate.
     * No new User row is created — the credential points to the candidate's
     * existing primary User so that logging in with the secondary email
     * still returns the primary user's JWT.
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

        $secondaryLogin = CandidateSecondaryLogin::create([
            'agency_id' => $agencyId,
            'candidate_id' => $candidate->id,
            'user_id' => $candidate->user_id,
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
