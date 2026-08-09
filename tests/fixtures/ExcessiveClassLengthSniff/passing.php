<?php

declare(strict_types=1);

namespace App\Fixtures;

// Every construct below except Invoice is a near miss the sniff must stay
// silent on, and each one is deliberately longer than the small threshold the
// behaviour test configures: PHPMD's ExcessiveClassLength is ClassAware, so an
// interface, a trait, and an enum are out of scope however long they get, an
// anonymous class is never reported in its own right, and a `::class` constant
// fetch is a T_CLASS token that opens no scope at all. Invoice is the one real
// class here, and it is short enough to clear the threshold on its own.

interface Repository
{
    public function find(int $id): ?object;

    public function save(object $entity): void;

    public function delete(object $entity): void;

    public function count(): int;

    public function flush(): void;
}

trait Timestamps
{
    private ?string $createdAt = null;

    private ?string $updatedAt = null;

    public function touch(): void
    {
        $this->updatedAt = 'now';
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }
}

enum Status: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

class Invoice
{
    use Timestamps;

    public function total(): int
    {
        return 0;
    }
}

$contract = Repository::class;

$anonymous = new class implements Repository {
    public function find(int $id): ?object
    {
        return null;
    }

    public function save(object $entity): void
    {
    }

    public function delete(object $entity): void
    {
    }

    public function count(): int
    {
        return 0;
    }

    public function flush(): void
    {
    }
};
