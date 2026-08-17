<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;
// Aliases a model onto a global class name, so a root-anchored return type
// below would resolve to a model if the imports were consulted for it.
use App\Models\User as DateTimeImmutable;

class Comment extends Model
{
    // Non-boolean properties wearing a question-shaped name are not this
    // rule's business — only the declared `bool` type triggers it.
    public string $isbn = '';

    public int $hasCount = 0;

    // Untyped: no signal to check against.
    public $published;

    // No return type: nothing to key the naming rules on.
    public function expired()
    {
        return true;
    }

    // A genuine union names no single type to reason about.
    public function lookup(): User|Collection
    {
        return new Collection();
    }

    // Magic methods carry framework-mandated names — `__isset()` returns bool
    // and would otherwise be held to the yes/no prefix rule.
    public function __isset(string $key): bool
    {
        return false;
    }

    // `getAttribute()` is Eloquent's own reader, not a legacy accessor: there
    // is no attribute name between `get` and `Attribute`.
    public function getAttribute(string $key): mixed
    {
        return null;
    }

    // Eloquent override points return a collection but cannot be renamed.
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    // Group-imported relation types resolve outside a Models namespace, so
    // they are neither a model nor a collection.
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // A class imported from a non-model namespace is not held to `find`.
    public function publishedOn(): Carbon
    {
        return $this->created_at;
    }

    // `array` is a builtin, never a model.
    public function casts(): array
    {
        return [];
    }

    // A leading backslash makes a name fully qualified: PHP resolves it
    // globally, bypassing the enclosing namespace...
    public function publishedOnGlobal(): \DateTime
    {
        return new \DateTime();
    }

    // ...and bypassing the import map too, so this is the global class and not
    // the model aliased onto its name above.
    public function expiresAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    // A promoted property with no type carries no signal, exactly like an
    // untyped class-body property, and a non-boolean one is not this rule's
    // business however its name reads. A plain parameter — no visibility
    // modifier — declares no property at all, whatever its type.
    public function __construct(
        private $published = null,
        private string $isReference = '',
        bool $strict = false,
    ) {
        parent::__construct(['strict' => $strict]);
    }

    // Nothing declared inside a method body is a member of this model: not the
    // locals, not the closure, and not a nested anonymous class's own methods.
    public function summarise(): string
    {
        $published = true;

        $format = function (bool $flag): string {
            return $flag ? 'yes' : 'no';
        };

        $formatter = new class {
            public bool $terse = true;

            public function expired(): bool
            {
                return false;
            }
        };

        return $format($published) . ($formatter->expired() ? '!' : '');
    }
}
