<?php

declare(strict_types=1);

use PHP_CodeSniffer\Util\Timing;
use SlevomatCodingStandard\Helpers\UseStatementHelper;

// Two real, autoloadable ancestors, each declaring the redeclared member
// private: Timing::$printed and UseStatementHelper::isGroupUse(). A private
// member is not inherited, so PHP loads both children below without complaint
// and the sniff must keep reporting them. Skipping on a private ancestor would
// hide a real violation.
class RedeclaresPrivateProperty extends Timing
{
    // Violation: $printed is private on Timing, so this declaration is a new
    // member and nothing forces it to stay static.
    private static $printed = false;
}

class RedeclaresPrivateMethod extends UseStatementHelper
{
    // Violation: isGroupUse() is private on UseStatementHelper, same reason.
    private static function isGroupUse(): bool
    {
        return false;
    }
}
