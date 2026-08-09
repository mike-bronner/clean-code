<?php

/**
 * A deliberately unterminated class. PHPCS leaves such a declaration with no
 * scope_opener, so the sniff has no body to walk. Fixtures are never collected
 * as tests and are excluded from the self-lint, so a parse error is safe here.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Fixtures\ExcessivePublicCount;

class NeverClosed
{
    public function only(): void
    {
    }
