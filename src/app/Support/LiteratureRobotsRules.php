<?php

namespace App\Support;

class LiteratureRobotsRules
{
    /** @return array{allowed: bool, delay: int} */
    public function evaluate(string $body, string $url): array
    {
        $groups = [];
        $group = ['agents' => [], 'rules' => [], 'delay' => 0];
        $hasDirectives = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);

            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);

            if ($key === 'user-agent') {
                if ($hasDirectives) {
                    $groups[] = $group;
                    $group = ['agents' => [], 'rules' => [], 'delay' => 0];
                    $hasDirectives = false;
                }

                $group['agents'][] = strtolower($value);
            } elseif ($group['agents'] !== []) {
                $hasDirectives = true;

                if (in_array($key, ['allow', 'disallow'], true) && $value !== '') {
                    $group['rules'][] = ['path' => $value, 'allow' => $key === 'allow'];
                } elseif ($key === 'crawl-delay' && is_numeric($value)) {
                    $group['delay'] = max(0, (int) ceil((float) $value));
                }
            }
        }

        $groups[] = $group;
        $matched = [];
        $specificity = -1;

        foreach ($groups as $candidate) {
            $score = -1;

            foreach ($candidate['agents'] as $agent) {
                $agent = rtrim($agent, '*');
                $score = max($score, $agent === '' ? 0 : (str_contains('athena-literatureharvester', $agent) ? strlen($agent) : -1));
            }

            if ($score > $specificity) {
                $matched = [];
                $specificity = $score;
            }

            if ($score >= 0 && $score === $specificity) {
                $matched[] = $candidate;
            }
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $query = parse_url($url, PHP_URL_QUERY);
        $path .= is_string($query) ? '?'.$query : '';
        $allowed = true;
        $longest = -1;
        $delay = 0;

        foreach ($matched as $candidate) {
            $delay = max($delay, $candidate['delay']);

            foreach ($candidate['rules'] as $rule) {
                $pattern = str_replace('\*', '.*', preg_quote($rule['path'], '~'));
                $pattern = str_ends_with($pattern, '\$') ? substr($pattern, 0, -2).'$' : $pattern;
                $length = strlen(str_replace(['*', '$'], '', $rule['path']));

                if (preg_match('~^'.$pattern.'~', $path) === 1
                    && ($length > $longest || ($length === $longest && $rule['allow']))) {
                    $allowed = $rule['allow'];
                    $longest = $length;
                }
            }
        }

        return ['allowed' => $allowed, 'delay' => $delay];
    }
}
