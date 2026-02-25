<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\RoleHasPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    //Pemission
    public function index()
    {
        return view('permission.index');
    }

    public function data()
    {
        $permissions = Permission::query()
            ->orderBy('group_name')
            ->orderBy('guard_name')
            ->orderBy('permission_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $permissions
        ]);
    }

    public function create()
    {
        $groups = PermissionGroup::orderBy('group_name')->get();
        return view('permission.create', compact('groups'));
    }

    public function store(Request $request)
    {
        // Log::info($request->all());
        $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.route_name' => 'required|string',
            'permissions.*.permission_name' => 'required|string',
            'permissions.*.permission_type' => 'required|in:view,create,edit,destroy',
            'permissions.*.group_name' => 'required|string',
            'permissions.*.guard_name' => 'required|string',
        ]);

        $duplicates = false;

        foreach ($request->permissions as $permission) {

            // if ($permission['permission_type'] === 'delete') {
            //     $permission['permission_type'] = 'destroy';
            // }

            $created = Permission::firstOrCreate(
                ['name' => $permission['route_name']],
                $permission
            );

            if (!$created->wasRecentlyCreated) {
                $duplicates = true;
            }
        }

        $message = 'Permissions saved successfully';
        if ($duplicates) {
            $message .= ' (duplicate routes ignored)';
        }

        return redirect()
            ->route('permission.index')
            ->with('success', $message);
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);
        $groups = PermissionGroup::orderBy('group_name')->get();
        return view('permission.edit', compact('groups','permission'));
    }

    public function update(Request $request, $id)
    {
        // Log::info($request->all());
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:permissions,name,' . $permission->id],
            'permission_name' => ['required', 'string', 'max:100'],
            'permission_type' => ['required', 'in:view,create,edit,destroy'],
            'group_name' => ['required', 'string', 'max:100'],
            'guard_name' => ['required', 'string', 'max:5'],

        ]);

        // $type = $request->permission_type === 'delete' ? 'destroy' : $request->permission_type;

        $permission->update([
            'name' => $request->name,
            'permission_name' => $request->permission_name,
            'permission_type' => $request->permission_type,
            'group_name' => $request->group_name,
            'guard_name' => $request->guard_name,
        ]);

        return redirect()->route('permission.index')->with('success', 'Updated successfully.');
    }

    public function destroy($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return redirect()->back()->with('error', 'Permission not found.');
        }

        $assigned = RoleHasPermission::where('permission_id', $permission->id)->exists();

        if ($assigned) {
            return redirect()->back()->with('error', 'Cannot delete this permission because it is assigned to a role.');
        }

        $permission->delete();

        return redirect()->route('permission.index')->with('success', 'Deleted successfully.');
    }


    //Pemission Group
    public function groupIndex()
    {
        return view('permission.groupIndex');
    }

    public function groupData()
    {
        $group = PermissionGroup::all();

        return response()->json(['data' => $group]);
    }

    public function groupCreate()
    {
        return view('permission.groupCreate');
    }

    public function groupStore(Request $request)
    {
        $request->validate([
            'group_name' => ['required', 'string', 'max:100', 'unique:permission_groups,group_name'],
        ]);

        PermissionGroup::create([
            'group_name' => $request->group_name,
        ]);

        return redirect()->route('permission.group.index')->with('success', 'Ceated successfully.');
    }

    public function groupEdit($id)
    {
        $group = PermissionGroup::findOrFail($id);
        return view('permission.groupEdit', compact('group'));
    }

    public function groupUpdate(Request $request, $id)
    {
        $group = PermissionGroup::findOrFail($id);

        $request->validate([
            'group_name' => ['required', 'string', 'max:100', 'unique:permission_groups,group_name,' . $group->id],
        ]);

        $group->update([
            'group_name' => $request->group_name,
        ]);

        return redirect()->route('permission.group.index')->with('success', 'Updated successfully.');
    }

    public function groupDestroy($id)
    {
        $group = PermissionGroup::find($id);

        if (!$group) {
            return redirect()->back()->with('error', 'Permission group not found.');
        }

        $hasPermissions = Permission::where('group_name', $group->group_name)->exists();

        if ($hasPermissions) {
            return redirect()->back()->with('error', 'Cannot delete this group because it has associated permissions.');
        }

        $group->delete();

        return redirect()->route('permission.group.index')->with('success', 'Deleted successfully.');
    }

}
