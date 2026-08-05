<?php

$book->author->name;
$book?->author?->name;
$book->author?->name;
$this->author->name;
$book->author->address->city;
$order->customer->name ?? '';
$a->b()->c->d;
$a->b['x']->c->d;
$books[0]->author->name;
$book->{$relation}->address->city;

$name = $book
    ->author
    // the relationship hop
    ->name;

class BookController
{
    public function show(Book $book): string
    {
        return $book->author->name;
    }
}
