<?php

/**
 * The one code rules.xml excludes: OldStyleCall, a PHP4-style *call* to a
 * parent constructor.
 *
 * PHPMD's ConstructorWithNameAsEnclosingClass only ever inspects a method's own
 * declared name against its enclosing class, so a call site has no counterpart
 * in the rule being replicated — PHPMD 2.15.0 reports nothing on this file.
 *
 * Both halves of the exclude are pinned in
 * tests/Ruleset/ConstructorNameTest.php: silent through rules.xml, and firing
 * without the exclude. Without the second half a fixture that tripped nothing
 * would look exactly like a working exclude list.
 *
 * The subclass declares __construct, so the OldStyle branch cannot fire here
 * and the only diagnostic available is the call-site one.
 */

declare(strict_types=1);

class LegacyBase
{
    public function __construct()
    {
    }
}

class LegacyChild extends LegacyBase
{
    public function __construct()
    {
        parent::LegacyBase();
    }
}
