<?php

declare(strict_types=1);

namespace App\Domain;

use App\Models\{User, function make as Ledger, const STATUS_ACTIVE};
use function App\Models\{build as Widget};
use function App\Models\assemble as Gadget;
use const App\Models\STATUS_DRAFT;
use Illuminate\Database\Eloquent\Model;

/**
 * A group `use` can mark its items two different ways, and each needs its own
 * screen:
 *
 * - **per item**, inside a mixed group — `{User, function make as Ledger}`
 *   imports a class and a function in one statement;
 * - **for the whole statement**, before the prefix — `use function App\Models\{…}`
 *   marks every item at once, and its marker sits where a namespace prefix
 *   would otherwise be read.
 *
 * Neither screen catches the other's shape. Only class items import a *type*;
 * functions and constants live in PHP's separate symbol tables and must never
 * reach the class import map. And the class item of a mixed group still has to
 * import — screening its neighbours must not cost it.
 */
class Thing extends Model
{
    // The class item of the mixed group: resolves to App\Models\User, a model,
    // correctly prefixed and named after it. Silent only if skipping the
    // function item left this import intact.
    public function findUserById(int $id): User
    {
        return new User();
    }

    // `Ledger` aliases a *function*, so it imports no type at all: as a return
    // type it resolves against the enclosing namespace to App\Domain\Ledger,
    // which is no model. Let the function item into the class map and it
    // resolves to a bogus name under App\Models instead — a `Models` segment,
    // and a violation reported against correct code.
    public function build(): Ledger
    {
        return new Ledger();
    }

    // `Widget` comes from a statement-level `use function` group, so it is no
    // more a type than `Ledger` is, and resolves to App\Domain\Widget. Read the
    // statement as a class import and its `function` marker is taken for the
    // start of the namespace prefix, putting `Widget` under App\Models — a
    // `Models` segment, and a violation invented against correct code.
    public function assemble(): Widget
    {
        return new Widget();
    }

    // The same marker on a non-group statement, which reaches the parser as
    // leading text just as the group form does.
    public function craft(): Gadget
    {
        return new Gadget();
    }
}
