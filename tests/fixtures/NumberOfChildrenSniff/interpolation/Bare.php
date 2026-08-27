<?php

declare(strict_types=1);

/**
 * Three classes in the global namespace, each named after one of
 * Interpolated.php's traits. Nothing anywhere extends any of them, so every one
 * has zero children and the file is silent at any threshold.
 *
 * They exist to be the wrong answer. A trait `use` misread as an import binds
 * the trait's short name to the *global* name it would import, so a following
 * `extends` of that short name lands here instead of on the trait — and the
 * report names a class in this file, which has no children at all.
 */
class Braced
{
}

class Dollared
{
}

class Heredoc
{
}
