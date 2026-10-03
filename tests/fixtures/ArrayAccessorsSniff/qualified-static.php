<?php

declare(strict_types=1);

final class QualifiedStaticReader
{
    public function rooted(): mixed
    {
        return \App\Registry::$items['key'];
    }

    public function qualified(): mixed
    {
        return App\Registry::$items['key'];
    }

    public function relative(): mixed
    {
        return namespace\Registry::$items['key'];
    }
}
