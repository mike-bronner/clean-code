<?php

declare(strict_types=1);

namespace App\Fixtures;

// Template-context convergence fixture. A `+`/`-` immediately after an open tag
// (`<?php` or short-echo `<?=`) is read as *binary* by the wired-in
// PSR12.Operators.OperatorSpacing, so this standard deliberately does not touch
// it — the sign is ceded to PSR12 and left out of scope. This fixture pins that
// phpcbf --standard=rules.xml converges (never exit 2) rather than oscillating
// with PSR12 over the open-tag sign. Trailing markup keeps the final `?>` off
// the end of the file so PSR2.Files.ClosingTag does not colour the result.

$total = 1;

?>
<?= - $total ?>
<p>trailing markup</p>
