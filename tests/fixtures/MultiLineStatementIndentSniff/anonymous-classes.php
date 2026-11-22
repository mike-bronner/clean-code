<?php

declare(strict_types=1);

namespace App;

class AnonymousClasses
{
    public function assigned(string $entry): void
    {
        $importer = new class ($entry) extends ThayerImporter
        {
            public function __construct(protected string $entry)
            {
            }
        };

        $importer->run();
    }

    public function implementing(): void
    {
        $importer = new class implements Importer
        {
            public function run(): int
            {
                return $this->first
                    + $this->second;
            }
        };

        $importer->run();
    }

    public function continuedHeader(): void
    {
        $importer = new class extends ThayerImporter implements
            Importer
        {
            public function run(): void
            {
            }
        };

        $importer->run();
    }

    public function returned(): Importer
    {
        return new class extends ThayerImporter
        {
            public function run(): void
            {
            }
        };
    }

    public function argument(): void
    {
        $this->register(
                new class extends ThayerImporter
                {
                    public function run(): void
                    {
                    }
                },
            );
    }

    public function inlineArgument(): void
    {
        $this->register(new class extends ThayerImporter
        {
            public function run(): void
            {
            }
        });
    }
}
