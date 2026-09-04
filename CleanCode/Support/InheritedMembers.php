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
    public function overridesUntypedParameter(File $phpcsFile, int $functionPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($functionPtr);

        if ($name === null) {
            return false;
        }

        foreach ($this->ancestors($phpcsFile, $functionPtr) as $ancestor) {
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

    // Whether the method at $functionPtr overrides one an ancestor declares
    // static. PHP refuses to load a class that makes an inherited static method
    // non-static, so a rule asking for the keyword's removal is asking for a
    // fatal error. Laravel's Facade is the case that reaches consumers: its
    // getFacadeAccessor() is abstract protected static, so every facade written
    // against it has to keep the keyword.
    //
    // A private ancestor member is skipped because it is not inherited: the
    // child's declaration is a new member, and PHP loads it non-static without
    // complaint. Treating one as binding would hide a real violation.
    public function overridesStaticMethod(File $phpcsFile, int $functionPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($functionPtr);

        if ($name === null) {
            return false;
        }

        foreach ($this->ancestors($phpcsFile, $functionPtr) as $ancestor) {
            if ($ancestor->hasMethod($name) === false) {
                continue;
            }

            $method = $ancestor->getMethod($name);

            if (
                $method->isPrivate() === false
                && $method->isStatic() === true
            ) {
                return true;
            }
        }

        return false;
    }

    // The property counterpart of overridesStaticMethod(). PHP rejects
    // redeclaring an inherited static property as non-static with the same
    // fatal, and skips a private ancestor property for the same reason.
    public function redeclaresStaticProperty(File $phpcsFile, int $pointer, string $property): bool
    {
        $name = ltrim($property, '$');

        foreach ($this->ancestors($phpcsFile, $pointer) as $ancestor) {
            if ($ancestor->hasProperty($name) === false) {
                continue;
            }

            $inherited = $ancestor->getProperty($name);

            if (
                $inherited->isPrivate() === false
                && $inherited->isStatic() === true
            ) {
                return true;
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
    public function isCodeSnifferClass(File $phpcsFile, int $pointer): bool
    {
        foreach ($this->ancestorNames($phpcsFile, $pointer) as $name) {
            if (str_starts_with($name, self::CODE_SNIFFER_PREFIX) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, ReflectionClass<object>>
     */
    private function ancestors(File $phpcsFile, int $pointer): array
    {
        $ancestors = [];

        foreach ($this->ancestorNames($phpcsFile, $pointer) as $name) {
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
    private function ancestorNames(File $phpcsFile, int $pointer): array
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
