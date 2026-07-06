<?php

namespace App\Support;

final class InstitutionalEmail
{
    /**
     * @param  array<int, string>  $allowedDomains
     */
    public static function isAllowed(?string $email, array $allowedDomains): bool
    {
        $email = strtolower(trim((string) $email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        $allowedDomains = array_map(
            fn (string $allowedDomain) => strtolower(trim($allowedDomain)),
            $allowedDomains,
        );

        return in_array($domain, $allowedDomains, true);
    }
}
