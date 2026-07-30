<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyBusinessHour;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AgencyController extends Controller
{
    public function data(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status');

        $query = Agency::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subdomain_prefix', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
            $query->where('status', $status);
        }

        $agencies = $query->latest('id')->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Agencies retrieved successfully',
            'data' => $agencies->items(),
            'pagination' => [
                'current_page' => $agencies->currentPage(),
                'per_page' => $agencies->perPage(),
                'total' => $agencies->total(),
                'last_page' => $agencies->lastPage(),
            ],
            'total_agencies' => Agency::count(),
            'active_agencies' => Agency::where('status', 'active')->count(),
            'suspended_agencies' => Agency::where('status', 'suspended')->count(),
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
            'subdomain_prefix' => ['required', 'string', 'max:50', 'unique:agencies,subdomain_prefix', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $data = $validator->validated();
            $prefix = strtolower($data['subdomain_prefix']);
            $fullSubdomain = $prefix.'.staffhaus.io';

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

            // create default business hours for the agency
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

            foreach ($days as $day) {
                AgencyBusinessHour::create([
                    'agency_id' => $agency->id,
                    'day' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_open' => in_array($day, ['Saturday', 'Sunday']) ? false : true,
                ]);
            }
            // end of business hour creation

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
                    ],
                ],

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

        if (! $agency) {
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
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $agency = Agency::find($id);

        if (! $agency) {

            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:100', Rule::unique('agencies', 'email')->ignore($agency->id)],
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'max_users' => 'required|integer|min:1',
            'max_clients' => 'required|integer|min:1',
            'max_candidates' => 'required|integer|min:1',
            'status' => 'nullable|in:active,inactive,suspended',
            'subdomain_prefix' => ['required', 'string', 'max:50', Rule::unique('agencies', 'subdomain_prefix')->ignore($agency->id), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $data = $validator->validated();

            $prefix = strtolower($data['subdomain_prefix']);
            $fullSubdomain = $prefix.'.staffhaus.io';
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
                        'password' => Hash::make($request->password),
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
                    ] : null,
                ],

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

        if (! $agency) {
            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        $agency->delete();

        return response()->json([
            'status' => true,
            'message' => 'Agency deleted successfully',
        ]);
    }

    public function suspends($id)
    {
        $agency = Agency::find($id);

        if (! $agency) {
            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        $agency->update([
            'status' => $agency->status === 'suspended' ? 'active' : 'suspended',
        ]);

        return response()->json([
            'status' => true,
            'message' => $agency->status === 'suspended' ? 'Agency suspended successfully' : 'Agency unsuspended successfully',
            'data' => [
                'id' => $agency->id,
                'status' => $agency->status,
            ],
        ]);
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

        if (! $agency) {

            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'logo_height' => 'nullable|integer|min:20|max:500',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,ico,webp|max:1024',
            'website' => 'nullable|url|max:255',
            'font' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
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

                'logo' => $data['logo']
                    ?? $agency->getRawOriginal('logo'),
                'logo_height' => $data['logo_height']
                    ?? $agency->logo_height,
                'favicon' => $data['favicon']
                    ?? $agency->getRawOriginal('favicon'),
                'website' => $data['website']
                    ?? $agency->website,
                'font' => $data['font']
                    ?? $agency->font,
                'tax_id' => $data['tax_id']
                    ?? $agency->tax_id,
                'language' => $data['language']
                    ?? $agency->language,
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
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Agency update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function info()
    {
        $authUser = auth('api')->user();

        $agency = Agency::find($authUser->agency_id);

        if (! $agency) {

            return response()->json([
                'status' => false,
                'message' => 'Agency not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Agency information retrieved successfully',
            'data' => [
                'id' => $agency->id,
                'logo' => $agency->logo,
                'logo_height' => $agency->logo_height,
                'favicon' => $agency->favicon,

                'website' => $agency->website,
                'font' => $agency->font,
                'tax_id' => $agency->tax_id,
                'language' => $agency->language,

                'stripe_publishable_key' => $agency->stripe_publishable_key
                    ? substr($agency->stripe_publishable_key, 0, 10).'...'
                    : null,

                'stripe_secret_key' => $agency->stripe_secret_key
                    ? substr($agency->stripe_secret_key, 0, 10).'...'
                    : null,

                'stripe_webhook_secret' => $agency->stripe_webhook_secret
                    ? substr($agency->stripe_webhook_secret, 0, 10).'...'
                    : null,

                'subdomain' => $agency->subdomain,
            ],
        ]);
    }
}
