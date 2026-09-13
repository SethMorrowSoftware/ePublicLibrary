<?php
/**
 * Advanced search.
 *
 * Combines full-text search with structured filters (genre, language,
 * year range, minimum rating). Sortable by relevance / title / author /
 * date / rating / popularity.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$q         = trim((string) ($_GET['q'] ?? ''));
$tagSlug   = trim((string) ($_GET['tag'] ?? ''));
$language  = trim((string) ($_GET['language'] ?? ''));
$yearMin   = (int) ($_GET['year_min'] ?? 0);
$yearMax   = (int) ($_GET['year_max'] ?? 0);
$minRating = (float) ($_GET['min_rating'] ?? 0);
$format    = trim((string) ($_GET['format'] ?? ''));
$format    = BookFormat::isValid($format) ? $format : '';
$sortBy    = (string) ($_GET['sort'] ?? ($q !== '' ? 'relevance' : 'created'));
$sortDir   = (string) ($_GET['order'] ?? 'desc');
$page      = max(1, (int) ($_GET['page'] ?? 1));

$hasQuery = $q !== '' || $tagSlug !== '' || $language !== ''
         || $yearMin > 0 || $yearMax > 0 || $minRating > 0 || $format !== '';

$result = $hasQuery
    ? BookRepository::paginate([
        'page'       => $page,
        'per_page'   => 24,
        'search'     => $q,
        'field'      => 'all',
        'tag_slug'   => $tagSlug ?: null,
        'language'   => $language ?: null,
        'year_min'   => $yearMin ?: null,
        'year_max'   => $yearMax ?: null,
        'min_rating' => $minRating ?: null,
        'format'     => $format ?: null,
        'sort_by'    => $sortBy,
        'sort_dir'   => $sortDir,
    ])
    : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 24];

// Filter UI inputs
$allGenres   = TagRepository::listAll('genre');
$allLangs    = BookRepository::distinctLanguages();
$yearRange   = BookRepository::yearRange();
$formatCounts = BookRepository::formatCounts();

render('library/search', [
    'pageTitle'  => $q !== '' ? 'Search: ' . $q : 'Advanced search',
    'pageClass'  => 'search-page',
    'q'          => $q,
    'tagSlug'    => $tagSlug,
    'language'   => $language,
    'yearMin'    => $yearMin,
    'yearMax'    => $yearMax,
    'minRating'  => $minRating,
    'format'     => $format,
    'formatCounts' => $formatCounts,
    'sortBy'     => $sortBy,
    'sortDir'    => $sortDir,
    'hasQuery'   => $hasQuery,
    'result'     => $result,
    'allGenres'  => $allGenres,
    'allLangs'   => $allLangs,
    'yearRange'  => $yearRange,
], 'app');
