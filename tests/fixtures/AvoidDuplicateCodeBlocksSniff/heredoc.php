<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * PHP_CodeSniffer gives every line of a heredoc, nowdoc, or multi-line quoted
 * string its own token, all of one type. A comparison by token type alone
 * therefore matched any long enough string body against itself. A string body
 * is text, not logic, so none of these lines are compared.
 */
class Heredoc
{
    public function countMismatched(string $table, string $foreignKey): int
    {
        return $this->selectOne(<<<SQL
            SELECT COUNT(*) AS mismatched
            FROM {$table} AS anchored
            WHERE anchored.{$foreignKey} IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM verses AS anchor_verse
                    JOIN chapters AS anchor_chapter
                        ON anchor_chapter.id = anchor_verse.chapter_id
                    JOIN books AS anchor_book
                        ON anchor_book.id = anchor_chapter.book_id
                    JOIN versions AS anchor_version
                        ON anchor_version.id = anchor_book.version_id
                    WHERE anchor_verse.id = anchored.{$foreignKey}
                        AND anchor_book.name = anchored.book_name
                        AND anchor_verse.number = anchored.verse_number
                )
            SQL);
    }

    public function countOrphans(): int
    {
        return $this->selectOne(<<<'SQL'
            SELECT COUNT(*) AS orphans
            FROM verses
            WHERE chapter_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM chapters
                    JOIN books
                        ON books.id = chapters.book_id
                    JOIN versions
                        ON versions.id = books.version_id
                    WHERE chapters.id = verses.chapter_id
                        AND books.name IS NOT NULL
                        AND versions.id IS NOT NULL
                )
            SQL);
    }

    public function countDangling(string $table): int
    {
        return $this->selectOne("
            SELECT COUNT(*) AS dangling
            FROM {$table}
            WHERE verse_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM verses
                    JOIN chapters
                        ON chapters.id = verses.chapter_id
                    JOIN books
                        ON books.id = chapters.book_id
                    WHERE verses.id = {$table}.verse_id
                        AND books.name IS NOT NULL
                )
        ");
    }

    private function selectOne(string $sql): int
    {
        return strlen($sql);
    }
}
