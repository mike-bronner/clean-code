<?php

$book->authorName;
$book->author;
$this->title;

$book->author->save();
$book->author->name();
$book->getAuthor()->name;
$repository->find($id)->name;
$book->author()->name;

$a->b['x']->c;
$a->b()['x']->c;
$a->b->{$c};

Book::query()->first()->author->name;
static::make()->author->name;
self::$instance->author->name;
Book::$registry->author->name;

($book)->author;
(new Book())->author->name;
(Book::query()->first())->author->name;
foo($book)->author->name;

$a->{$b}->c;
$a->{'b'}->c;
$a->$b->c;

class Book
{
    public function getAuthorNameAttribute(): string
    {
        return $this->authorName;
    }
}
