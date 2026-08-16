<?php

// The tokenizer never pairs this list(), so there is no closing parenthesis
// to look past for an "=". The sniff must stay silent rather than read a
// position that is not there.

if (list($first, $second
