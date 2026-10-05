<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use MikeBronner\CleanCode\Helpers\Declarations;
use PHP_CodeSniffer\Files\File;
use PhpToken;
use ReflectionClass;
use ReflectionProperty;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\NamespaceHelper;
use Throwable;

class InheritedMembers
{
    private const CODE_SNIFFER_PREFIX = 'PHP_CodeSniffer\\';

    private array $staticReadsByFile = [];

    public function overridesUntypedParameter(File $phpcsFile, int $functionPtr): bool
    {
        $name = (new Declarations)->name($phpcsFile, $functionPtr);

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

    public function overridesStaticMethod(File $phpcsFile, int $functionPtr): bool
    {
        $name = (new Declarations)->name($phpcsFile, $functionPtr);

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

    public function inheritsUntypedProperty(File $phpcsFile, int $pointer, string $property): bool
    {
        $declaration = $this->nearestDeclaration($phpcsFile, $pointer, $property);

        return $declaration !== null
            && $declaration->isPrivate() === false
            && $declaration->hasType() === false;
    }

    public function fulfilsStaticRead(File $phpcsFile, int $pointer, string $property): bool
    {
        if ($this->nearestDeclaration($phpcsFile, $pointer, $property) !== null) {
            return false;
        }

        $name = ltrim($property, '$');

        foreach ($this->lineage($phpcsFile, $pointer) as $ancestor) {
            $file = $ancestor->getFileName();

            if (
                is_string($file) === true
                && in_array($name, $this->staticReadsIn($file), true) === true
            ) {
                return true;
            }
        }

        return false;
    }

    public function declaredReturnType(File $phpcsFile, int $functionPtr): ?string
    {
        $name = (new Declarations)->name($phpcsFile, $functionPtr);

        if ($name === null) {
            return null;
        }

        foreach ($this->ancestors($phpcsFile, $functionPtr) as $ancestor) {
            if ($ancestor->hasMethod($name) === false) {
                continue;
            }

            $type = $ancestor->getMethod($name)
                ->getReturnType();

            if ($type === null) {
                continue;
            }

            return (string) $type;
        }

        return null;
    }

    public function isCodeSnifferClass(File $phpcsFile, int $pointer): bool
    {
        foreach ($this->ancestorNames($phpcsFile, $pointer) as $name) {
            if (str_starts_with($name, self::CODE_SNIFFER_PREFIX) === true) {
                return true;
            }
        }

        return false;
    }

    private function ancestors(File $phpcsFile, int $pointer): array
    {
        return array_values(array_filter(array_map(
                fn (string $name): ?ReflectionClass => $this->reflect($name),
                $this->ancestorNames($phpcsFile, $pointer)
            )));
    }

    private function reflect(string $name): ?ReflectionClass
    {
        try {
            $exists = class_exists($name) || interface_exists($name);
        } catch (Throwable) {
            return null;
        }

        return $exists === true ? new ReflectionClass($name) : null;
    }

    private function lineage(File $phpcsFile, int $pointer): array
    {
        $classPtr = ClassHelper::getClassPointer($phpcsFile, $pointer);
        $extended = $classPtr === null ? false : $phpcsFile->findExtendedClassName($classPtr);

        if (is_string($extended) === false) {
            return [];
        }

        $lineage = [];
        $ancestor = $this->reflect(ltrim(NamespaceHelper::resolveClassName($phpcsFile, $extended, $classPtr), '\\'));

        while ($ancestor !== null) {
            $lineage[] = $ancestor;
            $ancestor = $ancestor->getParentClass() ?: null;
        }

        return $lineage;
    }

    private function nearestDeclaration(File $phpcsFile, int $pointer, string $property): ?ReflectionProperty
    {
        $name = ltrim($property, '$');

        foreach ($this->lineage($phpcsFile, $pointer) as $ancestor) {
            if ($ancestor->hasProperty($name) === true) {
                return $ancestor->getProperty($name);
            }
        }

        return null;
    }

    private function staticReadsIn(string $file): array
    {
        if (isset($this->staticReadsByFile[$file]) === true) {
            return $this->staticReadsByFile[$file];
        }

        $tokens = array_values(array_filter(
                PhpToken::tokenize((string) file_get_contents($file)),
                static fn (PhpToken $token): bool => $token->isIgnorable() === false
            ));
        $reads = [];

        foreach ($tokens as $index => $token) {
            if (
                $token->is(T_STATIC) === true
                && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true
                && ($tokens[$index + 2] ?? null)?->is(T_VARIABLE) === true
            ) {
                $reads[] = ltrim($tokens[$index + 2]->text, '$');
            }
        }

        $this->staticReadsByFile[$file] = array_values(array_unique($reads));

        return $this->staticReadsByFile[$file];
    }

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
