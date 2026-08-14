<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * An enum under the source root with no test. T_ENUM, so never flagged.
 */
enum Status: string
{
    case Draft = "draft";
    case Published = "published";
}
