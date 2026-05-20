<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{

    public function roleList()
    {
        $user = auth('api')->user();

        $allowedRoles = collect();

        if ($user->hasRole('super_admin')) {
            $allowedRoles = collect(['super_admin', 'admin_staff']);
        }

        if ($user->hasRole('agency_admin')) {
            $allowedRoles = collect(['agency_admin', 'agency_staff']);
        }

        $roles = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', $allowedRoles)
            ->select('id', 'name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles fetched successfully.',
            'data'    => $roles
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        $allowedRoles = match (true) {

            $authUser->hasRole('super_admin') => [
                'super_admin',
                'admin_staff',
            ],

            $authUser->hasRole('agency_admin') => [
                'agency_admin',
                'agency_staff',
            ],

            $authUser->hasRole('admin_staff'),
            $authUser->hasRole('agency_staff') => [],

            default => null,
        };

        if (empty($allowedRoles)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to create users.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'image'      => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'role_id'    => ['required', 'integer', 'exists:roles,id'],
            'password'   => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $role = Role::find($validated['role_id']);

        if (!in_array($role->name, $allowedRoles)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to assign this role.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')
                    ->store('users', 'public');
            }

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'] ?? null,
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'] ?? null,
                'image'      => $imagePath,
                'agency_id'  => $authUser->agency_id,
                'password'   => Hash::make($validated['password']),
            ]);

            $user->assignRole($role->name);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'User created successfully.',
                'data' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'mobile' => $user->mobile,
                    'email' => $user->email,
                    'image' => $user->image,
                    'role' => $role->name,
                ]
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'User creation failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        $authUser = auth('api')->user();

        $user = User::with('roles')->findOrFail($id);

        if ($authUser->hasRole('agency_admin') && $authUser->agency_id !== $user->agency_id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'mobile'     => $user->mobile,
                'email'      => $user->email,
                'image'      => $user->image ? asset('storage/' . $user->image) : null,
                'role'       => $user->getRoleNames()->first(),
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $authUser = auth('api')->user();

        $allowedRoles = match (true) {

            $authUser->hasRole('super_admin') => [
                'super_admin',
                'admin_staff',
            ],

            $authUser->hasRole('agency_admin') => [
                'agency_admin',
                'agency_staff',
            ],

            $authUser->hasRole('admin_staff') ||
            $authUser->hasRole('agency_staff') => [],

            default => null,
        };

        if (!$allowedRoles) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update users.'
            ], 403);
        }

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'image'      => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'role_id'    => ['required', 'integer', 'exists:roles,id'],
            'password'   => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $role = Role::findOrFail($validated['role_id']);

        if (!in_array($role->name, $allowedRoles)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to assign this role.'
            ], 403);
        }

        DB::beginTransaction();

        try {

            $imagePath = $user->image;

            if ($request->hasFile('image')) {

                $oldImage = $user->getRawOriginal('image');

                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }

                $imagePath = $request->file('image')
                    ->store('users', 'public');
            }

            $user->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'] ?? null,
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'] ?? null,
                'image'      => $imagePath,
                'password'   => !empty($validated['password'])
                    ? Hash::make($validated['password'])
                    : $user->password,
            ]);

            $user->syncRoles([$role->name]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully.',
                'data' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'mobile' => $user->mobile,
                    'email' => $user->email,
                    'image' => $user->image,
                    'role' => $role->name,
                ]
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'User update failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function data(Request $request)
    {
        $authUser = auth('api')->user();

        $perPage = $request->get('per_page', 10);

        $query = User::whereHas('roles')
            ->with(['roles:id,name'])
            ->select('id','first_name','last_name','email','mobile','image','agency_id'
            );

        if (!$authUser->hasRole('super_admin')) {
            $query->where('agency_id', $authUser->agency_id);
        }

        $users = $query
            ->latest('id')
            ->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            return [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'mobile'     => $user->mobile,
                'image'      => $user->image,
                'agency_id'  => $user->agency_id,
                'role'       => $user->getRoleNames()->first(),
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => 'Users fetched successfully.',
            'data'    => $users->items(),

            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'from'         => $users->firstItem(),
                'to'           => $users->lastItem(),
            ]
        ]);
    }

    public function destroy($id)
    {
        $authUser = auth('api')->user();

        $user = User::findOrFail($id);

        if (
            $authUser->hasRole('agency_admin') &&
            $authUser->agency_id !== $user->agency_id
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        DB::beginTransaction();

        try {

            $image = $user->getRawOriginal('image');

            if ($image && Storage::disk('public')->exists($image)) {
                Storage::disk('public')->delete($image);
            }

            $user->syncRoles([]);
            // $user->syncPermissions([]);

            $user->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'User deleted successfully.'
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'User deletion failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updatePass(Request $request)
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status'  => true,
            'message' => 'Password updated successfully.',
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'mobile'     => 'nullable|string|max:20',
            'email'      => ['required','email','max:255',Rule::unique('users', 'email')->ignore($user->id)],
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'password'   => 'nullable|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $updateData = [
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'] ?? null,
            'mobile'     => $validated['mobile'] ?? null,
            'email'      => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('image')) {
            $oldImage = $user->getRawOriginal('image');

            if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }

            $updateData['image'] = $request->file('image')
                ->store('users', 'public');
        }

        $user->update($updateData);
        $user->refresh();

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully.',
            'data'    => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'mobile'     => $user->mobile,
                'email'      => $user->email,
                'image'      => $user->image,
                // 'role'       => $user->getRoleNames()->first(),
            ]
        ], 200);
    }

}
