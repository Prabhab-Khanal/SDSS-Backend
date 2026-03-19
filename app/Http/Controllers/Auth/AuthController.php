<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SDSS API',
    description: 'Secure Document Sharing System API'
)]
#[OA\Server(url: 'http://localhost:8088/api', description: 'Local Dev')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class AuthController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog
    ) {}

    #[OA\Post(
        path: '/api/register',
        summary: 'Register a new user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'John'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registration submitted'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'password'   => $request->password,
            'status'     => 'pending',
            'role'       => 'user',
        ]);

        $this->auditLog->log('user.registered', $user);

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted. Awaiting admin approval.',
            'data'    => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/check-email',
        summary: 'Check if email is already registered',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Email availability result'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'success'   => true,
            'message'   => $exists ? 'Email is already taken.' : 'Email is available.',
            'data'      => [
                'available' => !$exists,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Login and receive JWT token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 403, description: 'Account not approved'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $token = auth('api')->attempt($request->only('email', 'password'));

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = auth('api')->user();

        if ($user->status !== 'approved') {
            auth('api')->logout();
            $message = match($user->status) {
                'pending'   => 'Account awaiting admin approval',
                'suspended' => 'Account has been suspended',
                'rejected'  => 'Registration was not approved',
                default     => 'Account not active',
            };
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        $this->auditLog->log('user.logged_in', $user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user'       => [
                    'id'         => $user->id,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email'      => $user->email,
                    'role'       => $user->role,
                ],
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout and invalidate token',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(): JsonResponse
    {
        $user = auth('api')->user();

        $this->auditLog->log('user.logged_out', $user);

        auth('api')->invalidate(true);

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ], 200);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Refresh JWT token',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token refreshed'),
        ]
    )]
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed',
            'data'    => [
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Get current authenticated user',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user data'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user',
            'data'    => auth('api')->user(),
        ], 200);
    }
}
