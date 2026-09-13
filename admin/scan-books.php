<?php
/**
 * Bulk importer for a folder of book files on disk.
 *
 * Walks a directory tree, imports every EPUB / PDF / CBZ / CBR it finds, and
 * leaves the originals untouched (files are copied into storage, not moved),
 * so an admin can verify the results before deleting anything.
 *
 * Idempotent — files already in the library (matched by checksum) are skipped,
 * so re-running after adding more files is safe.
 *
 * The default source is the legacy `books/` directory from the v7.x
 * proof-of-concept; an admin can point it at any directory inside the install.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$defaultDir = project_path('books');
$requested  = trim((string) ($_POST['dir'] ?? $_GET['dir'] ?? ''));
$scanDir    = $requested !== '' ? resolve_import_dir($requested) : $defaultDir;

$results = [];
$summary = ['imported' => 0, 'skipped' => 0, 'failed' => 0];

if (is_post()) {
    csrf_verify_or_abort();

    if ($scanDir === null) {
        flash('error', 'That folder is outside the installation directory.');
        redirect('admin/scan-books.php');
    }
    if (!is_dir($scanDir)) {
        flash('error', 'No such folder: ' . $scanDir);
        redirect('admin/scan-books.php');
    }

    @set_time_limit(0);
    @ignore_user_abort(true);

    foreach (import_candidates($scanDir) as $file) {
        $result = import_one($file);
        $results[] = $result;
        $summary[$result['status']]++;
    }

    AuditLogger::log('books.folder_import', null, null, $summary + ['dir' => $scanDir]);
}

render('admin/scan-books', [
    'pageTitle'  => 'Import folder',
    'activeNav'  => 'import',
    'scanDir'    => $scanDir ?? $defaultDir,
    'defaultDir' => $defaultDir,
    'dirExists'  => $scanDir !== null && is_dir($scanDir),
    'extensions' => BookFormat::UPLOAD_EXTENSIONS,
    'results'    => $results,
    'summary'    => $summary,
], 'admin');


/**
 * Resolve an admin-supplied folder, refusing anything outside the install.
 * Returns null when the path escapes the project root.
 */
function resolve_import_dir(string $input): ?string
{
    $root = realpath(project_path()) ?: project_path();
    $candidate = $input;
    if ($candidate === '' || $candidate[0] !== '/') {
        $candidate = $root . '/' . ltrim($candidate, '/');
    }
    $real = realpath($candidate);
    if ($real === false) {
        return null;
    }
    if ($real !== $root && strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/**
 * Every importable file under $dir, sorted for a predictable report.
 *
 * @return SplFileInfo[]
 */
function import_candidates(string $dir): array
{
    $exts = BookFormat::UPLOAD_EXTENSIONS;
    $found = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        if (in_array(strtolower($file->getExtension()), $exts, true)) {
            $found[] = $file;
        }
    }
    usort($found, static fn(SplFileInfo $a, SplFileInfo $b): int
        => strnatcasecmp($a->getPathname(), $b->getPathname()));
    return $found;
}

/**
 * @return array{path: string, status: string, reason: string}
 */
function import_one(SplFileInfo $file): array
{
    $path = $file->getPathname();
    $name = $file->getFilename();

    try {
        $verdict = BookFileValidator::validate($path, $name);
        if (!$verdict['ok']) {
            return ['path' => $path, 'status' => 'failed', 'reason' => $verdict['error']];
        }

        $existing = BookRepository::findByHash($verdict['hash']);
        if ($existing) {
            if (!empty($verdict['converted'])) {
                @unlink($verdict['path']);
            }
            return [
                'path'   => $path,
                'status' => 'skipped',
                'reason' => 'already in the library as “' . $existing['title'] . '”',
            ];
        }

        // A converted CBR lives in the temp dir and should be moved into
        // storage; an original file on disk must only ever be copied.
        $result = BookImporter::import($verdict, $name, [
            'move'        => !empty($verdict['converted']),
            'uploaded_by' => (int) current_user()['id'],
        ]);

        AuditLogger::log('book.imported_folder', 'book', $result['id'], ['source' => $path]);

        return [
            'path'   => $path,
            'status' => 'imported',
            'reason' => 'imported as “' . $result['title'] . '” [' . BookFormat::label($result['format']) . ']',
        ];
    } catch (Throwable $e) {
        log_error($e);
        return ['path' => $path, 'status' => 'failed', 'reason' => $e->getMessage()];
    }
}
