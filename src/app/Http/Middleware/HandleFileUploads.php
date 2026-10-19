<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class HandleFileUploads
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('X-Athena-Upload-ID') || $request->isMethodSafe()
            || $request->allFiles() === [] || ! $request->user() || ! $request->hasSession()
            || $request->routeIs('*.preview', '*.download', '*.print', '*.pdf')) {
            return $next($request);
        }

        if (! Str::isUuid($request->header('X-Athena-Upload-ID'))) {
            return response()->json(['message' => 'The upload identifier is invalid. Reload the page and try again.'], 422);
        }

        $formRequestClass = $this->formRequestClass($request);
        if ($request->header('X-Athena-Upload-Retry') === '1' && $formRequestClass === null) {
            return response()->json([
                'code' => 'upload_retry_unavailable',
                'message' => 'The connection ended before this upload was confirmed. Check whether it was saved before uploading it again.',
            ], 409);
        }

        $key = 'athena-upload:'.hash('sha256', json_encode([
            $request->user()->getAuthIdentifier(), $request->session()->getId(),
            $request->user()->activeWorkspace(),
            $request->method(), $request->fullUrl(), $request->header('X-Athena-Upload-ID'),
            $request->header('X-Athena-Upload-Form'),
        ]));
        $lock = Cache::lock($key.':lock', 600);
        if (! $lock->get()) {
            return response()->json([
                'code' => 'upload_in_progress', 'message' => 'This upload is still being saved. Please wait a moment.',
            ], 409, ['Retry-After' => '2']);
        }

        try {
            $saved = Cache::get($key);
            if (is_array($saved)) {
                if ($formRequestClass === null) {
                    return response()->json([
                        'code' => 'upload_retry_unavailable',
                        'message' => 'This upload may already be saved. Check its saved state before uploading it again.',
                    ], 409);
                }
                $this->authorizeReplay($request, $formRequestClass);
                foreach ($saved['flash'] as $name => $value) {
                    $request->session()->flash($name, $value);
                }

                return new Response($saved['content'], $saved['status'], $saved['headers']);
            }

            $response = $this->nativeResponse($request, $next($request));
            if ($response->isSuccessful() && str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
                $flash = array_diff($request->session()->get('_flash.new', []), ['errors', '_old_input']);
                Cache::put($key, [
                    'content' => $response->getContent(), 'status' => $response->getStatusCode(),
                    'headers' => ['Content-Type' => $response->headers->get('Content-Type')],
                    'flash' => $request->session()->only($flash),
                ], now()->addMinutes(15));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /** @return class-string<FormRequest>|null */
    private function formRequestClass(Request $request): ?string
    {
        $controller = $request->route()?->getControllerClass();
        if (! $controller) {
            return null;
        }

        $method = new ReflectionMethod($controller, $request->route()->getActionMethod());
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)
                && method_exists($type->getName(), 'authorize')) {
                return $type->getName();
            }
        }

        return null;
    }

    /** @param class-string<FormRequest> $formRequestClass */
    private function authorizeReplay(Request $request, string $formRequestClass): void
    {
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                $policy = Gate::getPolicyFor($parameter);
                if ($policy && method_exists($policy, 'view')) {
                    Gate::forUser($request->user())->authorize('view', $parameter);
                }
            }
        }

        $formRequest = $formRequestClass::createFrom($request)->setContainer(app());
        $authorized = app()->call([$formRequest, 'authorize']);
        if ($authorized instanceof AuthorizationResponse) {
            $authorized->authorize();
        } else {
            abort_unless($authorized, 403);
        }
    }

    private function nativeResponse(Request $request, Response $response): Response
    {
        if ($request->header('X-Athena-Upload-Form') !== '1' || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $errors = $request->session()->get('errors');
        if ($errors instanceof ViewErrorBag && in_array('errors', $request->session()->get('_flash.new', []), true)) {
            $messages = [];
            foreach ($errors->getBags() as $bag) {
                $messages = array_merge_recursive($messages, $bag->messages());
            }

            return response()->json(['message' => 'Check the highlighted fields and try again.', 'errors' => $messages], 422);
        }

        $target = $response->getTargetUrl();
        $absolute = str_starts_with($target, '/') && ! str_starts_with($target, '//') ? $request->getSchemeAndHttpHost().$target : $target;
        $origin = parse_url($absolute);
        $current = parse_url($request->getSchemeAndHttpHost());
        if (! is_array($origin) || ($origin['scheme'] ?? null) !== ($current['scheme'] ?? null)
            || ($origin['host'] ?? null) !== ($current['host'] ?? null)
            || ($origin['port'] ?? null) !== ($current['port'] ?? null)) {
            return response()->json(['message' => 'The upload could not return to this application. Reload the page to check its saved state.'], 422);
        }

        return new JsonResponse(['redirect' => $absolute]);
    }
}
