<?php

use App\Http\Controllers\AuthController;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

// Rate test api problem without Rate Limit
Route::get('/limit-problem', function () {

    $key = 'rate-test-count';

    $count = Redis::incr($key);

    return response()->json([
        'message' => 'Request received',
        'count' => $count,
        'container' => gethostname(),
    ]);
});

// Apply Rate limit
Route::get('/rate-test', function (Request $request) {

    $key = 'rate-test:' . $request->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => RateLimiter::availableIn($key),
        ], 429);
    }

    RateLimiter::hit($key, 60);

    return response()->json([
        'message' => 'Request received',
        'container' => gethostname(),
        'remaining' => RateLimiter::remaining($key, 5),
    ]);
});


/** 1. First create the problem
    Let's make an endpoint that always queries MySQL. **/
Route::get('/users-cache-test', function () {

    $start = microtime(true);

    $users = User::orderBy('id')->get();

    $time = round((microtime(true) - $start) * 1000, 2);

    return response()->json([
        'message' => 'Users fetched from MySQL',
        'users' => $users,
        'query_time_ms' => $time,
        'container' => gethostname(),
    ]);
});

/** 2. Use Redis Cache
    So Laravel can use Redis as its cache backend.
    No MySQL query is required for the second request.  **/
Route::get('/cache-redis', function () {
    $chunkSize = 1000;
    $cacheDuration = now()->addHour();

    User::orderBy('id')
        ->chunk($chunkSize, function ($users, $page) use ($cacheDuration) {

            Cache::put(
                "users-list:{$page}",
                $users->toArray(),
                $cacheDuration
            );
        });

    return response()->json([
        'message' => 'Users fetched',
        'container' => gethostname(),
    ]);
});

// Update user then forgot/flush the ols redis cache here
Route::put('/users/{user}', function (Request $request, User $user) {
    $validated = $request->validate([
        'name' => ['sometimes', 'required', 'string', 'max:255'],
        'email' => [
            'sometimes',
            'required',
            'email',
            'max:255',
            'unique:users,email,' . $user->id,
        ]
    ]);

    DB::transaction(function () use ($user, $validated) {

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        // Invalidate Redis cache after MySQL update
        Cache::forget('users-list');
    });

    return response()->json([
        'message' => 'User updated successfully',
        'user' => $user->fresh(),
    ]);
});

// Register a User
Route::post('/register', [AuthController::class, 'register']);


// SESSION-BASED AUTHENTICATION
// We use WEB middleware here - The middleware handles the session lifecycle and cookie automatically.
Route::middleware(StartSession::class)->group(function () {
    Route::post('/session-login', [AuthController::class, 'sessionLogin']);
    Route::get('/session-profile', function (Request $request) {
        return response()->json([
            'message' => 'Profile fetched successfully',
            'user' => Auth::user(),
            'session_id' => $request->session()->getId(),
            'container' => gethostname(),
        ]);
    });
});


// STATEFUL BEARER TOKEN AUTHENTICATION
Route::post('/token-login', [AuthController::class, 'tokenLogin']);
Route::middleware('auth:sanctum')->get('/token-profile', function (Request $request) {
    return response()->json([
        'message' => 'Token profile fetched successfully',
        'user' => $request->user(),
        'container' => gethostname(),
    ]);
});


// STALE LESS AUTH - WE USE JWT FOR STATELESS AUTHENTICATION
Route::post('/jwt-login', [AuthController::class, 'jwtLogin']);
Route::get('/jwt-profile', function (Request $request) {
    $authHeader = $request->header('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return response()->json([
            'message' => 'Bearer token required',
        ], 401);
    }

    $token = substr($authHeader, 7);

    try {
        $decoded = JWT::decode(
            $token,
            new Key(config('app.jwt_secret'), 'HS256')
        );

        $user = User::find($decoded->sub);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        return response()->json([
            'message' => 'JWT profile fetched successfully',
            'user' => $user,
            'container' => gethostname(),
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            //'message' => 'Invalid or expired token',
            'message' => $e->getMessage(),
            'exception' => get_class($e),
        ], 401);
    }
});
