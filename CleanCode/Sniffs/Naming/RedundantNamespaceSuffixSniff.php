<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

/**
 * Flags a class, interface, trait, or enum whose own name ends in a suffix its
 * namespace already says (Classes: Class Naming, #27) —
 * docs/standards/classes-class-naming.md.
 *
 * `App\Services\BillingService` states "service" twice. The namespace already
 * files the type under `Services`, so the suffix adds no information at the
 * declaration and only lengthens every reference to it. The standard's fix is
 * `App\Services\Billing`, with `use App\Services\Billing as BillingService;`
 * at any call site where the fuller name genuinely reads better — the alias is
 * where a suffix belongs, because there it disambiguates rather than repeats.
 *
 * The rule is global inside the application: `app/Http`, `app/Models` and
 * `app/Livewire/Forms` carry no exemption, so `UserController`,
 * `StoreUserRequest` and `LoginForm` are all reported under their matching
 * folders even though Laravel's own generators produce those names. Every
 * ancestor counts, not only the immediate parent — `App\Services\Billing\
 * BillingGateway` is measured against `Services` and `Billing` both.
 *
 * ## What the redundancy is read from
 *
 * The **declared namespace**, never the file path. Under PSR-4 the two say the
 * same thing for any autoloadable class, and the namespace is the half a
 * checkout location cannot corrupt: PHP_CodeSniffer hands a sniff a fully
 * resolved absolute path, so a package checked out under
 * /home/app/project/src/ reads as living in `app/` when the path is trusted.
 * The sibling CleanCode.Routes.ApiControllerNamespace sniff pays for that hazard
 * with an anchoring rule and a namespace veto; reading the namespace alone
 * avoids it outright. A class in the global namespace says nothing about where
 * it is filed and is left alone.
 *
 * ## Scope: below the application root
 *
 * Only segments **below** the `App` root are candidates, which is what the
 * standard means by "the base folder within the app folder":
 *
 * - `App\Services\PaymentService` — `Services` is below the root: reported.
 * - `App\Application` — nothing below the root: nothing to repeat.
 * - `Tests\Unit\Services\PaymentServiceTest`, and this package's own
 *   `MikeBronner\CleanCode\Sniffs\Naming\…Sniff` — not headed by `App`, so out
 *   of scope. That gate is load-bearing rather than incidental: PHPUnit
 *   discovers a test class by its `Test` suffix and PHP_CodeSniffer discovers a
 *   sniff by its `Sniff` suffix, so a rule that reached `Tests\` or `Sniffs\`
 *   would demand a rename the tooling forbids.
 *
 * The root has to *head* the namespace, because that is what PSR-4 maps onto
 * the `app/` directory. `Vendor\App\Repositories\UserRepository` is an
 * installed package's own layout rather than this project's app folder, and is
 * left alone.
 *
 * ## Matching
 *
 * A segment is repeated when the declared name ends in that segment, or in a
 * singular form of it, **at a PascalCase word boundary** — the matched suffix
 * has to start at an upper-case letter, or be the whole name. Without that
 * boundary a two- or three-letter segment collides with ordinary English:
 * `App\Ads\Squad` ends in the letters of `Ad` and is not repeating anything.
 * `Controller` matching the whole of `App\Http\Controllers\Controller` is
 * deliberate — a type named for nothing but its own folder is the purest case
 * of the redundancy.
 *
 * The letters either side are compared case-insensitively, as PHP resolves
 * names: `App\WEBHOOKS\StripeWebhook` and `App\Notifications\OrderNOTIFICATION`
 * are both the violation, however the segment and the suffix are spelled. What
 * the boundary needs is an upper-case letter *starting* the suffix, so the one
 * spelling that escapes is a suffix run onto the name in lower case
 * (`Ordernotification`). That is the accepted cost of the boundary and not a
 * gap to close: nothing else in the ruleset reports it either — PSR-1's
 * `Squiz.Classes.ValidClassName` reads it as one PascalCase word and stays
 * silent — but matching inside a word is exactly what makes `App\Ads\Squad` a
 * violation, and a false report on an ordinary name costs more than a missed
 * one on a misspelled suffix.
 *
 * Singularisation is a set of candidates rather than one answer, because the
 * `-es` ending is ambiguous (`Statuses` drops `es`, `Cases` drops `s`) and no
 * token stream carries a dictionary. Both spellings are offered and the rule
 * matches whichever one the developer actually wrote. A plural this misses
 * therefore costs a report, never a false one: `Statuse` is not a suffix anyone
 * writes, so an unrecognised plural stays silent instead of demanding a
 * rename to a non-word — the failure mode the first implementation of this
 * standard shipped.
 *
 * One report per declaration, anchored on the deepest matching segment — the
 * nearest folder is the one whose name the developer echoed. Detection only:
 * renaming a type means rewriting every reference to it across the project,
 * which no single-file fixer can do, so nothing is offered to the fixer and
 * there is no autofixed.php fixture.
 */
class RedundantNamespaceSuffixSniff implements Sniff
{
    /**
     * The OO declaration keywords this sniff reads, each mapped to the word its
     * message calls it by.
     *
     * The family is every token type `File::getDeclarationName()` accepts,
     * minus `T_FUNCTION`: a function is not filed in a folder of its own kind,
     * and the standard is about the types a project declares. The sibling
     * CleanCode.Pattern.DisallowRepositoryClasses sniff reads the same four for
     * the same reason — a redundantly named interface, trait, or enum is the
     * identical violation under a different keyword.
     *
     * `T_ANON_CLASS` is absent rather than excluded: `getDeclarationName()`
     * rejects it, and an anonymous class has no name to repeat anything with.
     *
     * @var array<int, string>
     */
    private const DECLARATION_KEYWORDS = [
        T_CLASS => 'Class',
        T_ENUM => 'Enum',
        T_INTERFACE => 'Interface',
        T_TRAIT => 'Trait',
    ];

    /**
     * The namespace segment that marks the application root, compared
     * case-insensitively. `App` is Laravel's root namespace and the one the
     * standard is written against.
     */
    private const APPLICATION_ROOT = 'app';

    /**
     * Plurals whose singular no suffix rule reaches, lowercased on both sides.
     *
     * Only the shapes that are plausible folder names are listed. An omission
     * costs a missed report, never a false one — see the class docblock.
     *
     * @var array<string, string>
     */
    private const IRREGULAR_PLURALS = [
        'analyses' => 'analysis',
        'children' => 'child',
        'criteria' => 'criterion',
        'indices' => 'index',
        'matrices' => 'matrix',
        'people' => 'person',
    ];

    /**
     * Registration is derived from DECLARATION_KEYWORDS rather than restated,
     * so a keyword added to the map cannot be left unregistered, and a
     * registered keyword cannot arrive at process() with no word to call it by.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_keys(self::DECLARATION_KEYWORDS);
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A keyword with no name after it is what PHP_CodeSniffer hands a sniff
        // for a file caught mid-edit. There is no name to read a suffix off, so
        // the declaration passes over rather than being reported while the
        // developer is still typing it.
        if ($name === null) {
            return;
        }

        // Deepest segment first: the folder nearest the declaration is the one
        // whose name a developer echoes, so it is the one the message names.
        foreach (array_reverse($this->segmentsBelowApplicationRoot($phpcsFile, $stackPtr)) as $segment) {
            $suffix = $this->redundantSuffix($name, $segment);

            if ($suffix === null) {
                continue;
            }

            $phpcsFile->addError(
                '%s %s repeats its own %s namespace segment: drop the redundant "%s" suffix and'
                    . ' alias the import at the call sites that read better with it (see'
                    . ' docs/standards/classes-class-naming.md)',
                $stackPtr,
                'Found',
                [self::DECLARATION_KEYWORDS[$phpcsFile->getTokens()[$stackPtr]['code']], $name, $segment, $suffix]
            );

            return;
        }
    }

    /**
     * The declared namespace's segments below the application root, in declared
     * order and declared casing, or [] when there are none.
     *
     * [] is the answer to two different questions — "this declaration is not in
     * the application" and "this declaration sits directly on the application
     * root" — and they are deliberately not told apart: neither has a folder
     * below the root whose name could be repeated, so both are silence.
     *
     * The root is the namespace's **first** segment, which is what PSR-4 makes
     * it: composer.json maps the prefix `App\` onto the `app/` directory, so
     * `App` heading a namespace is the only thing that says "this is the
     * application's own folder". An `App` segment further down belongs to
     * something else — `Vendor\App\Repositories` is an installed package's
     * namespace, not this project's app folder, and the standard is written
     * about the latter.
     *
     * @return array<int, string>
     */
    private function segmentsBelowApplicationRoot(File $phpcsFile, int $stackPtr): array
    {
        $namespace = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        if ($namespace === null) {
            return [];
        }

        $segments = explode('\\', $namespace);
        $root = array_shift($segments);

        return strtolower((string) $root) === self::APPLICATION_ROOT ? $segments : [];
    }

    /**
     * The suffix of $name that repeats $segment, as the developer spelled it,
     * or null when the name does not end in that segment at a word boundary.
     */
    private function redundantSuffix(string $name, string $segment): ?string
    {
        foreach ($this->suffixCandidates(strtolower($segment)) as $candidate) {
            $offset = strlen($name) - strlen($candidate);

            if ($offset < 0 || strtolower(substr($name, $offset)) !== $candidate) {
                continue;
            }

            // The whole name, or a PascalCase word of it. A match starting
            // mid-word is a letter collision rather than a repeated folder.
            if ($offset === 0 || ctype_upper($name[$offset]) === true) {
                return substr($name, $offset);
            }
        }

        return null;
    }

    /**
     * The lowercased suffixes that count as repeating $segment: the segment
     * itself, plus every singular form a suffix rule can derive from it.
     *
     * Longest first, so a name ending in the plural is reported against the
     * plural rather than against the singular hiding inside it — and so a
     * segment that is already singular is always matched whole, whatever
     * shorter stem a suffix rule also derived from it.
     *
     * The empty string is dropped rather than filtered for tidiness: a
     * two-letter segment ending in `es` truncates to nothing, and an empty
     * candidate matches the end of every name at an offset that has no
     * character to test for a word boundary.
     *
     * @return array<int, string>
     */
    private function suffixCandidates(string $segment): array
    {
        $candidates = [$segment, ...$this->singularForms($segment)];

        $candidates = array_unique(array_filter(
            $candidates,
            static fn (string $candidate): bool => $candidate !== ''
        ));

        usort($candidates, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return $candidates;
    }

    /**
     * Every singular $segment could be the plural of, lowercased.
     *
     * The `-es` ending is genuinely ambiguous — `Statuses` drops `es` and
     * `Cases` drops `s` — so both stems are returned and the declared name
     * decides which one it wrote.
     *
     * A segment that is already singular is not told apart from a plural, and
     * does not need to be: `Status` yields the stem `Statu` alongside itself,
     * and nothing is named for a stem that is not a word. The segment itself is
     * always a candidate and is always the longer one, so it wins the match
     * wherever both would hold.
     *
     * @return array<int, string>
     */
    private function singularForms(string $segment): array
    {
        if (isset(self::IRREGULAR_PLURALS[$segment]) === true) {
            return [self::IRREGULAR_PLURALS[$segment]];
        }

        if (str_ends_with($segment, 'ies') === true && strlen($segment) > 3) {
            return [substr($segment, 0, -3) . 'y'];
        }

        if (str_ends_with($segment, 'es') === true) {
            return [substr($segment, 0, -2), substr($segment, 0, -1)];
        }

        return str_ends_with($segment, 's') === true ? [substr($segment, 0, -1)] : [];
    }
}
