<?= greeting() ?>
<p>Summary</p>
<?php

/**
 * One duplicated block, three PHP open tags around it — a short echo tag, then
 * two plain ones. The scan runs once, from the first tag whichever kind it is,
 * so the pair is reported once.
 *
 * The short tag comes first deliberately. It is what makes the guard's two
 * halves separable: dropping the guard reports the pair three times, leaving
 * T_OPEN_TAG_WITH_ECHO out of the guard's own lookback reports it twice, and
 * leaving that tag out of register() reports it not at all — the scan would
 * start at a tag that already has an earlier one behind it.
 *
 * The markup between the tags is inline HTML and is not code, so it neither
 * separates the two blocks nor pads either of them.
 */

$order = fetchOrder();
$total = $order->total();
$tax = $total * 0.2;
$net = $total - $tax;
$label = 'order';

?>
<p>Details</p>
<?php

$invoice = fetchInvoice();
$sum = $invoice->total();
$vat = $sum * 0.5;
$due = $sum - $vat;
$name = 'invoice';
