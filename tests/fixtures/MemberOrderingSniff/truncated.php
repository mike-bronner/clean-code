<?php

/**
 * A model-shaped class the tokenizer never opened. findExtendedClassName()
 * returns false without a scope_opener, so the gate closes on it and nothing is
 * reported — the alternative, walking to the end of the file, would order this
 * class's absent members against whatever came next.
 */

declare(strict_types=1);

class TruncatedModel extends Model
