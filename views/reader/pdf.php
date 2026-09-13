<?php
defined('APP_BOOTED') or exit;
/** @var array $book */
/** @var int $pageCount */
/** @var int $startPage */
partial('page-reader-shell', [
    'book'      => $book,
    'progress'  => $progress,
    'bookmarks' => $bookmarks,
    'isGuest'   => $isGuest,
    'pageCount' => $pageCount,
    'startPage' => $startPage,
    'kind'      => 'pdf',
]);
