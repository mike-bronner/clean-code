<?php

/**
 * A compliant view controller in a checkout whose own directory tree carries an
 * ancestor `Controllers` segment, with an `api` segment below it. Two
 * `Controllers` segments sit in this file's path inside the repository; only
 * the lower one is this application's controller root.
 *
 * Anchor on the first and the segments "below the controller root" become
 * api/app/Http/Controllers, which reads as an API path, and this ordinary view
 * controller is reported as missing an API namespace.
 */

declare(strict_types=1);

namespace App\Http\Controllers;

class RootAncestorController
{
}
