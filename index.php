<?php
/**
 * Library home page.
 *
 * Two modes:
 *   1. Home (no parameters): rails — Continue Reading (logged in), Recently
 *      Added, Top Rated — followed by the first page of the library grid.
 *   2. List (any search, filter, sort, order or page parameter): the
 *      paginated grid with sort controls. "See all" on a rail, the header's
 *      sort selects and every pagination link land here.
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

// Home and list share one page size, so page 2 of the list carries on
// exactly where the grid on the home page stopped.
$perPage = 24;

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

// Sort, order and page narrow nothing down, but asking for one of them is
// asking for the list rather than the home page. Without this, "See all"
// and the sort selects just reloaded the home page.
$wantsList = isset($_GET['sort']) || isset($_GET['order']) || isset($_GET['page']);

// ---- Home mode: rails + the first page of the library --------------------
if (!$hasFilter && !$wantsList) {
    $user = current_user();
    $continueReading = $user
        ? ProgressRepository::continueReading((int) $user['id'], 6)
        : [];

    render('library/home', [
        'pageTitle'       => config('app_name', 'ePublicLibrary'),
        'pageClass'       => 'library-page',
        'continueReading' => $continueReading,
        'recentlyAdded'   => BookRepository::recentlyAdded(8),
        'topRated'        => BookRepository::topRated(8, 1),  // include books with >=1 review
        'library'         => BookRepository::paginate(['page' => 1, 'per_page' => $perPage]),
    ]);
    exit;
}

// ---- List mode: grid ----------------------------------------------------
$result = BookRepository::paginate([
    'page'       => $page,
    'per_page'   => $perPage,
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
    'hasFilter'    => $hasFilter,
    'sortLabel'    => sort_label($sortBy, $sortDir),
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


/**
 * How the list is ordered, in words for its heading — so landing here from
 * "See all" on a rail visibly did something.
 */
function sort_label(string $sortBy, string $sortDir): string
{
    $desc = strtolower($sortDir) === 'desc';
    switch ($sortBy) {
        case 'created':   return $desc ? 'newest first' : 'oldest first';
        case 'rating':    return $desc ? 'highest rated first' : 'lowest rated first';
        case 'popular':   return $desc ? 'most read first' : 'least read first';
        case 'published': return $desc ? 'by publication date, newest first' : 'by publication date';
        case 'author':    return $desc ? 'by author, Z → A' : 'by author';
        case 'relevance': return 'by relevance';
        default:          return $desc ? 'by title, Z → A' : 'by title';
    }
}
