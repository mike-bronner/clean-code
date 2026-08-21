<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\User as Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as CollectionAlias;

/**
 * The other direction: correct code that reading the types as written would
 * report. Both methods are named after what they really return, and both would
 * be flagged — with actively wrong advice — if the alias were taken at face
 * value.
 */
class Post extends Model
{
    // Named after `User`, the model this returns. Judged as written, the sniff
    // would demand `findClientByName()` — renaming the method *away* from the
    // model it returns, toward a name local to this one file.
    public function findUserByName(string $name): Client
    {
        return new Client();
    }

    // An aliased collection, correctly prefixed `get`.
    public function getCommentsByType(string $type): CollectionAlias
    {
        return new CollectionAlias();
    }
}
