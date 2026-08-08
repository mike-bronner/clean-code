<?php

declare(strict_types=1);

class ReportRenderer
{
    private const VERBOSE = true;

    private bool $enabled = true;

    public function __construct(private string $format)
    {
    }

    public function render(string $template, int $limit = 10, array $rows = []): string
    {
        return $this->format . $template . $limit . count($rows);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function accepts(bool|string $value): string
    {
        return (string) $value;
    }

    public function collect(bool ...$flags): int
    {
        return count($flags);
    }

    public function resolved($mode = self::VERBOSE): string
    {
        return (string) $mode;
    }

    public function negated($mode = !true): string
    {
        return (string) $mode;
    }

    public function optional(?string $label = null): string
    {
        return (string) $label;
    }

    /**
     * @param bool $flag
     */
    public function documented($flag = null): string
    {
        return (string) $flag;
    }

    public function callsWithLiterals(): string
    {
        $format = static fn (string $value): string => $value;
        $trim = function (string $value): string {
            return trim($value);
        };

        return $format($this->render('a')) . $trim(var_export(true, true));
    }
}

interface Publisher
{
    public function publish(string $channel): void;
}

enum Visibility: string
{
    case Draft = 'draft';

    public function label(string $locale): string
    {
        return $locale . $this->value;
    }
}

function bootstrap(int $level = 0): void
{
    error_reporting($level);
}
