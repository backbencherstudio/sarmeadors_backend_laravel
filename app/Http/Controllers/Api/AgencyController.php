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
use Illuminate\Support\Facades\Storage;
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
                'total_users' => 1,
                'total_clients' => 0,
                'total_candidates' => 0,
            ]);

            $user = User::create([
                'first_name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'agency_id' => $agency->id,
                'is_owner' => 1,
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
        $agency = Agency::find($id);

        if (!$agency) {
            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $agency->id,
                'name' => $agency->name,
                'email' => $agency->email,
                'mobile' => $agency->mobile,
                'address' => $agency->address,
                'subdomain' => $agency->subdomain,
                'subdomain_prefix' => $agency->subdomain_prefix,
                'status' => $agency->status,
                'max_users' => $agency->max_users,
                'max_clients' => $agency->max_clients,
                'max_candidates' => $agency->max_candidates,
                'total_users' => $agency->total_users,
                'total_clients' => $agency->total_clients,
                'total_candidates' => $agency->total_candidates,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $agency = Agency::find($id);

        if (!$agency) {

            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => ['required','email', 'max:100', Rule::unique('agencies', 'email')->ignore($agency->id),],
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'max_users' => 'required|integer|min:1',
            'max_clients' => 'required|integer|min:1',
            'max_candidates' => 'required|integer|min:1',
            'status' => 'nullable|in:active,inactive,suspended',
            'subdomain_prefix' => [ 'required','string','max:50', Rule::unique('agencies', 'subdomain_prefix')->ignore($agency->id), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
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
            $agency->update([
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

            $admin = User::where('agency_id', $agency->id)
                ->where('is_owner', 1)
                ->first();

            if ($admin) {

                $admin->update([
                    'first_name' => $data['name'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'] ?? null,
                ]);

                if ($request->filled('password')) {
                    $admin->update([
                        'password' => Hash::make($request->password)
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Agency updated successfully',
                'data' => [
                    'agency' => [
                        'id' => $agency->id,
                        'name' => $agency->name,
                        'subdomain' => $agency->subdomain,
                        'status' => $agency->status,
                    ],

                    'admin' => $admin ? [
                        'id' => $admin->id,
                        'name' => $admin->first_name,
                        'email' => $admin->email,
                    ] : null
                ]

            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Agency update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $agency = Agency::find($id);

        if (!$agency) {

            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        if ($agency->total_clients > 0 || $agency->total_candidates > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete agency with existing clients or candidates',
            ], 400);
        }

        DB::beginTransaction();

        try {

            $users = User::where('agency_id', $agency->id)->get();

            foreach ($users as $user) {

                $user->syncRoles([]);
                $user->delete();
            }

            $agency->delete();

            DB::commit();

            return response()->json([

                'status' => true,

                'message' => 'Agency deleted successfully',

            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' => 'Agency deletion failed',

                'error' => $e->getMessage(),

            ], 500);
        }
    }

    public function infoUpdate(Request $request, $id)
    {
        $authUser = auth('api')->user();

        if ($authUser->agency_id != $id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        $agency = Agency::find($id);

        if (!$agency) {

            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'address' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,ico,webp|max:1024',
            'stripe_publishable_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'stripe_webhook_secret' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

            $data = $validator->validated();

            if ($request->hasFile('logo')) {

                if ($agency->getRawOriginal('logo')) {
                    Storage::disk('public')
                        ->delete($agency->getRawOriginal('logo'));
                }
                $data['logo'] = $request->file('logo')
                    ->store('agencies/logo', 'public');
            }

            if ($request->hasFile('favicon')) {

                if ($agency->getRawOriginal('favicon')) {
                    Storage::disk('public')
                        ->delete($agency->getRawOriginal('favicon'));
                }
                $data['favicon'] = $request->file('favicon')
                    ->store('agencies/favicon', 'public');
            }

            $agency->update([
                'address' => $data['address'] ?? $agency->address,
                'logo' => $data['logo']
                    ?? $agency->getRawOriginal('logo'),
                'favicon' => $data['favicon']
                    ?? $agency->getRawOriginal('favicon'),
                'stripe_publishable_key' => $data['stripe_publishable_key']
                    ?? $agency->stripe_publishable_key,
                'stripe_secret_key' => $data['stripe_secret_key']
                    ?? $agency->stripe_secret_key,
                'stripe_webhook_secret' => $data['stripe_webhook_secret']
                    ?? $agency->stripe_webhook_secret,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Agency information updated successfully',
                'data' => $agency->fresh(),
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Agency update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
