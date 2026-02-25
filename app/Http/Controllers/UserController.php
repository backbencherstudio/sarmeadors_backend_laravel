<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function create()
    {
        $roles = Role::all();
        return view('user.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password'   => ['required', 'confirmed', Password::defaults()],
            'role'       => ['required', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($request) {

            $user = User::create([
                'name'       => $request->name,
                'email'      => $request->email,
                'mobile'     => $request->mobile,
                'department' => $request->department,
                'password'   => Hash::make($request->password),
            ]);

            DB::table('model_has_roles')->insert([
                'role_id'    => $request->role,
                'model_id'   => $user->id,
                'model_type' => User::class,
            ]);
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function index()
    {
        return view('user.index');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        $roles = Role::all();

        $currentRoleId = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->value('role_id');

        return view('user.edit', compact('user', 'roles', 'currentRoleId'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:100'],
            'password'   => ['nullable', 'confirmed', 'min:6'],
            'role'       => ['required', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($request, $user) {

            $user->update([
                'name'       => $request->name,
                'email'      => $request->email,
                'mobile'     => $request->mobile,
                'department' => $request->department,
            ]);

            if ($request->filled('password')) {
                $user->update([
                    'password' => Hash::make($request->password),
                ]);
            }

            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', User::class)
                ->delete();

            DB::table('model_has_roles')->insert([
                'role_id'    => $request->role,
                'model_id'   => $user->id,
                'model_type' => User::class,
            ]);
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function userData()
    {
        $users = User::with('roles')->get();

        $users = $users->map(function ($user) {
            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'mobile'     => $user->mobile ?? '-',
                'department' => $user->department ?? '-',
                'role'       => $user->roles
                    ->map(fn ($r) => ucfirst($r->name) . ' (' . $r->guard_name . ')')
                    ->implode(', '),
                ];
        });

        return response()->json(['data' => $users]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }
        try {
            DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
            $user->delete();

            return redirect()->back()->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function profile()
    {
        return view('auth.reset-password');
    }

    public function updatePass(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return redirect()->route('profile.view')->with('success', 'Password updated successfully!');
    }

    public function profileUpdate(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . auth()->id(),
            'mobile'     => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
        ]);

        auth()->user()->update($request->only(
            'name',
            'email',
            'mobile',
            'department'
        ));

        return back()->with('success', 'Profile updated successfully.');
    }


    //Api
    public function apiData(Request $request)
    {
        // Log::info($request);

        $user = auth('api')->user();
        // Log::info($user);
        $users = User::with('roles')->get()->map(function ($user) {
            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'mobile'     => $user->mobile ?? '-',
                'department' => $user->department ?? '-',
                'status'     => $user->status,
                'role'       => $user->roles->map(fn($r) => ucfirst($r->name))->implode(', '),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Users fetched successfully.',
            'data'    => $users
        ], 200);
    }

}
