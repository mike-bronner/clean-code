<?php

/**
 * Compliant member ordering, plus every near-miss shape the sniff must stay
 * silent on. Both halves matter: a fixture holding only correctly ordered
 * models would pass just as well against a sniff whose gate never opened.
 */

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompliantModel extends Model
{
    use Archivable;
    use HasFactory;
    use SoftDeletes;

    public string $alpha = '';

    public int $beta = 0;

    protected array $casts = [];

    protected string $table = 'compliant';

    private bool $memoised = false;

    private ?string $rendered = null;

    public function __construct(private string $zeta, private string $alphaPromoted)
    {
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function archive(): void
    {
        $this->writeLog();
    }

    public function publish(): void
    {
        $this->archive();
    }

    private function writeLog(): void
    {
        $unordered = 1;
        $alphabetical = 2;
    }
}

/**
 * A class with no model-shaped parent. Every rule is broken here, and the gate
 * is the only reason nothing is reported — without it this fixture is the
 * loudest file in the suite.
 */
class PlainService
{
    use Zebra;
    use Alpha;

    private string $zulu = '';

    public string $alpha = '';

    public function zulu(): void
    {
    }

    public function alpha(): void
    {
    }
}

/**
 * A model-shaped class with a single member of each kind. One trait cannot be
 * out of order with itself, and neither can one property or one method.
 */
class SingleMemberModel extends Pivot
{
    use Zebra;

    private string $zulu = '';

    public function zebra(): void
    {
    }
}

/**
 * A model-shaped class with no traits, no properties, and no methods at all.
 */
class EmptyModel extends Authenticatable
{
}

/**
 * A visibility group of exactly one property, sitting between two larger ones —
 * the boundary at which a lone group has nothing of its own kind to sort
 * against, while groups on both sides do.
 *
 * The names are chosen so the class is silent only if each group is ordered
 * against itself: `$charlie` and `$delta` both sort before the `$zulu` above
 * them, and `$charlie` sorts before the `$table` between them, so any reading
 * that carries a baseline across a group boundary reports here. The
 * single-property groups elsewhere in this file cannot say that — each is the
 * only group its class has — and `CompliantModel`, the one class with all three
 * groups populated, has two properties in each.
 */
class InteriorSingletonGroupModel extends Model
{
    public string $alpha = '';

    public string $zulu = '';

    protected string $table = 'interior_singleton';

    private bool $charlie = false;

    private bool $delta = false;
}

/**
 * The three method categories interleaved, each internally alphabetical. The
 * sequence *between* categories is not a rule this sniff enforces, so nothing
 * is reported — the assertion that keeps that decision from drifting.
 */
class InterleavedModel extends BaseModel
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTitle(): string
    {
        return '';
    }

    public function archive(): void
    {
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function setTitle(string $title): void
    {
    }

    public function publish(): void
    {
    }
}

/**
 * Magic methods, whose placement is PHP's convention rather than the standard's
 * alphabet, and a nested anonymous class whose own members answer to it.
 */
class MagicAndNestedModel extends Model
{
    public function __construct()
    {
    }

    public function alpha(): void
    {
    }

    public function zulu(): object
    {
        return new class extends Model {
            use Zebra;
            use Alpha;

            private string $zulu = '';

            public string $alpha = '';
        };
    }

    public function __toString(): string
    {
        return '';
    }
}
