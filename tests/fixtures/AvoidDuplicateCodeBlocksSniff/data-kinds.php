<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Every kind of token a data-only line may hold, two lines of each. The test
 * runs this with a threshold of one line, so any two lines of one shape match.
 * Each pair stays silent only because its token kind counts as data. If one
 * kind stops counting as data, its pair is reported.
 */
class DataKinds extends Base
{
    public function kinds(string $name): array
    {
        return [
            'single',
            'quoted',
            "double {$name}",
            "quoted {$name}",
            1,
            2,
            1.5,
            2.5,
            true,
            true,
            false,
            false,
            null,
            null,
            'key' => 'value',
            'other' => 'value',
            'joined' . 'text',
            'more' . 'text',
            FIRST_CONSTANT,
            SECOND_CONSTANT,
            Qualified\First,
            Qualified\Second,
            \FullyQualified,
            \AlsoFullyQualified,
            namespace\Relative,
            namespace\AlsoRelative,
            Holder::FIRST,
            Holder::SECOND,
            self::class,
            self::class,
            static::class,
            static::class,
            parent::class,
            parent::class,
            Builder::make()
                ->with('first')
                ->with('second')
                ->with('third'),
            <<<TEXT
                first heredoc line
                second heredoc line
                TEXT,
            <<<TEXT
                third heredoc line
                TEXT,
            <<<'TEXT'
                first nowdoc line
                second nowdoc line
                TEXT,
            <<<'TEXT'
                third nowdoc line
                TEXT,
        ];
    }
}
