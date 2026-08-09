<?php

/**
 * Violating fixture for CleanCode.Functions.AvoidDuplicateFunctionBodies.
 *
 * Three shapes of the same defect:
 *
 *   - A group of three identical method bodies. The first is the original;
 *     the second and third are each reported against it, so a group of N
 *     duplicates yields N-1 warnings rather than N.
 *   - Two identical method bodies in different classes of the same file.
 *   - Two identical plain function bodies at file scope.
 *
 * Nothing outside the braces is compared, so the duplicated declarations
 * deliberately disagree on name, visibility, parameter type, and return type;
 * inside the braces they disagree on comments and whitespace. A parameter's
 * *name* is shared wherever the body reads it, because renaming it would
 * change the body too — that is near-miss detection, which this sniff does
 * not do.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\AvoidDuplicateFunctionBodiesSniff\Failing;

class Billing
{
    public function chargeCard(array $order): array
    {
        $amount = $order['total'];
        $amount = round($amount, 2);

        return ['charged' => $amount];
    }

    /**
     * Same body as chargeCard(), reformatted and re-commented, behind a
     * different visibility and a different parameter type.
     */
    protected function chargeAccount(iterable $order): array
    {
        // Read the total off the input.
        $amount = $order['total'];

        $amount = round($amount, 2);
        return ['charged' => $amount];
    }

    private function chargeWallet(array $order): iterable
    {
        $amount = $order['total'];
        $amount = round($amount, 2);

        return ['charged' => $amount]; // Third copy of the same body.
    }
}

class Refunds
{
    public function refundOrder(array $record): string
    {
        $reference = $record['reference'];
        $reference = strtoupper($reference);

        return $reference;
    }
}

class Chargebacks
{
    public function raiseDispute(array $record): string
    {
        $reference = $record['reference'];
        $reference = strtoupper($reference);

        return $reference;
    }
}

function normalizeTags(array $values): array
{
    $values = array_map('trim', $values);
    $values = array_filter($values);

    return array_values($values);
}

function normalizeLabels(array $values): array
{
    $values = array_map('trim', $values);
    $values = array_filter($values);

    return array_values($values);
}
