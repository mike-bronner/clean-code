<?php

class Post extends Model
{
    protected $with = [];

    public function author()
    {
        return $this->belongsTo(User::class);
    }
}

class Comment extends Model
{
    protected $with = array();
}

class Repository extends Model
{
    protected $with = [
        // No relationship is loaded on every query.
    ];
}

class Tag extends Model
{
    protected $appends = ['slug'];
}

class Category extends Model
{
    protected $With = ['parent'];
}

class Setting extends Model
{
    protected $with = self::DEFAULT_RELATIONS;
}

class Attachment extends Model
{
    protected $with;

    public function preload(): void
    {
        $with = ['owner'];

        $this->load($with);
    }
}

class Draft extends Model
{
    public function anonymous(): object
    {
        return new class extends Model {
            protected $with = ['author'];
        };
    }
}

class ReportBuilder
{
    protected $with = ['customer'];
}

class Importer extends Command
{
    protected $with = ['profile'];
}

class Article extends Model
{
    public function __construct($with = ['author'])
    {
        parent::__construct();
    }

    public static function findWith(int $id, array $with = ['author', 'comments'])
    {
        return static::query()->with($with)->find($id);
    }

    public function scopeLoaded($query, array $with = ['tags'])
    {
        return $query->with($with);
    }
}

abstract class Revision extends Model
{
    abstract public function preload(array $with = ['owner']);
}
