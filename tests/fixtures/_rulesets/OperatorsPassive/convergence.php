<?php

declare(strict_types=1);

namespace App\Fixtures;

$number = 1;
$other = 2;

// One line per context in which a `+`/`-` sign could be misread as binary.
$suppressedNegation = @ - $number;
$suppressedIdentity = @ + $number;
- $number;
+ $number;

// The control: a genuinely binary sign whose left operand is a postfix
// increment. It must keep its spaces, or the two standards contradict.
$binaryAfterPostfix = $number++ + $other;
?>
<p>A sign opening a PHP block, in both tag forms.</p>
<?= - $number ?>
<?php - $other; ?>
<p>Trailing markup keeps the file from ending on a closing tag.</p>
