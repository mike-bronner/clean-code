<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Every pair below is a near miss: the sniff walks all of it and stays silent
 * because each pair breaks the match before it reaches the five-line default.
 *
 * The comparison drops token *content*, not token *types*, so the four shapes
 * here — a swapped operator, an extra argument, a different loop keyword, and
 * a genuinely short duplicate — are all differences it still sees.
 */
class NearMisses
{
    /**
     * Two identical bodies, but only three lines of code each once the braces
     * are set aside. Below the threshold, so no comparison happens.
     */
    private function begin(): void
    {
        $this->connect();
        $this->prepare();
    }

    private function finish(): void
    {
        $this->flush();
        $this->close();
    }

    /**
     * The operator is the only difference, and it is a difference the sniff
     * keeps: `+` is T_PLUS and `-` is T_MINUS, two token types, not two
     * spellings of one. Four matching lines lead up to it and one follows.
     */
    private function totalCredits(array $rows): int
    {
        $total = 0;
        $step = 1;

        foreach ($rows as $row) {
            $total = $total + ($row['amount'] * $step);
        }

        return $total;
    }

    private function totalDebits(array $rows): int
    {
        $total = 0;
        $step = 1;

        foreach ($rows as $row) {
            $total = $total - ($row['amount'] * $step);
        }

        return $total;
    }

    /**
     * One extra argument. Punctuation counts towards a kept line's shape, so
     * the added comma and value make the two calls different lines.
     */
    private function notifyOwner(Order $order): void
    {
        $mailer = $this->mailer();
        $message = $mailer->compose($order);
        $mailer->send($message);
        $this->audit($order);
    }

    private function notifyBuyer(Order $order): void
    {
        $mailer = $this->mailer();
        $message = $mailer->compose($order);
        $mailer->send($message, true);
        $this->audit($order);
    }

    /**
     * Same intent, different construct: a `foreach` and a `while` are not a
     * renaming of each other.
     */
    private function collectActive(array $rows): array
    {
        $found = [];
        $cursor = 0;

        foreach ($rows as $row) {
            $found[] = $row;
        }

        return $found;
    }

    private function collectPending(array $rows): array
    {
        $found = [];
        $cursor = 0;

        while ($cursor < 3) {
            $found[] = $rows[$cursor];
        }

        return $found;
    }

    /**
     * Nothing here repeats anything: kept as the plain negative case, so the
     * fixture is not made entirely of pairs.
     */
    private function describe(Order $order): string
    {
        return sprintf(
            '%s/%d (%s)',
            $order->reference(),
            $order->total(),
            implode('|', $order->tags())
        );
    }
}
