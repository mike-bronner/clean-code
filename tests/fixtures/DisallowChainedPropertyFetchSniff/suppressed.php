<?php

class BookCard
{
    public function authorName(): string
    {
        // phpcs:ignore CleanCode.Models.DisallowChainedPropertyFetch -- the card reads a value object, not an Eloquent relation
        return $this->book->author->name
            ?? "";
    }

    public function authorCity(): string
    {
        return $this->book->author->city;
    }
}
