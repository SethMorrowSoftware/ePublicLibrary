<?php
/**
 * EpubParser — extracts metadata + cover image from an EPUB file.
 *
 * Replaces the regex-on-XML approach in the legacy code. Uses DOMDocument
 * and XPath which correctly handles:
 *   - DC namespace prefixes (dc:, opf:)
 *   - Multiple <dc:creator> entries
 *   - Whitespace and attribute reordering
 *   - HTML entities inside elements
 */

defined('APP_BOOTED') or exit;

class EpubParser
{
    /**
     * Parse an EPUB file. Returns an associative array of metadata fields
     * plus 'cover_data' (binary, or null) and 'cover_mime'.
     *
     * Throws RuntimeException on unrecoverable problems (bad ZIP, missing
     * container.xml). Missing-but-recoverable metadata becomes 'Unknown'.
     */
    public static function parse(string $epubPath): array
    {
        $meta = [
            'title'         => 'Untitled',
            'author'        => 'Unknown',
            'language'      => null,
            'publisher'     => null,
            'published'     => null,
            'isbn'          => null,
            'description'   => null,
            'subjects'      => [],
            'cover_data'    => null,
            'cover_mime'    => null,
            'cover_filename'=> null,
        ];

        $zip = new ZipArchive();
        $rc = $zip->open($epubPath);
        if ($rc !== true) {
            throw new RuntimeException('Could not open EPUB: zip error ' . $rc);
        }
        try {
            $containerXml = $zip->getFromName('META-INF/container.xml');
            if ($containerXml === false) {
                throw new RuntimeException('EPUB missing META-INF/container.xml');
            }
            $opfPath = self::extractOpfPath($containerXml);
            if ($opfPath === null) {
                throw new RuntimeException('EPUB container.xml has no rootfile');
            }

            $opfXml = $zip->getFromName($opfPath);
            if ($opfXml === false) {
                throw new RuntimeException("EPUB missing OPF at {$opfPath}");
            }

            $opfData = self::parseOpf($opfXml);
            foreach ($opfData as $k => $v) {
                if ($v !== null && $v !== '' && $v !== []) {
                    $meta[$k] = $v;
                }
            }

            // Cover extraction
            $cover = self::extractCover($zip, $opfXml, $opfPath, $opfData);
            if ($cover !== null) {
                $meta['cover_data']     = $cover['data'];
                $meta['cover_mime']     = $cover['mime'];
                $meta['cover_filename'] = $cover['filename'];
            }
        } finally {
            $zip->close();
        }
        return $meta;
    }

    private static function extractOpfPath(string $containerXml): ?string
    {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($containerXml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$loaded) {
            return null;
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('cn', 'urn:oasis:names:tc:opendocument:xmlns:container');
        $nodes = $xp->query('//cn:rootfile/@full-path');
        if ($nodes !== false && $nodes->length > 0) {
            return $nodes->item(0)->nodeValue;
        }
        // Fallback: without namespace
        $nodes = $xp->query('//rootfile/@full-path');
        return ($nodes !== false && $nodes->length > 0) ? $nodes->item(0)->nodeValue : null;
    }

    private static function parseOpf(string $opfXml): array
    {
        $out = [
            'title'       => null,
            'author'      => null,
            'language'    => null,
            'publisher'   => null,
            'published'   => null,
            'isbn'        => null,
            'description' => null,
            'subjects'    => [],
            '_cover_id'   => null,
            '_manifest'   => [],
        ];

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        if (!$dom->loadXML($opfXml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return $out;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('opf', 'http://www.idpf.org/2007/opf');
        $xp->registerNamespace('dc',  'http://purl.org/dc/elements/1.1/');

        $first = function (string $q) use ($xp): ?string {
            $n = $xp->query($q);
            if ($n !== false && $n->length > 0) {
                $value = trim($n->item(0)->nodeValue ?? '');
                return $value === '' ? null : $value;
            }
            return null;
        };
        $all = function (string $q) use ($xp): array {
            $list = [];
            $n = $xp->query($q);
            if ($n !== false) {
                foreach ($n as $node) {
                    $v = trim($node->nodeValue ?? '');
                    if ($v !== '') { $list[] = $v; }
                }
            }
            return $list;
        };

        $out['title']     = $first('//dc:title');
        $authors          = $all('//dc:creator');
        $out['author']    = $authors ? implode(', ', $authors) : null;
        $out['language']  = $first('//dc:language');
        $out['publisher'] = $first('//dc:publisher');
        $out['published'] = $first('//dc:date');
        $out['description'] = $first('//dc:description');
        $out['subjects']  = $all('//dc:subject');

        // ISBN can appear as <dc:identifier opf:scheme="ISBN">...</dc:identifier>
        $ids = $xp->query('//dc:identifier');
        if ($ids !== false) {
            foreach ($ids as $node) {
                $scheme = $node->getAttribute('opf:scheme') ?: ($node->getAttributeNS('http://www.idpf.org/2007/opf', 'scheme'));
                if (stripos($scheme, 'isbn') !== false) {
                    $out['isbn'] = trim($node->nodeValue);
                    break;
                }
            }
        }

        // Cover meta — newer EPUBs use <meta name="cover" content="id">
        $coverMeta = $xp->query('//opf:meta[@name="cover"]/@content');
        if ($coverMeta !== false && $coverMeta->length > 0) {
            $out['_cover_id'] = $coverMeta->item(0)->nodeValue;
        }

        // Manifest map: id => [href, media_type, properties]
        $items = $xp->query('//opf:manifest/opf:item');
        if ($items !== false) {
            foreach ($items as $item) {
                $id   = $item->getAttribute('id');
                $href = $item->getAttribute('href');
                $mime = $item->getAttribute('media-type');
                $props = $item->getAttribute('properties');
                $out['_manifest'][$id] = ['href' => $href, 'mime' => $mime, 'properties' => $props];
            }
        }

        return $out;
    }

    /**
     * Cover-extraction strategies, in order:
     *   1. <meta name="cover" content="id"> → manifest[id]
     *   2. Manifest item with properties="cover-image" (EPUB 3)
     *   3. Manifest item id="cover" or id="cover-image"
     *   4. Manifest item with href matching /cover.*\.(jpe?g|png|gif|webp)/i
     *   5. First image in the manifest
     */
    private static function extractCover(ZipArchive $zip, string $opfXml, string $opfPath, array $opf): ?array
    {
        $baseDir = dirname($opfPath);
        $baseDir = $baseDir === '.' ? '' : $baseDir;
        $manifest = $opf['_manifest'] ?? [];

        $candidates = [];

        if (!empty($opf['_cover_id']) && isset($manifest[$opf['_cover_id']])) {
            $candidates[] = $manifest[$opf['_cover_id']];
        }

        foreach ($manifest as $id => $item) {
            if (stripos($item['properties'] ?? '', 'cover-image') !== false) {
                $candidates[] = $item;
            }
        }

        foreach (['cover', 'cover-image'] as $idCandidate) {
            if (isset($manifest[$idCandidate])) {
                $candidates[] = $manifest[$idCandidate];
            }
        }

        foreach ($manifest as $item) {
            if (preg_match('/cover.*\.(jpe?g|png|gif|webp)$/i', $item['href'] ?? '')
                && strpos((string) $item['mime'], 'image/') === 0) {
                $candidates[] = $item;
            }
        }

        foreach ($manifest as $item) {
            if (strpos((string) ($item['mime'] ?? ''), 'image/') === 0) {
                $candidates[] = $item;
                break;
            }
        }

        foreach ($candidates as $cand) {
            $href = $cand['href'] ?? '';
            if ($href === '') { continue; }
            $path = $baseDir === '' ? $href : $baseDir . '/' . $href;
            $path = self::normalizePath($path);
            $data = self::readEntry($zip, $path);
            if ($data !== null) {
                return [
                    'data'     => $data,
                    'mime'     => $cand['mime'] ?? 'image/jpeg',
                    'filename' => basename($path),
                ];
            }
        }
        return null;
    }

    /**
     * Read the ZIP entry a manifest href names.
     *
     * Hrefs are URIs, so `Cover%20Image.jpg` refers to the entry
     * `Cover Image.jpg`; and some packagers write the manifest in a different
     * case from the archive. Looking the raw href up used to lose every such
     * cover. Returns null when no entry matches or it is empty.
     */
    private static function readEntry(ZipArchive $zip, string $path): ?string
    {
        foreach (array_unique([$path, rawurldecode($path)]) as $name) {
            $data = $zip->getFromName($name);
            if ($data === false) {
                $data = $zip->getFromName($name, 0, ZipArchive::FL_NOCASE);
            }
            if ($data !== false && $data !== '') {
                return $data;
            }
        }
        return null;
    }

    /** Collapse `a/./b/../c` → `a/c` without touching real paths. */
    private static function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') { continue; }
            if ($seg === '..') {
                array_pop($parts);
            } else {
                $parts[] = $seg;
            }
        }
        return implode('/', $parts);
    }
}
