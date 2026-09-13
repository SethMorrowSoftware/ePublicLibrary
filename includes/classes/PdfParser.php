<?php
/**
 * PdfParser — pull title / author / page count out of a PDF without a
 * PDF library.
 *
 * Deliberately modest in scope. A full PDF parser is a large dependency and
 * this app only needs enough to seed the catalogue row; anything it cannot
 * work out is left null and the admin edits it (or the filename is used as
 * the title, exactly like an EPUB with no <dc:title>).
 *
 * The tricky part of a modern PDF is that the document catalogue and the
 * /Info dictionary are frequently inside compressed object streams, so a
 * regex over the raw bytes finds nothing. We therefore scan the raw file
 * AND every FlateDecode stream we can inflate.
 */

defined('APP_BOOTED') or exit;

class PdfParser
{
    /** Never inflate more than this much data — a guard against zip bombs. */
    private const MAX_INFLATED_BYTES = 32 * 1024 * 1024;

    /** Only the first N MB are scanned for metadata; page counts use the tail too. */
    private const MAX_SCAN_BYTES = 24 * 1024 * 1024;

    /**
     * @return array{title: ?string, author: ?string, published: ?string,
     *               description: ?string, subjects: string[], page_count: ?int}
     */
    public static function parse(string $path): array
    {
        $meta = [
            'title'       => null,
            'author'      => null,
            'published'   => null,
            'description' => null,
            'subjects'    => [],
            'page_count'  => null,
        ];

        $raw = @file_get_contents($path, false, null, 0, self::MAX_SCAN_BYTES);
        if ($raw === false || $raw === '') {
            return $meta;
        }

        $haystacks = [$raw];
        foreach (self::inflatedStreams($raw) as $stream) {
            $haystacks[] = $stream;
        }

        foreach ($haystacks as $text) {
            $meta['title']       ??= self::infoValue($text, 'Title');
            $meta['author']      ??= self::infoValue($text, 'Author');
            $meta['description'] ??= self::infoValue($text, 'Subject');
            $meta['published']   ??= self::pdfDate(self::infoValue($text, 'CreationDate'));
            if (!$meta['subjects']) {
                $meta['subjects'] = self::keywords(self::infoValue($text, 'Keywords'));
            }
        }

        // XMP metadata is plain XML and often present when /Info is not.
        if ($meta['title'] === null || $meta['author'] === null) {
            $xmp = self::xmp($raw);
            $meta['title']  ??= $xmp['title'];
            $meta['author'] ??= $xmp['author'];
        }

        $meta['page_count'] = self::pageCount($path, $haystacks);

        foreach (['title', 'author', 'description'] as $k) {
            if ($meta[$k] !== null) {
                $meta[$k] = self::clean($meta[$k]);
                if ($meta[$k] === '') {
                    $meta[$k] = null;
                }
            }
        }
        return $meta;
    }

    /** Page count only — used when re-indexing an already-imported book. */
    public static function pageCountOf(string $path): ?int
    {
        $raw = @file_get_contents($path, false, null, 0, self::MAX_SCAN_BYTES);
        if ($raw === false) {
            return null;
        }
        $haystacks = array_merge([$raw], self::inflatedStreams($raw));
        return self::pageCount($path, $haystacks);
    }

    /* --------------------------------------------------------------------- */

    /**
     * /Count on the page-tree root is authoritative when we can find it;
     * otherwise fall back to counting page objects.
     *
     * @param string[] $haystacks
     */
    private static function pageCount(string $path, array $haystacks): ?int
    {
        $best = null;

        foreach ($haystacks as $text) {
            // The root Pages node is the /Pages object that also carries /Kids.
            if (preg_match_all('~/Type\s*/Pages\b[^>]{0,400}?/Count\s+(\d{1,7})~s', $text, $m)) {
                foreach ($m[1] as $n) {
                    $n = (int) $n;
                    if ($n > 0 && ($best === null || $n > $best)) {
                        $best = $n;
                    }
                }
            }
            if ($best === null && preg_match_all('~/Count\s+(\d{1,7})\s*/Kids~s', $text, $m2)) {
                foreach ($m2[1] as $n) {
                    $n = (int) $n;
                    if ($n > 0 && ($best === null || $n > $best)) {
                        $best = $n;
                    }
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        // Last resort: count /Type /Page objects (the negative lookahead keeps
        // /Pages nodes out of the tally).
        $counted = 0;
        foreach ($haystacks as $text) {
            $counted += preg_match_all('~/Type\s*/Page(?![sA-Za-z])~', $text);
        }
        return $counted > 0 ? $counted : null;
    }

    /** Read one key from the /Info dictionary, handling both string forms. */
    private static function infoValue(string $text, string $key): ?string
    {
        // Literal string: /Title (Some text \(escaped\))
        if (preg_match('~/' . $key . '\s*\(((?:[^()\\\\]|\\\\.|\((?:[^()\\\\]|\\\\.)*\))*)\)~s', $text, $m)) {
            return self::decodeLiteral($m[1]);
        }
        // Hex string: /Title <FEFF00480069>
        if (preg_match('~/' . $key . '\s*<([0-9A-Fa-f\s]+)>~', $text, $m)) {
            $hex = preg_replace('/\s+/', '', $m[1]);
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bin = @hex2bin($hex);
            return $bin === false ? null : self::toUtf8($bin);
        }
        return null;
    }

    /** @return array{title: ?string, author: ?string} */
    private static function xmp(string $raw): array
    {
        $out = ['title' => null, 'author' => null];
        if (!preg_match('~<x:xmpmeta.*?</x:xmpmeta>~s', $raw, $m)) {
            return $out;
        }
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($m[0]);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$loaded) {
            return $out;
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        foreach ([['title', '//dc:title//*[local-name()="li"]'],
                  ['author', '//dc:creator//*[local-name()="li"]']] as [$key, $query]) {
            $nodes = $xp->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                $value = trim((string) $nodes->item(0)->nodeValue);
                $out[$key] = $value !== '' ? $value : null;
            }
        }
        return $out;
    }

    /**
     * Inflate every FlateDecode stream in the file so metadata hiding inside
     * an object stream is still visible to the regexes above.
     *
     * @return string[]
     */
    private static function inflatedStreams(string $raw): array
    {
        if (!function_exists('gzuncompress')) {
            return [];
        }
        $out = [];
        $budget = self::MAX_INFLATED_BYTES;
        $offset = 0;

        while (($pos = strpos($raw, 'stream', $offset)) !== false) {
            $offset = $pos + 6;
            // Skip the EOL that must follow the `stream` keyword.
            if (substr($raw, $offset, 2) === "\r\n") {
                $offset += 2;
            } elseif ($raw[$offset] === "\n" || $raw[$offset] === "\r") {
                $offset += 1;
            }
            $end = strpos($raw, 'endstream', $offset);
            if ($end === false) {
                break;
            }
            $len = $end - $offset;
            if ($len > 8 && $len <= $budget) {
                $chunk = substr($raw, $offset, $len);
                // Only bother when it really looks like a zlib stream.
                if ((ord($chunk[0]) & 0x0f) === 8) {
                    $plain = @gzuncompress($chunk);
                    if ($plain !== false && $plain !== '') {
                        $out[] = $plain;
                        $budget -= strlen($plain);
                        if ($budget <= 0) {
                            break;
                        }
                    }
                }
            }
            $offset = $end + 9;
        }
        return $out;
    }

    private static function decodeLiteral(string $value): string
    {
        $replacements = [
            '\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\b' => "\x08",
            '\\f' => "\x0C", '\\(' => '(', '\\)' => ')', '\\\\' => '\\',
        ];
        $value = strtr($value, $replacements);
        // Octal escapes: \053
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static function (array $m): string {
            return chr(octdec($m[1]) & 0xFF);
        }, $value) ?? $value;
        return self::toUtf8($value);
    }

    /** PDF strings are either UTF-16BE with a BOM, or PDFDocEncoding (≈Latin-1). */
    private static function toUtf8(string $value): string
    {
        if (strncmp($value, "\xFE\xFF", 2) === 0) {
            $converted = @mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16BE');
            return $converted !== false ? $converted : '';
        }
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        return $converted !== false ? $converted : '';
    }

    /** D:20240115093000+01'00' → 2024-01-15 */
    private static function pdfDate(?string $value): ?string
    {
        if ($value === null || !preg_match('/(\d{4})(\d{2})?(\d{2})?/', $value, $m)) {
            return null;
        }
        $year = (int) $m[1];
        if ($year < 1000 || $year > 3000) {
            return null;
        }
        $parts = [$m[1]];
        if (!empty($m[2])) { $parts[] = $m[2]; }
        if (!empty($m[3])) { $parts[] = $m[3]; }
        return implode('-', $parts);
    }

    /** @return string[] */
    private static function keywords(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $parts = preg_split('/[,;]+/', $value) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = self::clean($part);
            if ($part !== '' && mb_strlen($part) <= 80) {
                $out[] = $part;
            }
        }
        return array_slice(array_values(array_unique($out)), 0, 12);
    }

    private static function clean(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
