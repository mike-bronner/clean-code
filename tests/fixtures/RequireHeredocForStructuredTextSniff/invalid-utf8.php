<?php

// Not valid UTF-8: raw Latin-1 bytes. Byte mode still reads a lone 0x85 byte
// as a line break, so the first string is markdown, as before.
$markdown = "café…# heading";
$plain = "café # not a heading";
