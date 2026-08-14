<?php

// Positive: one access per line — a single link is a single thought.
$profile = $user->profile;
$name = $user?->name;
$count = Order::count();
$default = self::DEFAULT_VALUE;
$instance = static::make();
$resolved = parent::resolve();

// Positive: assigning one access to one property.
$this->name = $user->name;
$this->total = Cart::total();

// Positive: a fluent chain broken one link per line.
$users = User::query()
    ->where('active', true)
    ->orderBy('name')
    ->get();

// Positive: the same chain kept as separate statements instead.
$query = Report::query();
$query->whereBetween('created_at', $range);
$rows = $query->get();

// Positive: interpolation inside a string is one thought.
$greeting = "Hello {$user->profile->name}";

// Positive: a property chain broken one link per line.
$city = $user->profile
    ->address
    ->city;

// Positive: a nullsafe link on its own continuation line.
$id = $order->customer
    ?->id;

// Positive: static and parent roots follow the same rule.
$config = static::config()
    ->get('key');
$value = self::instance()
    ->value;
$built = parent::builder()
    ->build();

// Positive: a dynamic member is a link like any other.
$dynamic = $this->{$prop}
    ->name;
$called = $obj->{$name}()
    ->prop;

// Positive: a closure argument is one thought on its line.
usort($items, fn ($left, $right) => $left->id <=> $right->id);
