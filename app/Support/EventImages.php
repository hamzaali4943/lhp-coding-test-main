<?php

namespace App\Support;

/**
 * Maps an event to a small set of locally-served placeholder images.
 *
 * Events have no real images, so we keep a pool of locally-hosted SVG
 * placeholders per category (public/images/events/{category}/{n}.svg) and
 * assign each event a deterministic subset of them based on its id. The result
 * is stable across requests, reuses a handful of files, and is served entirely
 * from our own origin (no external/hotlinked URLs).
 */
class EventImages
{
    /** Variants available per category (must match the generated files). */
    public const VARIANTS = 3;

    /** Categories with a dedicated image set; anything else falls back here. */
    public const CATEGORIES = [
        'concert', 'conference', 'meetup', 'workshop',
        'festival', 'sports', 'networking', 'exhibition',
    ];

    private const FALLBACK = 'meetup';

    /**
     * Local image URLs for an event (always at least two).
     *
     * @return list<string>
     */
    public static function for(string $id, string $category): array
    {
        $category = in_array($category, self::CATEGORIES, true) ? $category : self::FALLBACK;

        // Derive a stable offset from the id so different events of the same
        // category lead with different cover images.
        $offset = hexdec(substr(md5($id), 0, 4)) % self::VARIANTS;

        $images = [];
        for ($i = 0; $i < self::VARIANTS; $i++) {
            $n = (($offset + $i) % self::VARIANTS) + 1;
            $images[] = "/images/events/{$category}/{$n}.svg";
        }

        return $images;
    }
}
