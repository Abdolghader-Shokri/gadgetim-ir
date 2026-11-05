<?php
/**
 * Loader for the Persian (Iran) translation of the Gadgetim theme.
 *
 * load_theme_textdomain() reads translations bundled inside a theme from
 * <theme>/languages/{locale}.l10n.php (here: fa_IR.l10n.php), while the canonical
 * file keeps the {domain}-{locale} name used by the .po/.pot tooling (gadgetim-fa_IR.l10n.php,
 * generated from gadgetim-fa_IR.po with `wp i18n make-php`).
 * This file only returns that array, so there is exactly one copy of the strings.
 * Do not delete it: without it the theme shows English on the storefront.
 *
 * @package Gadgetim
 */

return require __DIR__ . '/gadgetim-fa_IR.l10n.php';
