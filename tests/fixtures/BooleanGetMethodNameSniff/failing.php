<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\BooleanGetMethodName;

abstract class Report
{
    /**
     * @return boolean
     */
    public function getVisible()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function getPublished()
    {
        return false;
    }

    /**
     * @return bool Whether the report holds any rows.
     */
    public function getPopulated(int $threshold)
    {
        return $threshold > 0;
    }

    /**
     * @return BOOLEAN
     */
    public function _getArchived()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function getterCached()
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function GetDraft()
    {
        return true;
    }

    /**
     * @return bool
     */
    abstract protected function getLocked();
}

interface Publishable
{
    /**
     * @return bool
     */
    public function getSchedulable();
}

trait Archivable
{
    /**
     * @return bool
     */
    public function getArchivable()
    {
        return true;
    }
}

enum Visibility: string
{
    case Public = 'public';

    /**
     * @return bool
     */
    public function getRestricted()
    {
        return $this === self::Public;
    }
}

class Attributed
{
    /**
     * An attribute sits between the doc comment and the declaration, so the
     * search for the comment has to step over it.
     *
     * @return bool
     */
    #[Deprecated]
    public function getSuspended()
    {
        return true;
    }

    #[Deprecated]
    /**
     * The other order: the attribute is above the comment.
     *
     * @return bool
     */
    public function getRevoked()
    {
        return true;
    }
}
