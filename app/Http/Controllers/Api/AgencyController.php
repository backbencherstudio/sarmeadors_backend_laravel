<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AgencyController extends Controller
{

    public function data()
    {
        $agencies = Agency::all();

        return response()->json([
            'status' => true,
            'message' => 'Agencies retrieved successfully',
            'data' => $agencies
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email|unique:agencies,email',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:6|confirmed',
            'max_users' => 'required|integer|min:1',
            'max_clients' => 'required|integer|min:1',
            'max_candidates' => 'required|integer|min:1',
            'status' => 'nullable|in:active,inactive,suspended',
            'subdomain_prefix' => ['required', 'string','max:50','unique:agencies,subdomain_prefix','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            $data = $validator->validated();
            $prefix = strtolower($data['subdomain_prefix']);
            $fullSubdomain = $prefix . '.staffhaus.io';

            $agency = Agency::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'address' => $data['address'] ?? null,

                'subdomain' => $fullSubdomain,
                'subdomain_prefix' => $prefix,

                'status' => $data['status'] ?? 'active',

                'max_users' => $data['max_users'],
                'max_clients' => $data['max_clients'],
                'max_candidates' => $data['max_candidates'],
            ]);

            $user = User::create([
                'first_name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'agency_id' => $agency->id,
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('agency_admin');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Agency created successfully',
                'data' => [
                    'agency' => [
                        'id' => $agency->id,
                        'name' => $agency->name,
                        'subdomain' => $agency->subdomain,
                        'status' => $agency->status,
                    ],

                    'admin' => [
                        'id' => $user->id,
                        'name' => $user->first_name,
                        'email' => $user->email,
                    ]
                ]

            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Agency creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit($id)
    {
        $agency = Agency::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'agency' => $agency,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $agency = Agency::findOrFail($id);

            $user = User::where('agency_id', $agency->id)
                        ->role('agency_admin')
                        ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:100',
                'last_name'  => 'nullable|string|max:100',
                'email'      => ['required','email', Rule::unique('users', 'email')->ignore($user->id), ],
                'mobile'     => 'nullable|string|max:20',
                'address'    => 'nullable|string|max:1000',
                'password'   => 'nullable|min:6|confirmed',
                'subdomain'  => ['required','string', Rule::unique('agencies', 'subdomain')->ignore($agency->id), ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $parts = explode('.', $data['subdomain']);
            $prefix = $parts[0];

            $agency->update([
                'name'             => $data['name'],
                'email'            => $data['email'],
                'mobile'           => $data['mobile'] ?? null,
                'address'          => $data['address'] ?? null,
                'subdomain'        => $data['subdomain'],
                'subdomain_prefix' => $prefix,
            ]);

            $user->update([
                'first_name' => $data['name'],
                'last_name'  => null,
                'email'      => $data['email'],
                'mobile'     => $data['mobile'] ?? null,
            ]);

            if (!empty($data['password'])) {
                $user->update([
                    'password' => Hash::make($data['password'])
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Agency updated successfully',
                'data' => [
                    'agency_id' => $agency->id,
                    'agency_name' => $agency->name,
                    'subdomain' => $agency->subdomain,
                    'agency_admin_name' => $user->first_name . ' ' . $user->last_name,
                    'agency_email' => $user->email,
                ]
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Agency update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $agency = Agency::findOrFail($id);
            $authUser = auth('api')->user();

            if ($authUser->agency_id == $agency->id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You cannot delete your own agency.'
                ], 403);
            }

            $agencyUsers = $agency->users()->get();

            foreach ($agencyUsers as $user) {
                $user->roles()->detach();
                $user->delete();
            }

            $agency->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Agency deleted successfully.'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Agency deletion failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function infoUpdate(Request $request)
    {
        $authUser = auth('api')->user();
        $agency = $authUser->agency;

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => ['required','email', Rule::unique('users', 'email')->ignore($authUser->id), ],
            'mobile'     => 'nullable|string|max:20',
            'address'    => 'nullable|string|max:1000',
            'password'   => 'nullable|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $agency->update([
            'name' => $data['name'],
            'email'      => $data['email'],
            'mobile'     => $data['mobile'] ?? null,
            'address'    => $data['address'] ?? null,
        ]);

        if (!empty($data['password'])) {
            $authUser->update([
                'password' => Hash::make($data['password'])
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Agency information updated successfully'
        ]);
    }

}
