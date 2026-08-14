<?php

/**
 * The one shape where Generic.PHP.NoSilencedErrors and PHPMD's
 * CleanCode/ErrorControlOperator disagree.
 *
 * PHPMD's rule is `MethodAware, FunctionAware`, so it is only ever handed a
 * method or a function node — an `@` written at a file's top level is never
 * looked at and never reported. The sniff registers on the `T_ASPERAND` token
 * itself, so it reports there too.
 *
 * That is stricter, never looser, and suppression at file scope hides exactly
 * the same errors it hides inside a function, so the extra report is kept.
 * This fixture is what stops it being "fixed" back to parity.
 */

declare(strict_types=1);

$contents = @file_get_contents('/does/not/exist');
$key = @$_SERVER['MISSING'];

unset($contents, $key);
