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

($book)->author->name;
($condition ? $book : $fallback)->author->name;
(($book))->author?->name;

$fn('author')->author->name;
($fn)()->author->name;
$handlers['x']()->author->name;
$book->{$method}()->author->name;
$book->{Book::KEY}->address->city;

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
