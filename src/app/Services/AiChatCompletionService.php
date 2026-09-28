<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;

class AiChatCompletionService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(array $payload, bool $usesVision, int $connectTimeout, int $timeout): Response
    {
        $providers = $this->providers($usesVision);

        if ($providers === []) {
            throw new LogicException('No AI provider is configured.');
        }

        $lastConnectionException = null;

        foreach ($providers as $index => $provider) {
            try {
                $response = Http::baseUrl($provider['base_url'])
                    ->withToken($provider['key'])
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->post('chat/completions', [
                        ...$payload,
                        'model' => $provider['model'],
                    ]);
            } catch (ConnectionException $exception) {
                $lastConnectionException = $exception;

                if (! isset($providers[$index + 1])) {
                    throw $exception;
                }

                Log::warning('AI provider connection failed; trying fallback provider.', [
                    'provider' => $provider['name'],
                    'exception' => $exception::class,
                ]);

                continue;
            }

            if ($response->successful() || ! $this->shouldFailOver($response) || ! isset($providers[$index + 1])) {
                return $response;
            }

            Log::warning('AI provider request failed; trying fallback provider.', [
                'provider' => $provider['name'],
                'status' => $response->status(),
                'request_id' => $response->header('x-request-id'),
            ]);
        }

        throw $lastConnectionException ?? new LogicException('No AI provider response was returned.');
    }

    public function isConfigured(bool $usesVision = false): bool
    {
        return $this->providers($usesVision) !== [];
    }

    /** @return list<array{name: string, key: string, model: string, base_url: string}> */
    private function providers(bool $usesVision): array
    {
        return collect([
            $this->provider('openrouter', $usesVision),
            $this->provider('gemini', $usesVision),
        ])->filter()->values()->all();
    }

    /** @return array{name: string, key: string, model: string, base_url: string}|null */
    private function provider(string $name, bool $usesVision): ?array
    {
        $key = trim((string) config("services.{$name}.key"));
        $modelKey = $usesVision ? 'vision_model' : 'model';
        $model = trim((string) config("services.{$name}.{$modelKey}", config("services.{$name}.model")));
        $baseUrl = trim((string) config("services.{$name}.base_url"));

        if ($key === '' || $model === '' || $baseUrl === '') {
            return null;
        }

        return [
            'name' => $name,
            'key' => $key,
            'model' => $model,
            'base_url' => $baseUrl,
        ];
    }

    private function shouldFailOver(Response $response): bool
    {
        return $response->status() === 408
            || $response->status() === 429
            || $response->serverError();
    }
}
