<?php

// Violations: missing parameter type hints.
class MissingParameterHints
{
    public function noHintNoAnnotation($value): void
    {
        $this->noHintNoAnnotation($value);
    }

    /**
     * @param string $value
     */
    public function annotationOnly($value): void
    {
        $this->annotationOnly($value);
    }
}

// Violations: missing return type hints.
class MissingReturnHints
{
    public function returnsValueNoAnnotation(int $value)
    {
        return $value;
    }

    /**
     * @return string
     */
    public function annotatedReturnOnly(string $value)
    {
        return $value;
    }

    public function returnsNothing(int $value)
    {
        $value = $value + 1;
    }

    public function missingBoth($value)
    {
        return $value;
    }
}

// Violation: promoted constructor property without a type hint.
class MissingPromotedPropertyHint
{
    public function __construct(private $name)
    {
        $this->name = $name;
    }
}

// Violation: magic methods other than __construct/__destruct/__clone are checked.
class MissingMagicMethodReturnHint
{
    public function __toString()
    {
        return 'value';
    }
}

// Violation: a value-less closure must declare : void.
class ClosureViolations
{
    public function closures(): void
    {
        $missingVoid = function () {
            // no return value, no hint
        };

        $missingVoid();
    }
}

// Violations: docblock-only complex types must be promoted to native hints.
// These exercise the enable* properties pinned in rules.xml — with a flag
// disabled, the annotation is not promotable and the behaviour changes.
class DocblockOnlyComplexTypes
{
    /**
     * @param int|string $value
     */
    public function unionAnnotationOnly($value): void
    {
        $this->unionAnnotationOnly($value);
    }

    /**
     * @param \Countable&\ArrayAccess $subject
     */
    public function intersectionAnnotationOnly($subject): void
    {
        $subject[] = count($subject);
    }

    /**
     * @param mixed $value
     */
    public function mixedParameterAnnotationOnly($value): void
    {
        $this->mixedParameterAnnotationOnly($value);
    }

    /**
     * @return mixed
     */
    public function mixedReturnAnnotationOnly(int $value)
    {
        return $value;
    }

    /**
     * @return never
     */
    public function neverAnnotationOnly()
    {
        throw new \RuntimeException('never returns');
    }
}

// Violation: a free function with an unhinted parameter and no return type is
// flagged on both counts, proving the sniffs are not method-only. Neither is
// inferable, so both stay unfixed.
function unhintedFreeFunction($value)
{
    return $value;
}

// Violations: docblock-only `object` hints promote to a native `object` hint,
// exercising enableObjectTypeHint on both the parameter and return sniffs.
class DocblockOnlyObjectTypes
{
    /**
     * @param object $value
     */
    public function objectParameterAnnotationOnly($value): void
    {
        $this->objectParameterAnnotationOnly($value);
    }

    /**
     * @return object
     */
    public function objectReturnAnnotationOnly()
    {
        return new \stdClass();
    }
}

// Violations: docblock-only complex RETURN types promote to native hints,
// exercising enableUnionTypeHint, enableIntersectionTypeHint, and
// enableStaticTypeHint on the return sniff — the parameter side of union,
// intersection, and mixed is covered above by DocblockOnlyComplexTypes.
class DocblockOnlyReturnComplexTypes
{
    /**
     * @return int|string
     */
    public function unionReturnAnnotationOnly(int $value)
    {
        return $value > 0 ? $value : 'zero';
    }

    /**
     * @return \Countable&\ArrayAccess
     */
    public function intersectionReturnAnnotationOnly(\ArrayObject $value)
    {
        return $value;
    }

    /**
     * @return static
     */
    public function staticReturnAnnotationOnly()
    {
        return $this;
    }
}

// Standalone null/true/false hints are pinned OFF to hold the PHP 8.1 floor.
// A docblock `@param false`/`@param true`/`@return false` is widened to the
// native `bool` (not the PHP 8.2 standalone `false`/`true`), while a docblock
// `@param null`/`@return null` cannot be expressed natively on 8.1 and is left
// unhinted (no violation). All pin enableStandaloneNullTrueFalseTypeHints=false:
// flip it on and the false/true cases fix to native `false`/`true` instead of
// `bool`, and the null cases start being flagged — either divergence fails
// these locks.
class DocblockOnlyStandaloneTypes
{
    /**
     * @param false $flag
     */
    public function standaloneFalseParameterAnnotationOnly($flag): void
    {
        $this->standaloneFalseParameterAnnotationOnly($flag);
    }

    /**
     * @return false
     */
    public function standaloneFalseReturnAnnotationOnly(int $value)
    {
        return $value < 0;
    }

    /**
     * @param null $value
     */
    public function standaloneNullParameterAnnotationOnly($value): void
    {
        echo $value;
    }

    /**
     * @param true $flag
     */
    public function standaloneTrueParameterAnnotationOnly($flag): void
    {
        $this->standaloneTrueParameterAnnotationOnly($flag);
    }

    /**
     * @return null
     */
    public function standaloneNullReturnAnnotationOnly()
    {
        return null;
    }

    /**
     * @return true
     */
    public function standaloneTrueReturnAnnotationOnly(int $value)
    {
        return $value > 0;
    }
}

// Violation: a docblock-only nullable `@param Foo|null` with no native hint is
// promoted to a native nullable hint — the promotion counterpart to the
// compliant native `?string` in passing.php (FullyHintedMethods::find), giving
// nullable the same two-sided coverage every other edge case carries.
class DocblockOnlyNullableType
{
    /**
     * @param \DateTimeImmutable|null $value
     */
    public function nullableParameterAnnotationOnly($value): void
    {
        echo $value?->format('c');
    }
}
