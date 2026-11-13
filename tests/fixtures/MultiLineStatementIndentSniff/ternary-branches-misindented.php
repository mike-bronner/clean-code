<?php

$value = $isLive
    ? $isPaid
    && $isListed
    : $isDraft
    || $isPending;

$value = $isLive ? $isPaid
    : $isDraft
    || $isPending;

$value = $isLive
    ?: $isPaid
    && $isListed;

$value = [
    $isLive ? $isPaid : $isDraft,
    $isOpen
        && $isFree,
];

$value = [check($isLive ? $isPaid : $isDraft), check(
        $isOpen
            && $isFree
    )];

$value = $isLive
    ? $isPaid
    : $isDraft
        or $isPending;

$value = $isLive
    ? $isPaid
    : $isDraft
    and $isPending
        && $isOpen;

$value = $isLive
    ? $isPaid
    : $isDraft
        xor $isPending;
