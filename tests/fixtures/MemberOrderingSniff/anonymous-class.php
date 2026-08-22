<?php

/**
 * Anonymous classes, which PHP_CodeSniffer retokenizes from T_CLASS to
 * T_ANON_CLASS and which a standard registering T_CLASS alone therefore never
 * sees at all.
 *
 * The first class breaks all five rules at the top level, so the assertion is a
 * count and a line rather than the silence a nested case can only offer. The
 * three after it are the boundaries around the gate: an argument list between
 * `class` and `extends`, no extends clause, and a parent that is not a model.
 */

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

$model = new class extends Model {
    use Zebra;
    use Alpha;
    use Beta, Gamma;

    public string $zulu = '';

    public string $alpha = '';

    private bool $early = false;

    protected string $table = 'anonymous';

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setTitle(string $title): void
    {
    }

    public function getTitle(): string
    {
        return '';
    }

    public function publish(): void
    {
    }

    public function archive(): void
    {
    }
};

// An argument list sits between `class` and `extends`, and the extends clause
// is still found past it.
$configured = new class('anonymous', 1) extends Model {
    use Zebra;
    use Alpha;
};

// No extends clause at all: outside the gate, so its reversed traits are not
// this standard's business.
$plain = new class {
    use Zebra;
    use Alpha;
};

// A parent that is not model-shaped: outside the gate for the same reason.
$service = new class extends Controller {
    use Zebra;
    use Alpha;
};
