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

User::query()->update(['active' => false]);
App\Models\Order::create(['name' => $name]);
DB::table('versions')->where('abbreviation', 'RP')->update(['abbreviation' => 'NRP']);
$this->user->save();
\App\Models\User::create(['name' => $name]);
$owner = $this; notes()->update(['read' => true]);
$user::create(['name' => $name]);
static::$instance->save();
$this->user->notes()->update(['read' => true]);
$this->{$relation}()->delete();
$this->notes[0]->delete();
(new User())->save();
$this->$relation()->delete();
