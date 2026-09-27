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

    public function declaredReturnType(File $phpcsFile, int $functionPtr): ?string
    {
        $name = $phpcsFile->getDeclarationName($functionPtr);

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
        $ancestors = [];

        foreach ($this->ancestorNames($phpcsFile, $pointer) as $name) {
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
