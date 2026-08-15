<?php

class Book
{
    public function getAuthorNameAttribute(): string
    {
        // phpcs:ignore CleanCode.Models.DisallowChainedPropertyFetch -- accessor body: the one place this traversal belongs
        return $this->author->name
            ?? "";
    }

    public function getAuthorCityAttribute(): string
    {
        return $this->author->city;
    }
}
