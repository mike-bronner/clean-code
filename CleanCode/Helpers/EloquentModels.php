<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

final class EloquentModels
{
    private const BASE_CLASSES = [
        'illuminate\database\eloquent\model',
        'illuminate\foundation\auth\user',
        'illuminate\database\eloquent\relations\pivot',
        'illuminate\database\eloquent\relations\morphpivot',
    ];

    private const MODELS_SEGMENT = 'models';

    public function isModel(File $phpcsFile, int $classPtr): bool
    {
        $namespace = (string) NamespaceHelper::findCurrentNamespaceName($phpcsFile, $classPtr);

        if ($this->hasModelsSegment($namespace) === true) {
            return true;
        }

        $extends = $phpcsFile->findExtendedClassName($classPtr);

        if (is_string($extends) === false) {
            return false;
        }

        $base = ltrim(NamespaceHelper::resolveClassName($phpcsFile, $extends, $classPtr), '\\');

        return in_array(strtolower($base), self::BASE_CLASSES, true);
    }

    public function hasModelsSegment(string $name): bool
    {
        foreach (explode('\\', $name) as $segment) {
            if (strtolower($segment) === self::MODELS_SEGMENT) {
                return true;
            }
        }

        return false;
    }
}
