<?php

declare(strict_types=1);

/**
 * Qualifying switches nested inside one another, which is what the arm walk's
 * jump over a nested scope is written for.
 *
 * Every switch here qualifies on its own terms — a one-hop discriminator read
 * and scalar-literal labels throughout — so each reports its own count, and no
 * level's arms may appear in another level's number. Every count is distinct
 * from the counts around it, so a walk that leaked arms across a boundary would
 * have to change a reported number rather than coincidentally matching it.
 */

function sizeOf(object $shape, object $inner): string
{
    switch ($shape->type) {
        case 'circle':
            switch ($inner->kind) {
                case 'a':
                    return 'a';
                case 'b':
                    return 'b';
                case 'c':
                    return 'c';
                case 'd':
                    return 'd';
                case 'e':
                    return 'e';
            }

            return 'Circle';
        case 'square':
            return 'Square';
        case 'triangle':
            return 'Triangle';
    }

    return '';
}

function trailingNest(object $shape, object $inner): string
{
    switch ($shape->type) {
        case 'circle':
            return 'Circle';
        case 'square':
            return 'Square';
        case 'triangle':
            switch ($inner->kind) {
                case 'a':
                    return 'a';
                case 'b':
                    return 'b';
                case 'c':
                    return 'c';
                case 'd':
                    return 'd';
            }
    }

    return '';
}

function threeDeep(object $shape, object $inner, object $core): string
{
    switch ($shape->type) {
        case 'circle':
            switch ($inner->kind) {
                case 'a':
                    switch ($core->name) {
                        case 'p':
                            return 'p';
                        case 'q':
                            return 'q';
                        case 'r':
                            return 'r';
                        case 's':
                            return 's';
                        case 't':
                            return 't';
                    }

                    return 'a';
                case 'b':
                    return 'b';
                case 'c':
                    return 'c';
                case 'd':
                    return 'd';
            }

            return 'Circle';
        case 'square':
            return 'Square';
        case 'triangle':
            return 'Triangle';
    }

    return '';
}
