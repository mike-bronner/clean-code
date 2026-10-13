# Testing: Databases (SQLite caveats)

Do not use SQLite for testing if:

- You are using JSON fields.
- You require exact float value calculations based on decimal fields.
- You have table alterations in your migrations.
- You have raw queries which manipulate dates.

## Compliant

```text
<env name="DB_CONNECTION" value="mysql"/>

$table->json('settings');
```

## Non-compliant

```text
<env name="DB_CONNECTION" value="sqlite"/>

$table->json('settings');
```

## Enforcement

Enforced by code review only. No sniff checks this standard.
