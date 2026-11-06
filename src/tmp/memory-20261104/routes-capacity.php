<?php

use App\Models\User;
use App\Support\WorkspacePreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

function capacityWebAuthorizeRequest(Request $request): void
{
    $expected = (string) env('CAPACITY_TOKEN');
    $supplied = $request->query('token');
    abort_unless((string) env('CAPACITY_ENABLED') === '1' && strlen($expected) >= 32
        && is_string($supplied) && hash_equals($expected, $supplied), 404);
}

Route::get('/__capacity/login/{userId}', function (Request $request, string $userId) {
    capacityWebAuthorizeRequest($request);
    abort_unless(ctype_digit($userId), 404);
    $user = User::findOrFail((int) $userId);
    abort_unless(str_starts_with($user->email, 'capacity-') && str_ends_with($user->email, '@example.test'), 403);
    $workspace = $request->query('workspace');
    abort_unless(is_string($workspace) && $user->canUseWorkspace($workspace), 403);
    Auth::login($user);
    $request->session()->regenerate();
    WorkspacePreference::select($request, $workspace);

    return response()->json(['user_id' => $user->id, 'workspace' => $user->activeWorkspace(),
        'session_id' => $request->session()->getId(), 'csrf_token' => $request->session()->token()])
        ->header('Cache-Control', 'private, no-store');
})->whereNumber('userId');

Route::get('/__capacity/task/{case}', function (Request $request, string $case) {
    capacityWebAuthorizeRequest($request);
    abort_unless(function_exists('capacityRunTask'), 503);

    return response()->json(capacityRunTask($case))->header('Cache-Control', 'private, no-store');
})->where('case', '[a-z0-9-]+');

Route::get('/__capacity/status', function (Request $request) {
    capacityWebAuthorizeRequest($request);

    return response()->json(['ok' => true, 'environment' => app()->environment(),
        'user_id' => $request->user()?->id, 'workspace' => $request->user()?->activeWorkspace(),
        'session_id' => $request->session()->getId(), 'php_version' => PHP_VERSION])
        ->header('Cache-Control', 'private, no-store');
});
