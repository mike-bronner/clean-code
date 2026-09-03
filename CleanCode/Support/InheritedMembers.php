<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;
use ReflectionClass;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

class InheritedMembers
{
    private const CODE_SNIFFER_PREFIX = 'PHP_CodeSniffer\\';

    // Whether the method at $functionPtr overrides one whose parameters an
    // ancestor declares untyped. PHP treats adding a hint to an inherited
    // untyped parameter as narrowing and refuses to load the class, so a rule
    // asking for that hint is asking for a fatal error. Observed live: a
    // Livewire component typing a parameter its vendor parent left untyped
    // stopped the application booting.
    public static function overridesUntypedParameter(File $phpcsFile, int $functionPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($functionPtr);

        if ($name === null) {
            return false;
        }

        foreach (self::ancestors($phpcsFile, $functionPtr) as $ancestor) {
            if ($ancestor->hasMethod($name) === false) {
                continue;
            }

            foreach (
                $ancestor->getMethod($name)
                    ->getParameters() as $parameter
            ) {
                if ($parameter->hasType() === false) {
                    return true;
                }
            }
        }

        return false;
    }

    // Whether the declaration at $pointer belongs to a PHP_CodeSniffer class.
    //
    // PHPCS assigns a sniff's properties from ruleset XML as strings, so
    // `<property name="minimum" value="3"/>` puts "3" into the property and a
    // native int throws TypeError in the consumer's run. The sniff classes
    // PHPCS ships leave them untyped for that reason.
    public static function isCodeSnifferClass(File $phpcsFile, int $pointer): bool
    {
        foreach (self::ancestorNames($phpcsFile, $pointer) as $name) {
            if (str_starts_with($name, self::CODE_SNIFFER_PREFIX) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, ReflectionClass<object>>
     */
    private static function ancestors(File $phpcsFile, int $pointer): array
    {
        $ancestors = [];

        foreach (self::ancestorNames($phpcsFile, $pointer) as $name) {
            // A consumer's own parent may not be loadable during a lint run. It
            // is skipped rather than guessed at, so an unresolvable ancestor
            // leaves the caller reporting exactly as it did before — an
            // unfixable report is better than a silently hidden real one.
            if (
                class_exists($name) === false
                && interface_exists($name) === false
            ) {
                continue;
            }

            $ancestors[] = new ReflectionClass($name);
        }

        return $ancestors;
    }

    /**
     * @return array<int, string>
     */
    private static function ancestorNames(File $phpcsFile, int $pointer): array
    {
        $classPtr = ClassHelper::getClassPointer($phpcsFile, $pointer);

        if ($classPtr === null) {
            return [];
        }

        $names = [];
        $extended = $phpcsFile->findExtendedClassName($classPtr);

        if (is_string($extended) === true) {
            $names[] = $extended;
        }

        $implemented = $phpcsFile->findImplementedInterfaceNames($classPtr);

        if (is_array($implemented) === true) {
            $names = array_merge($names, $implemented);
        }

        return array_map(
            static fn (string $name): string => ltrim(
                NamespaceHelper::resolveClassName($phpcsFile, $name, $classPtr),
                '\\'
            ),
            $names
        );
    }
}
