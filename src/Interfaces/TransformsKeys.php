<?php

declare(strict_types=1);

namespace Medas\Cache\Interfaces;

/**
 * A cache that can tell under which identifier it stores the entry for a key, so a key index can record where each
 * entry ended up.
 *
 * @see \Medas\Cache\KeyRegisterDecorator
 */
interface TransformsKeys
{
    /**
     * @param string|string[] $key
     *
     * @return string The identifier the entry is stored under, e.g. the path of its file for `FileSystemCache`. For
     *                the other caches in this package, it's the normalised key: an array is joined with null bytes,
     *                and a prefix the backend adds is left out.
     */
    public function transformKey(array|string $key): string;
}
