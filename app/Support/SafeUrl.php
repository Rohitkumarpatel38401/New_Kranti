<?php

namespace App\Support;

/**
 * Links typed into the CMS may only be http(s) URLs or paths on this site; anything else
 * (javascript:, data:, protocol-relative //host) is replaced by the fallback when rendered.
 */
final class SafeUrl
{
    public const PATTERN = '/^(https?:\/\/[^\s]+|\/(?!\/)[^\s]*)$/i';

    public static function isSafe(?string $url): bool
    {
        return is_string($url) && preg_match(self::PATTERN, trim($url)) === 1;
    }

    public static function or(?string $url, string $fallback): string
    {
        return self::isSafe($url) ? trim($url) : $fallback;
    }
}
