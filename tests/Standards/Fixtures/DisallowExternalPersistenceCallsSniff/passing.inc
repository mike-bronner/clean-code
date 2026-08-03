<?php

$this->save();
$this->update(['name' => $name]);
$this?->delete();
$this->delete(...);

User::create(['name' => $name]);
static::create(['name' => $name]);
parent::update(['name' => $name]);

$user->saveQuietly();
$user->updateOrFail();
$repository->persist($user);

$wasSaved = $user->save;
$pending = $order->update;

$user->{$method}();
$user->{'save'}();

class Agent
{
    public function addListingInfo(ListingInfo $listingInfo): void
    {
        $this->listing_id = $listingInfo->id;
        $this->save();
    }

    public function create(): void
    {
    }

    public function update(int $id): void
    {
    }

    public function delete(): void
    {
    }
}
