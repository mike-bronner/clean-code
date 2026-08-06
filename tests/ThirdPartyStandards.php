<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests;

/**
 * The third-party PHPCS standards the master rules.xml references.
 *
 * Composer's phpcodesniffer-composer-installer writes these paths into
 * CodeSniffer.conf on install, but every suite here builds its Config through
 * ConfigDouble, which blanks PHPCS's static config data — so each suite has to
 * restore installed_paths (in memory only) before parsing rules.xml, or the
 * referenced sniffs fail to resolve.
 *
 * Kept in one place because the value is not per-suite: it is the set of
 * standards rules.xml depends on. Wiring a new third-party standard into
 * rules.xml is then a one-line change here instead of an edit to every suite.
 */
final class ThirdPartyStandards
{
    /**
     * Absolute paths of the installed third-party standards, in the
     * comma-separated form PHPCS's installed_paths config expects.
     */
    public static function installedPaths(): string
    {
        $vendor = dirname(__DIR__) . '/vendor';

        return implode(',', [
            $vendor . '/sirbrillig/phpcs-variable-analysis',
            $vendor . '/slevomat/coding-standard',
        ]);
    }
}
