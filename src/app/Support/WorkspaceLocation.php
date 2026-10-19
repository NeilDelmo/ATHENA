<?php

namespace App\Support;

use Illuminate\Http\Request;

class WorkspaceLocation
{
    public static function key(int $userId, string $workspace): string
    {
        return 'workspace_locations.'.$userId.'.'.$workspace;
    }

    public static function safeUrl(Request $request, mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\\\\]/', $value)) {
            return null;
        }
        $base = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
        $url = str_starts_with($value, '/') && ! str_starts_with($value, '//') ? $request->getSchemeAndHttpHost().$value : $value;
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)) {
            return null;
        }
        if (! str_starts_with($url, $base.'/') && $url !== $base) {
            return null;
        }

        return $url;
    }

    public static function saved(Request $request, string $workspace): ?string
    {
        $saved = $request->session()->get(self::key($request->user()->id, $workspace));
        if (! is_array($saved) || ($saved['saved_at'] ?? 0) < time() - 86400) {
            return null;
        }

        return self::safeUrl($request, $saved['url'] ?? null);
    }

    public static function destination(Request $request, string $workspace, mixed $browserLocation = null): string
    {
        $savedUrl = self::saved($request, $workspace);
        if (! $savedUrl) {
            return route($request->user()->dashboardRouteName($workspace));
        }
        $browserUrl = self::safeUrl($request, $browserLocation);
        $destination = $browserUrl && parse_url($browserUrl, PHP_URL_PATH) === parse_url($savedUrl, PHP_URL_PATH) ? $browserUrl : $savedUrl;
        $request->session()->put('workspace_resume', ['user_id' => $request->user()->id, 'workspace' => $workspace, 'url' => $destination]);

        return $destination;
    }
}
