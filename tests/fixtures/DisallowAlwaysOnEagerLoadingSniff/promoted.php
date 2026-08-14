<?php

class Post extends Model
{
    public function __construct(public array $with = ['author'])
    {
        parent::__construct();
    }
}

class Comment extends Model
{
    public function __construct(protected readonly array $with = ['post'])
    {
        parent::__construct();
    }
}

class Tag extends Model
{
    public function __construct(private array $with = [])
    {
        parent::__construct();
    }
}
