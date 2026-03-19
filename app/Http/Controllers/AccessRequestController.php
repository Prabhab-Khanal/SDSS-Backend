<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccessRequest\RejectAccessRequestRequest;
use App\Http\Requests\AccessRequest\StoreAccessRequestRequest;
use App\Http\Resources\AccessRequestResource;
use App\Http\Resources\AuthorizationTokenResource;
use App\Models\AccessRequest;
use App\Models\File;
use App\Services\AccessRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessRequestController extends Controller
{
    public function __construct(
        private AccessRequestService $accessRequestService
    ) {}

    public function store(StoreAccessRequestRequest $request, File $file): JsonResponse
    {
        $accessRequest = $this->accessRequestService->createRequest(
            auth('api')->user(),
            $file,
            $request->message
        );

        $accessRequest->load(['file', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Access request submitted',
            'data'    => new AccessRequestResource($accessRequest),
        ], 201);
    }

    public function outgoing(Request $request): JsonResponse
    {
        $query = AccessRequest::where('requester_id', auth('api')->id())
            ->with(['file.user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Outgoing requests retrieved',
            'data'    => AccessRequestResource::collection($requests),
            'meta'    => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'per_page'     => $requests->perPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    public function incoming(Request $request): JsonResponse
    {
        $query = AccessRequest::whereHas('file', function ($q) {
                $q->where('user_id', auth('api')->id());
            })
            ->with(['file', 'requester']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Incoming requests retrieved',
            'data'    => AccessRequestResource::collection($requests),
            'meta'    => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'per_page'     => $requests->perPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    public function approve(AccessRequest $accessRequest): JsonResponse
    {
        $this->authorize('approve', $accessRequest);

        if ($accessRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $token = $this->accessRequestService->approve($accessRequest);

        return response()->json([
            'success' => true,
            'message' => 'Access request approved. Token valid for 5 minutes.',
            'data'    => [
                'access_request'      => new AccessRequestResource($accessRequest->fresh(['file', 'requester'])),
                'authorization_token' => new AuthorizationTokenResource($token),
            ],
        ]);
    }

    public function reject(RejectAccessRequestRequest $request, AccessRequest $accessRequest): JsonResponse
    {
        $this->authorize('reject', $accessRequest);

        if ($accessRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $this->accessRequestService->reject($accessRequest, $request->rejection_reason);

        return response()->json([
            'success' => true,
            'message' => 'Access request rejected',
            'data'    => new AccessRequestResource($accessRequest->fresh(['file', 'requester'])),
        ]);
    }
}
