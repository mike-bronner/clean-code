<?php

/**
 * A method fragment the tokenizer could not finish reading, inside a class it
 * did open — the method-level counterpart of truncated.php, whose whole class
 * never opened.
 *
 * `public function` with no name after it leaves the method walk nothing to
 * order. The name-token search stops at the class closer and finds nothing, and
 * the fragment is passed over: `zulu()` above it stays the last method the walk
 * saw, and no method violation is reported.
 *
 * The class's properties are deliberately out of order, so the one violation
 * below is what says the gate is open and the walks did run on this class. The
 * fragment's silence is the rest of the assertion.
 */

declare(strict_types=1);

class NamelessMethodModel extends Model
{
    public $zulu;

    public $alpha;

    public function zulu(): void
    {
    }

    public function
}
