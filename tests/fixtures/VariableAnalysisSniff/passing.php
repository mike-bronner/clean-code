<?php

declare(strict_types=1);

namespace App;

use Throwable;

class Passing
{
    private string $name = 'compliant';

    public function assigned(): string
    {
        $greeting = 'hello';

        return $greeting;
    }

    public function parameter(string $subject): string
    {
        return $subject;
    }

    public function property(): string
    {
        return $this->name;
    }

    public function loop(array $rows): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += $row;
        }

        return $total;
    }

    public function caught(): string
    {
        try {
            $result = $this->assigned();
        } catch (Throwable $error) {
            $result = $error->getMessage();
        }

        return $result;
    }

    public function superglobal(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function inherited(int $factor): callable
    {
        return function (int $value) use ($factor): int {
            return $value * $factor;
        };
    }

    public function byReference(array &$bucket): void
    {
        $bucket[] = 1;
    }

    public function destructured(array $pair): int
    {
        [$left, $right] = $pair;

        return $left + $right;
    }

    /**
     * A formal parameter nobody reads. PHPMD splits these into its own
     * UnusedFormalParameter rule (#120), so UnusedLocalVariable stays silent
     * and CleanCode/ruleset.xml configures the sniff to match.
     */
    public function unusedParameter(string $ignored): string
    {
        return 'result';
    }

    /**
     * A caught exception nobody reads. PHPMD allows any variable bound by a
     * catch statement, and the sniff's default does the same.
     */
    public function unusedCaughtException(): string
    {
        try {
            return $this->assigned();
        } catch (Throwable $error) {
            return 'failed';
        }
    }

    /**
     * compact() reads a name through a string literal. Both tools understand
     * it, so $city is defined and used, not unused.
     */
    public function compacted(): array
    {
        $city = 'Denver';

        return compact('city');
    }

    /**
     * Passing a name by reference to a function counts as using it: the
     * callee writes through the alias.
     */
    public function passedByReference(): int
    {
        $matches = [];
        preg_match('/\d+/', 'abc 42', $matches);

        return count($matches);
    }

    /**
     * A foreach value bound by reference and written through. The write is
     * the point of the loop, so the name is used.
     */
    public function foreachByReference(array $items): array
    {
        foreach ($items as &$item) {
            $item = 1;
        }

        return $items;
    }

    /**
     * A closure capturing by reference so the enclosing scope can read the
     * result afterwards.
     */
    public function closureUseByReference(): int
    {
        $collected = 0;

        $adder = static function () use (&$collected): void {
            $collected++;
        };
        $adder();

        return $collected;
    }

    /**
     * Read only inside a heredoc. Interpolation is a use.
     */
    public function heredocInterpolation(): string
    {
        $subject = 'world';

        return <<<TXT
        hello {$subject}
        TXT;
    }
}

// PHPMD's UnusedLocalVariable is FunctionAware and MethodAware only, so it
// never looks at a file's top-level scope. CleanCode/ruleset.xml sets
// allowUnusedVariablesInFileScope to match, which is why this assignment is
// not a violation even though nothing reads it.
$fileScopeAssignment = 'never read';
