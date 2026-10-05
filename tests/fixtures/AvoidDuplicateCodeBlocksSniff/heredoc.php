<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * PHP_CodeSniffer gives every line of a heredoc its own token, and the sniff
 * compares each line like any other. countOrphans() and countStrays() run the
 * same query, word for word, apart from the variable they interpolate, so the
 * pair is reported.
 *
 * countDangling() and countDetached() differ in one word, on the middle line
 * of the method. Each side of it is four lines long, too short to match on its
 * own, so that pair is not reported.
 *
 * Each line of a heredoc body is a single token, so no query here reaches the
 * default token minimum. The test lowers it.
 */
class Heredoc
{
    public function countOrphans(string $table): int
    {
        return $this->selectOne(<<<SQL
            SELECT COUNT(*) AS orphans
            FROM {$table}
            WHERE chapter_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM chapters
                    WHERE chapters.id = {$table}.chapter_id
                )
            SQL);
    }

    public function countStrays(string $source): int
    {
        return $this->selectOne(<<<SQL
            SELECT COUNT(*) AS orphans
            FROM {$source}
            WHERE chapter_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM chapters
                    WHERE chapters.id = {$source}.chapter_id
                )
            SQL);
    }

    public function countDangling(): int
    {
        return $this->selectOne(<<<'SQL'
            SELECT COUNT(*) AS dangling
            FROM verses
            WHERE book_id IS NOT NULL
            ORDER BY id
            LIMIT 1
            SQL);
    }

    public function countDetached(): int
    {
        return $this->selectOne(<<<'SQL'
            SELECT COUNT(*) AS dangling
            FROM verses
            WHERE book_id IS NULL
            ORDER BY id
            LIMIT 1
            SQL);
    }

    private function selectOne(string $sql): int
    {
        return strlen($sql);
    }
}
