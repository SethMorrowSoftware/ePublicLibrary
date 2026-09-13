<?php
/**
 * Library home page.
 *
 * Two modes:
 *   1. Home (no search/filter): shows rails — Continue Reading (logged in),
 *      Recently Added, Top Rated — plus a "Recently added" tail grid.
 *   2. Search/filter: shows the standard paginated grid with sort controls.
 *
 * Phase 1 sourced books from a filesystem scan; this still queries the DB
 * via BookRepository (the read path is the same as Phase 1).
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

if (!config_exists()) {
    redirect('setup.php');
}

// Back-compat JSON autocomplete endpoint
if (isset($_GET['autocomplete'])) {
    json_response(BookRepository::autocomplete((string) $_GET['autocomplete'], 8));
}

$searchTerm  = trim((string) ($_GET['search'] ?? ''));
$searchField = (string) ($_GET['field'] ?? 'all');
$sortBy      = (string) ($_GET['sort']   ?? 'title');
$sortDir     = (string) ($_GET['order']  ?? 'asc');
$tagSlug     = trim((string) ($_GET['tag']     ?? ''));
$language    = trim((string) ($_GET['language']?? ''));
$minRating   = (float)  ($_GET['min_rating'] ?? 0);
$format      = trim((string) ($_GET['format'] ?? ''));
$format      = BookFormat::isValid($format) ? $format : '';
$yearMin     = (int)    ($_GET['year_min']   ?? 0);
$yearMax     = (int)    ($_GET['year_max']   ?? 0);
$page        = max(1, (int) ($_GET['page'] ?? 1));

$hasFilter = $searchTerm !== '' || $tagSlug !== '' || $language !== ''
          || $minRating > 0   || $yearMin > 0   || $yearMax > 0 || $format !== '';

// ---- Home mode: rails ---------------------------------------------------
if (!$hasFilter) {
    $user = current_user();
    $continueReading = $user
        ? ProgressRepository::continueReading((int) $user['id'], 6)
        : [];
    $recentlyAdded = BookRepository::recentlyAdded(12);
    $topRated      = BookRepository::topRated(12, 1);  // include books with >=1 review

    render('library/home', [
        'pageTitle'       => config('app_name', 'ePublicLibrary'),
        'pageClass'       => 'library-page',
        'continueReading' => $continueReading,
        'recentlyAdded'   => $recentlyAdded,
        'topRated'        => $topRated,
        'totalBooks'      => BookRepository::totalCount(),
    ]);
    exit;
}

// ---- Search/filter mode: grid -------------------------------------------
$result = BookRepository::paginate([
    'page'       => $page,
    'per_page'   => 25,
    'search'     => $searchTerm,
    'field'      => $searchField,
    'sort_by'    => $sortBy,
    'sort_dir'   => $sortDir,
    'tag_slug'   => $tagSlug ?: null,
    'language'   => $language ?: null,
    'min_rating' => $minRating ?: null,
    'year_min'   => $yearMin ?: null,
    'year_max'   => $yearMax ?: null,
    'format'     => $format ?: null,
]);

render('library/index', [
    'pageTitle'    => $searchTerm !== ''
        ? 'Search: ' . $searchTerm
        : config('app_name', 'ePublicLibrary'),
    'pageClass'    => 'library-page',
    'books'        => $result['items'],
    'total'        => $result['total'],
    'page'         => $result['page'],
    'pages'        => $result['pages'],
    'searchTerm'   => $searchTerm,
    'queryParams'  => array_filter([
        'search'     => $searchTerm,
        'field'      => $searchField !== 'all' ? $searchField : null,
        'sort'       => $sortBy   !== 'title' ? $sortBy : null,
        'order'      => $sortDir  !== 'asc' ? $sortDir : null,
        'tag'        => $tagSlug ?: null,
        'language'   => $language ?: null,
        'min_rating' => $minRating ?: null,
        'year_min'   => $yearMin ?: null,
        'year_max'   => $yearMax ?: null,
        'format'     => $format ?: null,
    ]),
]);
