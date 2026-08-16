<?php

/**
 * A class that is not a controller at all — an API resource — in a checkout
 * with an ancestor `Controllers` segment. Its own namespace carries no
 * controller root, and its path carries one only by accident of where the
 * project happens to sit.
 *
 * Read the path without letting the namespace veto it and the segments below
 * that accidental root are my-app/app/Http/Resources/Api: an API path with no
 * API namespace, reported as a misplaced controller.
 */

declare(strict_types=1);

namespace App\Http\Resources\Api;

class UserResource
{
}
