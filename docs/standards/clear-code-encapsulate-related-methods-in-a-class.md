# Clear Code: Encapsulate Related Methods in a Class

## Standard

- In Laravel, most business logic is contained within Models.
- Sometimes classes are needed to encapsulate concepts outside of models or
  other Laravel-prescribed functional classes.
- Action classes are optimal candidates for this purpose.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Rationale

Related methods scattered across controllers, helpers, or unrelated classes
hide the concept they collectively implement. Grouping them into one
dedicated class:

- gives the concept a **name and a home**, making it discoverable instead of
  reconstructable only by reading call sites;
- keeps each class **cohesive** — one responsibility, one reason to change;
- makes the logic **testable in isolation**, without booting the controller
  or model it used to be buried in;
- allows **reuse** from any context (controller, job, command, listener)
  without duplicating the logic.

## Example — an Action class

A single concept — publishing a post — encapsulated behind one public entry
point:

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Post;
use Illuminate\Support\Facades\Notification;

class PublishPostAction
{
    public function __invoke(Post $post): Post
    {
        $post->forceFill(['published_at' => now()])->save();

        Notification::send($post->subscribers, new PostPublished($post));

        return $post;
    }
}
```

The controller (or job, or command) delegates instead of accumulating the
logic itself:

```php
public function store(PublishPostRequest $request, PublishPostAction $publishPost): RedirectResponse
{
    $post = $publishPost(Post::findOrFail($request->validated('post_id')));

    return redirect()->route('posts.show', $post);
}
```

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. Deciding that scattered methods
*belong together* — that they implement one concept deserving its own class —
requires understanding intent and cross-file relationships. A token-based
PHPCS sniff sees one file's tokens at a time and cannot make that call.
Enforcement is via code review and developer discipline.

### Partial enforcement — Action class shape

One narrow slice **is** token-visible: once an Action class exists, its shape
can be checked. That slice is enforced by the custom sniff
`CleanCode.ClearCode.ActionSingleEntryPoint`
([#161](https://github.com/mike-bronner/clean-code/issues/161)), which warns
on every public method an Action class declares past the first.

**Which classes are examined.** Either half of the convention puts a class in
scope:

- its own name ends in `Action` — `PublishPostAction`;
- its declared namespace carries an `Actions` segment — `App\Actions\…`,
  including any namespace below it.

The two are matched with different case sensitivity, on purpose. The class name
is a *suffix* match, and `Action` is the suffix of ordinary words a project
really declares — `Transaction`, `Reaction`, `Interaction`, `Faction` — so it is
compared case-sensitively and none of those is examined. The namespace half is
an *exact segment* match, where no such collision exists, so `App\Actions` and
`app\actions` are both in scope while `App\ActionsArchive` is not.

**What is counted.** Every public method the class declares at its own top
level, in declaration order, static ones included. The first is the entry point,
whatever it is called: `__invoke()` and `handle()` are this doc's convention,
not the sniff's rule. Every method after it is reported at its own declaration.

`__construct` is never counted — an Action is constructed with its collaborators
and then invoked, so a constructor is not a second way in. Nothing else is
exempt. A public accessor handing back a result the entry point computed is
reported like any other extra public method: whether it has earned its place is
a judgement the sniff cannot make, which is exactly why this is a **warning**
and not an error. A class with one public method, or none at all, is never
reported — the rule is about a *second* way in, not a missing first one.

**Boundaries, accepted by design.**

- Convention-dependent in both directions: an Action named and placed outside
  the convention is never examined, and a class that merely matches it is
  examined whether or not it is really an Action.
- Only classes. An interface method is a contract, a trait's methods belong to
  whichever class mixes them in, an enum is not an Action, and an anonymous
  class carries no name the convention can read.
- Traits and parents are invisible: a public method mixed in or inherited is
  declared in another file, and a sniff reads one file at a time.
- Protected and private methods are never counted — an entry point is public.
- A class body PHP_CodeSniffer never saw closed is passed over in silence.
  With the file's structure unresolved, attributing methods to it would be a
  guess.
- Detection only. Splitting an Action in two means creating a class, moving a
  method, and rewriting every call site that reaches it — an architectural
  change with no mechanical rewrite — so nothing is offered to the fixer.

## What remains code review

Everything upstream of the Action class's existence: recognizing that logic
sprawled across controllers or helpers encodes one concept, choosing to
extract it, and drawing the boundary of what belongs inside. The shape sniff
can keep an existing Action class honest, but only review can notice the
class that should exist and doesn't.

Downstream of it, one judgement stays with review as well: whether a second
public method the sniff has reported is a genuine second concept or a
legitimate part of one — a public accessor over a computed result being the
case the sniff deliberately does not try to settle.
