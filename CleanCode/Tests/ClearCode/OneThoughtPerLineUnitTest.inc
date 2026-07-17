<?php

$profile = $user->profile;
$name = $user?->name;
$count = Order::count();
$default = self::DEFAULT_VALUE;
$instance = static::make();
$resolved = parent::resolve();
$this->name = $user->name;
$this->total = Cart::total();
usort($items, fn ($left, $right) => $left->id <=> $right->id);
format($first->name, $second->name);

$users = User::query()
    ->where('active', true)
    ->orderBy('name')
    ->get();

$query = Report::query();
$query->whereBetween('created_at', $range);
$rows = $query->get();

$greeting = "Hello {$user->profile->name}";

$city = $user->profile->address->city;
$title = Format::title()->name;
$id = $order->customer?->id;
$config = static::config()->get('key');
$value = self::instance()->value;
$built = parent::builder()->build();
$this->config->name = $value;

$result = $service->repository
    ->find($id)->name;

$dynamic = $this->{$prop}->name;
$called = $obj->{$name}()->prop;
$static = $this::{$prop}->name;

$report = $builder
    ->select('total')
    ->where('active', true)->orderBy('name');
