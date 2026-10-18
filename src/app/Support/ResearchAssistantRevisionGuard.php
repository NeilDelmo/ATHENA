<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ResearchAssistantRevisionGuard
{
    /** @param array<array-key, mixed> $context */
    public static function preserves(string $before, string $after, array $context = []): bool
    {
        if (self::tokens($before) !== self::tokens($after)
            || self::approvalSentences($before) !== self::approvalSentences($after)) {
            return false;
        }

        // shortcut: Isolated single-word names need saved name context; add entity extraction if that context is unavailable.
        preg_match_all('/\b\p{Lu}[\p{Ll}\p{M}]+(?=\h+(?:et\h+al\.|(?:found|reported|observed|concluded|argued|noted|documented|examined)\b))/u', $before.' '.$after, $attributions);
        $names = $attributions[0];
        foreach (Arr::dot($context) as $key => $name) {
            if (! is_string($name) || trim($name) === ''
                || ! preg_match('/(?:^|\.)(?:[a-z_]*name|(?:project_)?leader|authors?|signator(?:y|ies)|person|people|prepared_by|approved_by|certified_by)(?:\.[0-9]+)?$/i', $key)) {
                continue;
            }
            $names[] = trim($name);
            foreach (preg_split('/[\s.]+/u', trim($name)) ?: [] as $part) {
                if (Str::length($part) >= 3 && ! in_array(Str::lower($part), ['prof', 'engr', 'atty', 'mrs', 'dela', 'del', 'los', 'van', 'von', 'the', 'and'], true)) {
                    $names[] = $part;
                }
            }
        }
        foreach (array_unique($names) as $name) {
            $pattern = '/(?<![\pL\pN])'.preg_quote($name, '/').'(?![\pL\pN])/iu';
            preg_match_all($pattern, $before, $original);
            preg_match_all($pattern, $after, $proposed);
            sort($original[0]);
            sort($proposed[0]);
            if ($original[0] !== $proposed[0]) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function tokens(string $text): array
    {
        $month = '(?i:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';
        $day = '(?i:\d{1,2}(?:st|nd|rd|th)?|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|eleventh|twelfth|thirteenth|fourteenth|fifteenth|sixteenth|seventeenth|eighteenth|nineteenth|twentieth|twenty[- ](?:first|second|third|fourth|fifth|sixth|seventh|eighth|ninth)|thirtieth|thirty[- ]first)';
        $unit = '(?:%|(?i:percent|per\s+cent|kg|mg|mcg|[µμ]g|g|lb|lbs|oz|km|cm|mm|m|ml|l|liters?|litres?|gallons?|inches|inch|ft|feet|yards?|miles?|ha|acres?|mol|mmol|ppm|ppb|°[CF]|hours?|minutes?|seconds?|days?|weeks?|months?|years?)(?:[²³23])?)';
        $currency = '(?:\p{Sc}|(?i:PHP|USD|EUR|GBP|JPY|AUD|CAD|CNY))';
        $numberWord = '(?i:zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|million|billion)';
        $quantity = '(?:\d+(?:[.,]\d+)*(?:[eE][+\-]?\d+|\s*/\s*\d+(?:[.,]\d+)*)?|(?i:(?:minus|plus|negative|positive)\s+)?'.$numberWord.'(?:[ -]+(?:and\s+)?'.$numberWord.')*)';
        $word = '[\p{Lu}][\p{Ll}\p{M}]+(?:[\x{2019}\x{0027}-][\pL\pM]+)*';
        $nameWords = $word.'(?:\h+(?:(?:de|del|dela|da|dos|los|van|von|of|the|and)\h+)?(?:'.$word.'|\p{Lu}\.))*';

        $patterns = [
            '~\[\[[^\]\r\n]+\]\]|\[[^\]\r\n]{1,200}\]|(?:\b'.$word.'(?:\h+(?:et\h+al\.|and\h+'.$word.'))?\h*)?\([^()\r\n]{0,160}\b(?:18|19|20)\d{2}[a-z]?\b[^()\r\n]{0,160}\)|https?://[^\s<>]+~u',
            '~(?<![\pL\pN])(?:\d{1,4}[-/.]\d{1,2}[-/.]\d{1,4}|'.$month.'\.?\s+'.$day.'(?:,?\s+\d{4})?|'.$day.'\s+'.$month.'\.?(?:,?\s+\d{4})?|'.$month.'|(?i:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday))(?![\pL\pN])~u',
            '~(?<![\pL\pN])(?:[+\-\x{2212}]\s*)?(?:'.$currency.'\s*)?(?:[+\-\x{2212}]\s*)?'.$quantity.'(?:\s*(?:'.$currency.'|'.$unit.'(?:\s*/\s*'.$unit.')?))?(?![\pL\pN])~u',
            '~\b(?i:Dr|Prof|Mr|Mrs|Ms|Engr|Atty)\.\h+'.$nameWords.'|(?<![\pL\pN])(?!(?:The|This|These|Those|Our|Their|His|Her|Its|On|In|At|By|For|With|From|To|An?|And|As|Of|During|After|Before|Under|Following)\b)'.$word.'(?:\h+(?:(?:de|del|dela|da|dos|los|van|von|of|the|and)\h+)?(?:'.$word.'|\p{Lu}\.))+|(?<![\pL\pN])\p{Lu}[\p{Lu}\d]+(?:-[\p{Lu}\d]+)*(?![\pL\pN])~u',
        ];
        $tokens = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $token) {
                $tokens[] = str_replace('−', '-', Str::squish($token));
            }
        }
        sort($tokens);

        return $tokens;
    }

    /** @return list<string> */
    private static function approvalSentences(string $text): array
    {
        $sentences = preg_split('/(?<!Dr\.)(?<!Prof\.)(?<!Mr\.)(?<!Mrs\.)(?<!Ms\.)(?<!Engr\.)(?<!Atty\.)(?<=[.!?])\s+|\R+/u', $text) ?: [];
        $protected = [];
        foreach ($sentences as $sentence) {
            if (preg_match('/\b(?:approv(?:al|e[ds]?|ing)|disapproved|reject(?:ed|ion)|signed|unsigned|signatur(?:e|es)|signator(?:y|ies)|certif(?:ied|ication)|authori[sz](?:ed|ation)|endors(?:ed|ement)|consent)\b/i', $sentence)) {
                $protected[] = Str::squish($sentence);
            }
        }
        sort($protected);

        return $protected;
    }
}
