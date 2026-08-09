<?php

// An array left unterminated in a file being edited. PHP_CodeSniffer gives the
// T_ARRAY token an opener but no closer, so there are no bounds to walk and
// the array is abandoned without a diagnostic.
$partial = array('k' => 1, 'k' => 2,
