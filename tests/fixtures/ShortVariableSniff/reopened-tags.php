<?php

/**
 * File-level code split across two PHP blocks.
 *
 * Everything outside a class-like and outside a named function shares one
 * scope, however many open tags the file has, so the same short name is
 * reported once — at its first occurrence, in the first block. A sniff that
 * gave every open tag its own scope would report `$fv` twice.
 */

declare(strict_types=1);

$fv = 1;

?>
<p>markup between two PHP blocks</p>
<?php

echo $fv;

$sv = 2;

echo $sv;
