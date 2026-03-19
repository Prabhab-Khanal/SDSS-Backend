<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\AccountRejected;
use App\Notifications\AccountSuspended;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved',
            'data'    => UserResource::collection($users)->response()->getData(true)['data'] ?? [],
            'meta'    => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User details',
            'data'    => new UserResource($user),
        ]);
    }

    public function approve(User $user): JsonResponse
    {
        if ($user->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'User is already approved.',
            ], 422);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change admin account status.',
            ], 422);
        }

        $user->update(['status' => 'approved']);

        $this->auditLog->log('user.approved', $user);
        $user->notify(new AccountApproved());

        return response()->json([
            'success' => true,
            'message' => 'User approved successfully',
            'data'    => new UserResource($user),
        ]);
    }

    public function reject(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change admin account status.',
            ], 422);
        }

        $user->update(['status' => 'rejected']);

        $this->auditLog->log('user.rejected', $user);
        $user->notify(new AccountRejected());

        return response()->json([
            'success' => true,
            'message' => 'User rejected',
            'data'    => new UserResource($user),
        ]);
    }

    public function suspend(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change admin account status.',
            ], 422);
        }

        if ($user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved users can be suspended.',
            ], 422);
        }

        $user->update(['status' => 'suspended']);

        $this->auditLog->log('user.suspended', $user);
        $user->notify(new AccountSuspended());

        return response()->json([
            'success' => true,
            'message' => 'User suspended',
            'data'    => new UserResource($user),
        ]);
    }
}
