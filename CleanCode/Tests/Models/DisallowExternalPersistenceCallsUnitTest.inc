<?php

$user->save();
$order->update(['status' => 'shipped']);
$post->delete();
$tags->create(['name' => $name]);
$user?->save();
$USER->SAVE();
User::query()->firstOrFail()->delete();
Order::factory()->create();
$callable = $user->delete(...);

$this->save();
$this->update(['name' => $name]);
$this?->delete();
User::create(['name' => $name]);
static::create(['name' => $name]);
parent::update(['name' => $name]);

$user->saveQuietly();
$repository->persist($user);
$wasSaved = $user->save;

class UserController
{
    public function store(User $user): void
    {
        $user->save();
    }

    public function update(int $id): void
    {
    }
}
