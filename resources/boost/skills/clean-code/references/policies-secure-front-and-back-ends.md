# Policies: Secure Front- and Back-Ends

- Checks should be implemented on the front end to prevent displaying of
  unwanted elements.
- Checks should be implemented on the back end to prevent execution of
  unwanted code, in the event front-end restrictions are circumvented.

The two halves are not alternatives. A hidden button is a usability
affordance, not a control: the back-end check is what actually denies the
action once someone reaches the endpoint directly.

## Compliant

```blade
@can('delete', $post)
    <button wire:click="delete">Delete</button>
@endcan

public function destroy(Post $post): RedirectResponse
{
    $this->authorize('delete', $post);
    $post->remove();

    return back();
}
```

## Non-compliant

```blade
@can('delete', $post)
    <button wire:click="delete">Delete</button>
@endcan

public function destroy(Post $post): RedirectResponse
{
    $post->remove();

    return back();
}
```

## Enforcement

Enforced by code review only. No sniff checks this standard.
