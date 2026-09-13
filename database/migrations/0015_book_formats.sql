-- Multi-format library: EPUB, PDF, and comic archives (CBZ).
--
-- `format` is the canonical reader selector. CBR uploads are transcoded to
-- CBZ during import (see BookFileValidator / ComicArchive), so only three
-- values ever reach this column; `original_format` remembers what the admin
-- actually uploaded so the UI can say "CBR" where that is the truth.
--
-- `page_count` is populated for PDF and CBZ at import time and drives the
-- page-based progress locator ("page:12") those readers store in
-- reading_progress.cfi. It stays NULL for EPUB, which uses CFIs instead.

ALTER TABLE `books`
    ADD COLUMN `format`          ENUM('epub','pdf','cbz') NOT NULL DEFAULT 'epub' AFTER `mime_type`,
    ADD COLUMN `original_format` VARCHAR(8) NULL DEFAULT NULL AFTER `format`,
    ADD COLUMN `page_count`      INT UNSIGNED NULL DEFAULT NULL AFTER `original_format`,
    ADD KEY `idx_books_format` (`format`, `status`);

-- Everything that predates this migration is an EPUB by definition.
UPDATE `books`
SET `format` = 'epub',
    `original_format` = 'epub',
    `mime_type` = 'application/epub+zip'
WHERE `mime_type` = 'application/epub+zip' OR `mime_type` = '' OR `mime_type` IS NULL;
