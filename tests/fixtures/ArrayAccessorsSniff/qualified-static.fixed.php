<?php

declare(strict_types=1);

final class QualifiedStaticReader
{
    public function rooted(): mixed
    {
        return data_get(\App\Registry::$items, 'key');
    }

    public function qualified(): mixed
    {
        return data_get(App\Registry::$items, 'key');
    }

    public function relative(): mixed
    {
        return data_get(namespace\Registry::$items, 'key');
    }
}
