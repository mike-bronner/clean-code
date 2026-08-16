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
can be checked. A class named `*Action` (or living in an `Actions`
sub-namespace) should expose a single public entry point — conventionally
`__invoke()` or `handle()` — and additional public methods indicate unrelated
entry points that belong in their own Action class. Focused sniff issue:
[#161](https://github.com/mike-bronner/phpcs-rules/issues/161).

## What remains code review

Everything upstream of the Action class's existence: recognizing that logic
sprawled across controllers or helpers encodes one concept, choosing to
extract it, and drawing the boundary of what belongs inside. The shape sniff
can keep an existing Action class honest, but only review can notice the
class that should exist and doesn't.
