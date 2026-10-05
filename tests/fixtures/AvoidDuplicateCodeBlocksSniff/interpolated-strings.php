<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * PHP_CodeSniffer keeps a double-quoted string with variables in it as one
 * token, so a variable inside the string is part of that token's text. The
 * sniff sets those variable names aside too.
 *
 * greetCustomer() and greetMerchant() differ only in variable names, two of
 * them inside strings, and are reported. priceInDollars() and priceInEuros()
 * differ only in the text after an escaped dollar sign. That text is literal,
 * not a variable, so that pair is not reported.
 */
class InterpolatedStrings
{
    public function greetCustomer(Customer $customer): array
    {
        $name = $customer->name;
        $opening = "Dear {$name},";
        $closing = "Thank you, $name.";
        $this->mailer->queue($opening, $closing);

        return [$opening, $closing];
    }

    public function greetMerchant(Merchant $merchant): array
    {
        $title = $merchant->name;
        $opening = "Dear {$title},";
        $closing = "Thank you, $title.";
        $this->mailer->queue($opening, $closing);

        return [$opening, $closing];
    }

    public function priceInDollars(Product $product): string
    {
        $amount = $product->price;
        $label = "{$amount} \$price";
        $note = "per \$unit for {$amount}";
        $this->labels->queue($label, $note);

        return $label . $note;
    }

    public function priceInEuros(Product $product): string
    {
        $amount = $product->price;
        $label = "{$amount} \$cost";
        $note = "per \$item for {$amount}";
        $this->labels->queue($label, $note);

        return $label . $note;
    }
}
