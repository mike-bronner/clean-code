<?php

declare(strict_types=1);

class OutOfOrderModel extends Model
{
    use Zebra;
    use Alpha;
    use Beta, Gamma;

    public string $zulu = '';

    public string $alpha = '';

    private bool $early = false;

    protected string $table = 'out_of_order';

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
