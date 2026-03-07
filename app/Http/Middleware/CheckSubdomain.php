<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;

class CheckSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        if ($user && ($user->hasRole('super_admin') || $user->hasRole('admin_staff'))) {
            return $next($request);
        }

        if (!$user) {
            $email = $request->input('email');
            if ($email) {
                $loginUser = User::where('email', $email)->first();
                if ($loginUser && ($loginUser->hasRole('super_admin') || $loginUser->hasRole('admin_staff'))) {
                    return $next($request);
                }
            }
        }

        $subdomain = $request->header('X-Subdomain');

        if (!$subdomain) {
            return response()->json([
                'status' => false,
                'message' => 'Subdomain is required.'
            ], 400);
        }

        $agency = Agency::where('subdomain_prefix', $subdomain)->first();

        if (!$agency) {
            return response()->json([
                'status' => false,
                'message' => 'Agency not found for this subdomain.'
            ], 404);
        }

        $request->merge(['current_agency' => $agency]);

        return $next($request);
    }
}
