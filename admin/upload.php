<?php
/**
 * Admin book upload — EPUB, PDF, and comic archives (CBZ/CBR).
 *
 * Replaces the legacy upload_epub.php (hardcoded SHA256 password) and the
 * completely unauthenticated upload.php. Every file is validated by its real
 * container type, deduplicated by checksum, parsed for metadata, given a
 * cover where one can be produced, and inserted through BookImporter — the
 * same path the folder importer uses.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$messages = [];

if (is_post()) {
    csrf_verify_or_abort();

    @set_time_limit(0);

    if (empty($_FILES['book']['name']) || !is_array($_FILES['book']['name'])) {
        $messages[] = ['type' => 'error', 'text' => 'No files were selected.'];
    } else {
        $names    = $_FILES['book']['name'];
        $tmpNames = $_FILES['book']['tmp_name'];
        $errors   = $_FILES['book']['error'];
        $sizes    = $_FILES['book']['size'];

        for ($i = 0, $n = count($names); $i < $n; $i++) {
            $name = (string) $names[$i];
            if ($name === '') { continue; }

            try {
                $messages[] = upload_one_book($name, (string) $tmpNames[$i], (int) $errors[$i]);
            } catch (Throwable $e) {
                log_error($e);
                $messages[] = ['type' => 'error', 'text' => $name . ': ' . $e->getMessage()];
            }
        }
    }
}

render('admin/upload', [
    'pageTitle'  => 'Upload books',
    'activeNav'  => 'upload',
    'messages'   => $messages,
    'maxMb'      => (int) config('storage.max_upload_mb', 100),
    'accept'     => BookFormat::acceptAttribute(),
    'rarSupport' => RarTranscoder::isSupported(),
    'rarTools'   => RarTranscoder::supportedToolNames(),
    'pdfCovers'  => ThumbnailService::canRasterizePdf(),
], 'admin');


/**
 * Validate + import a single uploaded file.
 *
 * @return array{type: string, text: string}
 */
function upload_one_book(string $originalName, string $tmpPath, int $error): array
{
    if ($error !== UPLOAD_ERR_OK) {
        return ['type' => 'error', 'text' => $originalName . ': ' . upload_error_message($error)];
    }
    if (!is_uploaded_file($tmpPath)) {
        return ['type' => 'error', 'text' => $originalName . ': not a valid upload.'];
    }

    // 1. Validate by real container type (this also transcodes CBR → CBZ).
    $verdict = BookFileValidator::validate($tmpPath, $originalName);
    if (!$verdict['ok']) {
        return ['type' => 'error', 'text' => $originalName . ': ' . $verdict['error']];
    }

    // 2. Deduplicate on the checksum of the file as uploaded.
    $existing = BookRepository::findByHash($verdict['hash']);
    if ($existing) {
        if (!empty($verdict['converted'])) {
            @unlink($verdict['path']);
        }
        return [
            'type' => 'info',
            'text' => $originalName . ': already in the library as “' . $existing['title'] . '”.',
        ];
    }

    // 3. Import.
    $result = BookImporter::import($verdict, $originalName, [
        'move'        => true,
        'uploaded_by' => (int) current_user()['id'],
    ]);

    AuditLogger::log('book.upload', 'book', $result['id'], [
        'title'  => $result['title'],
        'format' => $result['format'],
        'size'   => $verdict['size'],
    ]);

    $note = '';
    if (!empty($verdict['converted'])) {
        $note = ' (converted from CBR to CBZ)';
    }
    if (!empty($verdict['page_count'])) {
        $note .= ' — ' . number_format((int) $verdict['page_count']) . ' pages';
    }

    return [
        'type' => 'success',
        'text' => $originalName . ': added as “' . $result['title'] . '”'
                . ' [' . BookFormat::label($result['format']) . ']' . $note . '.',
    ];
}

/** PHP's upload error codes, in words the admin can act on. */
function upload_error_message(int $code): string
{
    $limit = (int) config('storage.max_upload_mb', 100);
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => "file is larger than PHP's upload_max_filesize — raise it (and post_max_size) in your PHP settings.",
        UPLOAD_ERR_FORM_SIZE  => "file exceeds the {$limit} MB form limit.",
        UPLOAD_ERR_PARTIAL    => 'upload was interrupted; try again.',
        UPLOAD_ERR_NO_FILE    => 'no file was received.',
        UPLOAD_ERR_NO_TMP_DIR => 'the server has no writable temp directory.',
        UPLOAD_ERR_CANT_WRITE => 'the server could not write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'a PHP extension blocked the upload.',
        default               => "upload failed (PHP error {$code}).",
    };
}
