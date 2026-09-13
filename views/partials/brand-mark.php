<?php
defined('APP_BOOTED') or exit;
/**
 * The ePublicLibrary open-book mark. Matches assets/favicon.svg.
 *
 * Vars:
 *   $class  string  — class applied to the <svg>
 */
$class = $class ?? 'brand-mark';
?>
<svg class="<?= e($class) ?>" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
    <rect width="64" height="64" rx="14" fill="var(--accent-600)"/>
    <path d="M32 17 Q22 18.5 13 21.5 v22 Q22 41 32 42 Z" fill="#ffffff"/>
    <path d="M32 17 Q42 18.5 51 21.5 v22 Q42 41 32 42 Z" fill="#f0efe9"/>
    <rect x="30.8" y="16.6" width="2.4" height="25.8" rx="1.2" fill="var(--accent-700)"/>
</svg>
