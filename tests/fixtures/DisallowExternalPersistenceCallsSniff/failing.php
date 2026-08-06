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
$this->agent->save();

class UserController
{
    public function store(User $user): void
    {
        $user->save();
    }

    public function destroy(User $user): void
    {
        $user->delete();
    }
}
