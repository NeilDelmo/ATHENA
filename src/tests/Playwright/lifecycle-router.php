<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$publicPath = dirname(__DIR__, 2).'/public';
$requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($requestPath !== '/' && is_file($publicPath.$requestPath)) {
    return false;
}

require __DIR__.'/lifecycle-clock.php';
define('LARAVEL_START', microtime(true));
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useStoragePath(getenv('LARAVEL_STORAGE_PATH'));

$app->booted(static function (): void {
    if (getenv('PLAYWRIGHT_FUNCTIONAL') === '1') {
        Route::middleware('web')->get('/__playwright/functional-state', static function (Request $request) {
            abort_unless(app()->environment('testing') && config('database.connections.mysql.database') === 'athena_playwright', 404);
            abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);
            $manifest = json_decode(file_get_contents(base_path('playwright/.auth/local/lifecycle.json')), true, flags: JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($manifest['key'], (string) $request->header('X-Playwright-Key')), 404);

            return response()->json(require __DIR__.'/functional-state.php');
        });
    }
    Route::middleware('web')->get('/__playwright/session/{actor}', static function (Request $request, string $actor) {
        abort_unless(app()->environment('testing') && config('database.connections.mysql.database') === 'athena_playwright', 404);
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);
        $manifest = json_decode(file_get_contents(base_path('playwright/.auth/local/lifecycle.json')), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($manifest['key'], (string) $request->header('X-Playwright-Key')) && isset($manifest['users'][$actor]), 404);
        $identity = $manifest['users'][$actor];
        $user = User::whereKey($identity['id'])->where('email', $identity['email'])->firstOrFail();
        $workspace = (string) $request->query('workspace', $identity['workspace']);
        abort_unless(array_key_exists($workspace, $user->availableWorkspaces()), 403);
        Auth::login($user);
        $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);

        return response()->noContent();
    });
});

$app->handleRequest(Request::capture());
