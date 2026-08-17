<?php

/**
 * The four rules that are purely alphabetical, each broken, and nothing else.
 *
 * Every trait has its own use statement and the visibility groups run public,
 * protected, private — so the only thing wrong here is the order of names
 * inside each group. That is what makes this fixture the existing-rule
 * evaluation: it is the exact shape no shipped Slevomat sniff has anything to
 * say about, and four of the standard's five rules.
 */

declare(strict_types=1);

class AlphabeticalOnlyModel extends Model
{
    use Zebra;
    use Alpha;

    public string $zulu = '';

    public string $alpha = '';

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
}
