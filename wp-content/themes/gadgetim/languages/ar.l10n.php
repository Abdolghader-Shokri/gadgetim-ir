<?php
/**
 * Loader for the Arabic translation of the Gadgetim theme.
 *
 * load_theme_textdomain() reads translations bundled inside a theme from
 * <theme>/languages/{locale}.l10n.php (here: ar.l10n.php), while the canonical
 * file keeps the {domain}-{locale} name used by the .po/.pot tooling (gadgetim-ar.l10n.php,
 * generated from gadgetim-ar.po with `wp i18n make-php`).
 * This file only returns that array, so there is exactly one copy of the strings.
 * Do not delete it: without it the theme shows English on the storefront.
 *
 * @package Gadgetim
 */

return require __DIR__ . '/gadgetim-ar.l10n.php';
