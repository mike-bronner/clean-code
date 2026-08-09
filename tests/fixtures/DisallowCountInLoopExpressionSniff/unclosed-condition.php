<?php

// The other half of the malformed-source guard: the opening parenthesis is
// tokenised, so parenthesis_opener is set, but the file ends before the
// closer — leaving parenthesis_closer absent. count() sits inside what would
// have been the condition, so a sniff that read the opener and then guessed an
// end would report here.
$items = [];

while (count($items) > 0
