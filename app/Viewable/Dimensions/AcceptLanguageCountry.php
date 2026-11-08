<?php

namespace App\Viewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\CountryResolver;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * Reads the country Cloudflare sends, and falls back to the region of the
 * browser's first language, such as `NL` from `nl-NL,nl;q=0.9`. Good enough
 * for a demo on a laptop; use a GeoIP database in production.
 */
final class AcceptLanguageCountry implements CountryResolver
{
    public function country(DimensionInput $input): ?string
    {
        $header = $input->header('CF-IPCountry');

        if ($header !== null && preg_match('/^[A-Za-z]{2}$/', $header) === 1) {
            return $header;
        }

        $language = $input->header('Accept-Language');

        if ($language === null || preg_match('/^[a-z]{2,3}-([A-Za-z]{2})\b/', $language, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
