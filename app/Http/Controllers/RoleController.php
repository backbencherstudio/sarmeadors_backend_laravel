<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Spatie\Permission\Models\Role;
use App\Models\RoleHasPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('role.index');
    }

    public function data()
    {
        $roles = Role::with('permissions')->latest()->get();

        $data = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => ucfirst($role->name) . '(' . $role->guard_name . ')',
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function create()
    {
        $permissions = Permission::all();
        return view('role.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        // Log::info('Creating role with data', $request->all());

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(
                    fn ($q) => $q->where('guard_name', $request->guard_name)
                ),
            ],
            'guard_name' => 'required|in:web,api',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $role = Role::create([
                    'name' => strtolower($request->name),
                    'guard_name' => $request->guard_name,
                ]);

                // Log::info('Role created with ID: ' . $role->id);

                $role->syncPermissions(array_map('intval', $request->permissions));
            });

            return redirect()
                ->route('role.index')
                ->with('success', 'Role created successfully.');

        } catch (\Exception $e) {
            // Log::error('Role creation failed', ['error' => $e->getMessage()]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create role.');
        }
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::all();

        return view('role.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn ($q) => $q->where('guard_name', $role->guard_name)),
            ],
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
        ]);

        try {
            DB::transaction(function () use ($request, $role) {
                $role->update([
                    'name' => strtolower($request->name),
                ]);

                // Cast IDs to int for safety
                $role->syncPermissions(array_map('intval', $request->permissions));
            });

            return redirect()
                ->route('role.index')
                ->with('success', 'Role updated successfully.');

        } catch (\Exception $e) {
            Log::error('Role update failed', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update role.');
        }
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $assigned = DB::table('model_has_roles')->where('role_id', $role->id)->exists();

        if ($assigned) {
            return redirect()->back()->with('error', 'Cannot delete this role because it is assigned to one or more users.');
        }

        RoleHasPermission::where('role_id', $role->id)->delete();
        $role->delete();

        return redirect()->route('role.index')->with('success', 'Role deleted successfully.');
    }

}
