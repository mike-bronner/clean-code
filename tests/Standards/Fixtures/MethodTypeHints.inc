<?php

// Compliant: every parameter and return is natively type-hinted.
class FullyHintedMethods
{
    public function __construct(private string $name)
    {
    }

    public function __destruct()
    {
    }

    public function __clone()
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function add(int $left, int $right): int
    {
        return $left + $right;
    }

    public function find(?string $identifier): ?\DateTimeImmutable
    {
        return $identifier === null ? null : new \DateTimeImmutable($identifier);
    }

    public function parse(int|string $value): int|false
    {
        return is_int($value) ? $value : false;
    }

    public function merge(\Countable&\ArrayAccess $subject): void
    {
        $subject[] = count($subject);
    }

    public function accept(mixed $value): mixed
    {
        return $value;
    }

    public function fail(): never
    {
        throw new \RuntimeException('failed');
    }

    public static function make(string $name): static
    {
        return new static($name);
    }
}

interface HintedContract
{
    public function resolve(string $key): object;
}

// Compliant edge cases: closures and arrow functions.
class ClosureEdgeCases
{
    public function closures(): void
    {
        $hinted = function (int $value): int {
            return $value * 2;
        };

        $voidHinted = function (): void {
            // no return value, hint present
        };

        // Closures returning a value are not checked for a return hint.
        $returningValue = function ($value) {
            return $value;
        };

        // Arrow functions are not checked at all.
        $arrow = fn ($value) => $value + 1;

        $hinted(1);
        $voidHinted();
        $returningValue(2);
        $arrow(3);
    }
}

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

// Compliant: methods carrying @inheritDoc are skipped — the signature is the
// parent's. One class isolates the parameter-sniff skip, the other the
// return-sniff skip.
class InheritDocParameterSkip extends MissingParameterHints
{
    /**
     * @inheritDoc
     */
    public function noHintNoAnnotation($value): void
    {
        parent::noHintNoAnnotation($value);
    }
}

class InheritDocReturnSkip extends MissingReturnHints
{
    /**
     * @inheritDoc
     */
    public function returnsValueNoAnnotation(int $value)
    {
        return parent::returnsValueNoAnnotation($value);
    }
}

// Compliant: free functions are covered by the same rules — #70 owns
// parameter/return hints for every callable, not just class methods. A fully
// hinted free function produces no violation.
function compliantFreeFunction(int $value): int
{
    return $value * 2;
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
// A docblock `@param false`/`@return false` is widened to the native `bool`
// (not the PHP 8.2 standalone `false`), while a docblock `@param null` cannot
// be expressed natively on 8.1 and is left unhinted (no violation). Both pin
// enableStandaloneNullTrueFalseTypeHints=false: flip it on and the false cases
// fix to native `false` instead of `bool`, and the null case starts being
// flagged — either divergence fails these locks.
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
}
