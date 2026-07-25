<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the "Classes: Class Naming" standard — every class under a Laravel
 * application's `app/` folder carries the suffix implied by the folder it lives
 * in, so a class name announces its role.
 *
 * The required suffix is derived from the *base* folder under `app/`,
 * singularised: `app/Services/PaymentService`, `app/Jobs/SendMailJob`,
 * `app/Policies/UserPolicy`. Three folders are exceptions:
 *
 * - `app/Models` — no suffix (`app/Models/User`, not `UserModel`).
 * - `app/Livewire` — no suffix (`app/Livewire/Counter`).
 * - `app/Livewire/Forms` — the `Form` suffix, and only Livewire form classes
 *   belong here; a `Form`-suffixed class anywhere else under `app/` is flagged.
 * - `app/Http` — the suffix comes from the *second* segment rather than the
 *   base, because `Http` itself names no role:
 *   `app/Http/Controllers/UserController`, `app/Http/Requests/StoreRequest`.
 *
 * Where no suffix can be derived the sniff requires none: a class sitting
 * directly in `app/` has no base folder, and one sitting directly in `app/Http`
 * has no second segment (Laravel's own `app/Http/Kernel` is the canonical
 * example).
 *
 * The rule is Laravel-specific but harmless elsewhere: files whose path
 * contains no `app` segment are skipped entirely, so the sniff is inert in a
 * project without that layout.
 *
 * Detection only: correcting a violation means renaming the class and every
 * reference to it — imports, type hints, container bindings, string class
 * names, config and route files. A token-based fixer cannot follow those, so
 * renaming is left to the developer (or an IDE refactor).
 */
class ClassSuffixByFolderSniff implements Sniff
{
    /**
     * The suffix reserved for Livewire form classes: required by
     * `app/Livewire/Forms`, and flagged as misplaced in any folder that does not
     * derive it.
     */
    private const FORM_SUFFIX = 'Form';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $folders = $this->foldersUnderApp($phpcsFile->getFilename());

        if ($folders === null) {
            return;
        }

        // Null for a `class` keyword with no name token after it — a truncated
        // or otherwise unparseable declaration. There is no name to judge, and
        // crashing on a work-in-progress file would be worse than staying
        // quiet, so leave it to PHP's own syntax check.
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null) {
            return;
        }

        $requiredSuffix = $this->requiredSuffix($folders);

        if ($requiredSuffix !== null && str_ends_with($name, $requiredSuffix) === false) {
            $phpcsFile->addError(
                'Class %s must be suffixed with "%s" to match its folder (app/%s)',
                $stackPtr,
                'MissingSuffix',
                [$name, $requiredSuffix, implode('/', $folders)]
            );
        }

        // A Form-suffixed class is misplaced unless its own folder is one the
        // Form suffix is derived from — app/Livewire/Forms, and by the same
        // derivation any app/…/Forms folder. Flagging it there too would leave
        // such a class unnameable: required to end in Form and forbidden to.
        if ($requiredSuffix !== self::FORM_SUFFIX && str_ends_with($name, self::FORM_SUFFIX)) {
            $phpcsFile->addError(
                'Class %s carries the "Form" suffix but does not live in app/Livewire/Forms;'
                    . ' only Livewire form classes are suffixed "Form"',
                $stackPtr,
                'MisplacedFormClass',
                [$name]
            );
        }
    }

    /**
     * The folder segments between the `app` directory and the file itself, or
     * null when the path has no `app` segment at all (the file is not part of
     * a Laravel application folder, so this standard does not apply).
     *
     * The *last* `app` segment wins: a checkout whose own parent directory is
     * named `app` (a common container layout, `/app/app/Models/User.php`) must
     * still resolve against the application's `app/`, not the outer one.
     *
     * @return array<int, string>|null
     */
    private function foldersUnderApp(string $path): ?array
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        // Drop the file name; only directory segments describe the folder.
        array_pop($segments);

        $appIndexes = array_keys($segments, 'app', true);

        if ($appIndexes === []) {
            return null;
        }

        return array_slice($segments, end($appIndexes) + 1);
    }

    /**
     * The suffix the given folder path demands, or null when it demands none.
     *
     * @param array<int, string> $folders
     */
    private function requiredSuffix(array $folders): ?string
    {
        // Directly in app/ — there is no base folder to derive a role from.
        if ($folders === []) {
            return null;
        }

        if ($folders[0] === 'Models') {
            return null;
        }

        if ($folders[0] === 'Livewire') {
            return ($folders[1] ?? null) === 'Forms' ? self::FORM_SUFFIX : null;
        }

        // Http names no role of its own, so the suffix comes from the folder
        // beneath it; nested folders below that (app/Http/Controllers/Api) keep
        // the same suffix as their parent category.
        if ($folders[0] === 'Http') {
            return isset($folders[1]) ? $this->singularise($folders[1]) : null;
        }

        return $this->singularise($folders[0]);
    }

    /**
     * Turns a plural folder name into the singular suffix it implies:
     * `Services` → `Service`, `Policies` → `Policy`. Names that are already
     * singular are returned untouched, including those ending in a double `s`
     * (`Access`) which a naive trim would corrupt.
     */
    private function singularise(string $folder): string
    {
        if (str_ends_with($folder, 'ies')) {
            return substr($folder, 0, -3) . 'y';
        }

        if (str_ends_with($folder, 'ss') || str_ends_with($folder, 's') === false) {
            return $folder;
        }

        return substr($folder, 0, -1);
    }
}
