# Don't Optimize Early

Optimizing without a specific need (when code standards are already met and no
apparent issues exist) is pointless and can make code worse. Save optimization
for the last possible moment, as changes will inform how code should be
optimized. Premature optimization increases complexity, wastes time and
resources, and compromises code quality.

**Takeaway:** follow coding standards primarily; only optimize when the need
arises.

## Compliant

```php
$activeUsers = $users->filter(fn (User $user): bool => $user->isActive());
```

## Non-compliant

```php
$activeUsers = [];

for ($index = 0, $count = count($users); $index < $count; $index++) {
    if ($users[$index]->isActive()) {
        $activeUsers[] = $users[$index];
    }
}
```

## Enforcement

Enforced by code review only. No sniff checks this standard.
