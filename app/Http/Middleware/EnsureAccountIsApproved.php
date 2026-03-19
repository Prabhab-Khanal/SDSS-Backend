<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $fresh = User::find($user->id);

        return match($fresh->status) {
            'approved'  => $next($request),
            'pending'   => response()->json(['success' => false, 'message' => 'Account awaiting admin approval'], 403),
            'suspended' => response()->json(['success' => false, 'message' => 'Account has been suspended'], 403),
            'rejected'  => response()->json(['success' => false, 'message' => 'Registration was not approved'], 403),
            default     => response()->json(['success' => false, 'message' => 'Account status invalid'], 403),
        };
    }
}
