# gadgetim-core — module contract

Data and behaviour for the Gadgetim storefront (WordPress 6.7+, PHP 8.1+, WooCommerce 9.6+).
The theme `wp-content/themes/gadgetim` is presentation only and calls the helpers below.
Specification: `docs/architecture.md` (+ `docs/decisions.md` where they differ).

## Layout and loading

```
gadgetim-core.php     header, constants, requirements check, autoloader, functions.php, Plugin::boot() at file load
includes/Plugin.php   boot order, activation, WooCommerce check (plugins_loaded), text domain (init)
includes/Autoloader.php   Gadgetim\Core\X\Y  ->  includes/X/Y.php
includes/functions.php    global theme helpers (see below)
includes/Support/     Digits Jalali Gregorian Normalize Phone Options Cache RateLimit Html Context  (no Module class)
includes/Settings/    Schema Settings Capabilities Module Admin/{SettingsPage,Fields,Notices,Tools}
templates/admin/      settings views;  assets/admin/  admin.css admin.js;  languages/  translations
```

Constants: `GADGETIM_CORE_VERSION` ('0.1.0'), `GADGETIM_CORE_API` (1), `GADGETIM_CORE_FILE/DIR/URL`,
`GADGETIM_CORE_MIN_PHP/WP/WC`. Conventions: namespace `Gadgetim\Core`, options `gadgetim_*`, hooks
`gadgetim_*`, meta `_gtm_*`, text domain `gadgetim-core`, English source strings, LF, no build step.

### Module contract

A module is `includes/<Module>/` with `final class Module` (namespace `Gadgetim\Core\<Module>`) exposing
`public static function register(): void`. `Plugin::boot()` runs **at plugin-file load** and calls
`register()` in this order, skipping missing modules:
`Support` (no Module; `Context::register()` + `Cache::register_triggers()` run here), `Settings`, `I18n`,
`Pricing`, `Catalog`, `ContactMode`, `Checkout`, `Seo`, `Rest`, `Import`, `Cli`.

Rules for `register()`:

- Only add hooks. WooCommerce is **not loaded yet** (plugins load alphabetically): hook `plugins_loaded`,
  `woocommerce_loaded` or `init` for anything that touches WC. `Plugin::has_woocommerce()` is reliable
  from `plugins_loaded` on; `Plugin::woocommerce_ok()` also checks the version.
- No `__()` at load time (translations load on `init`): translate inside the hooked callbacks.
- Seed options on the `gadgetim_core_activate` action (fired by `Plugin::activate()`), react to version
  changes on `gadgetim_core_upgrade( $from, $to )`, clean up on `gadgetim_core_deactivate`.
- Never edit `Plugin.php`, `functions.php` or another module's directory. Code against the other
  module's public API and guard with `class_exists()` / `method_exists()`.
- Other actions: `gadgetim_core_booted( string[] $modules )`, `Plugin::modules()`, `Plugin::has_module('I18n')`.
- `Plugin::view( 'admin/x', $args )` / `Plugin::render()` include `templates/<name>.php` (the view reads `$args`).

## Settings (`Gadgetim\Core\Settings`)

One option `gadgetim_settings` (autoload). Fields are addressed by **dotted paths** that mirror the array:
`contact.phone` ⇄ `['contact']['phone']`; form inputs are `gadgetim_settings[contact][phone]`.

```php
use Gadgetim\Core\Settings\{Schema, Settings, Capabilities};
use Gadgetim\Core\Settings\Admin\{Tools, Notices, SettingsPage};

// Add a tab (call on init/admin_init, or inside the gadgetim_settings_schema filter):
Schema::add_tab( 'contact_mode', __( 'Contact instead of price', 'gadgetim-core' ), 'manage_woocommerce', [
    'contact.enabled'    => [ 'type' => 'toggle', 'label' => __( 'Enable call-for-price', 'gadgetim-core' ), 'default' => false ],
    'contact.show_price' => [ 'type' => 'toggle', 'label' => __( 'Still show the price', 'gadgetim-core' ), 'default' => true ],
    'contact.label'      => [ 'type' => 'text', 'i18n' => true, 'label' => __( 'Button label', 'gadgetim-core' ),
                              'default' => [ 'fa' => 'تماس بگیرید', 'en' => 'Ask for price' ] ],
], [ 'order' => 25 ] );
// $fields may also be [ section_id => [ 'label' => …, 'fields' => [...] ] ].
Schema::add_section( 'brand', 'social', __( 'Social', 'gadgetim-core' ) );
Schema::add_field( 'brand', 'social', 'brand.x_handle', [ 'type' => 'text', 'label' => 'X' ] );
// A tab that lives on its own admin page: Schema::add_tab( 'currencies', $label, 'manage_options', [], [ 'url' => admin_url( 'admin.php?page=gadgetim-currencies' ) ] );
// A tab with custom UI:        Schema::add_tab( 'redirects', $label, 'manage_options', [], [ 'render' => [ RedirectsPage::class, 'render' ] ] );

Settings::get( 'contact.phone' );                 // stored value, else schema default, else null
Settings::get( 'views.sample', 5 );
Settings::get_lang( 'brand.tagline', 'en' );      // i18n field: en, else fa, else ''
Settings::set( 'contact.enabled', true );         // sanitized by the field, fires gadgetim_settings_saved
Settings::update( [ 'contact.phone' => '0917…', 'contact.whatsapp' => '…' ] );
Settings::raw(); Settings::defaults(); Settings::all();
```

Field definition keys: `type` (text, textarea, richtext, number, toggle, select, phone, email, url, image,
repeater), `label`, `description`, `default`, `i18n` (text, textarea, richtext, repeater → one value per
language `['fa'=>…,'en'=>…,'ar'=>…]`), `options` (array or callable, select), `min`/`max`/`step`,
`placeholder`, `rows`, `capability` (per field), `sanitize` (callable `($value, $field)` overriding the type
sanitizer), `render` (callable `($path, $field, $value, $lang)` printing a custom control), `attrs`, `order`.
Sanitizers by type follow architecture §5.10 (`sanitize_text_field`, `wp_kses_post`, `Phone::normalize`,
`esc_url_raw`, `absint`, whitelist for select, clamp for number). A form save posts **one tab**; only that
tab's fields are read and the tab capability is checked again in the sanitizer.

Core tabs: `brand` (Brand & contact: `brand.name/tagline/logo_id/logo_dark_id`, `contact.phone/whatsapp/
telegram/instagram/email/address/hours`), `trust` (`trust.badges`, `trust.return_window`, `trust.delivery`,
`trust.guarantee`, `trust.verification_steps`, `trust.guarantee_page`), `catalog` (`views.enabled`,
`views.sample`), `tools`. `Schema::CORE_DEFAULTS` seeds `i18n.fallback = '404'` (I18n adds the UI).
Filters: `gadgetim_settings_schema`, `gadgetim_settings_defaults`, `gadgetim_tab_capability`.
Action: `gadgetim_settings_saved( $new, $old )` (Support\Cache purges all on it).

**Capabilities (D11):** `Capabilities::for_tab( $id )` returns `manage_options` for `redirects`, `strings`,
`tools`, `languages`, `currencies` no matter what the tab declares; everything else defaults to
`manage_woocommerce` (`manage_options` when WooCommerce is absent). `Capabilities::can( $id )`,
`Capabilities::allowed_tabs()`, `Capabilities::default_capability()`.

**Admin menu:** top-level `gadgetim` (dashicons-store), registered on `admin_menu` priority 9. Add sub-pages
with `add_submenu_page( SettingsPage::SLUG, … )` at priority 10+. `SettingsPage::url( 'tools' )` gives a tab
URL; `SettingsPage::is_our_screen( $hook )` tells whether to enqueue assets (hooks `gadgetim_page_*`).

**Tools:** `Tools::add( $id, $label, callable $run, $capability = 'manage_options', $args )` on `admin_init`.
`$run( $tool )` returns a string (success message), `WP_Error`, bool, or `['message'=>…,'type'=>…]`; it runs
through `admin-post.php?action=gadgetim_run_tool` with nonce `gadgetim_run_tool_{id}`, `manage_options`
**and** the tool's own capability. `$args`: `description`, `button`, `confirm`, `order`, `danger`,
`render` (callable printing custom UI — progress bars, file inputs — in place of the button;
`Tools::form( $tool )` prints the standard button). Core tools: `purge-cache`, `flush-rewrites`.

**Notices:** `Notices::flag_product( $product_id, $message )` (one persistent warning per product, linking to
its edit screen), `Notices::add( $id, $message, $type, [ 'link' => [...], 'capability' => …, 'dismissible' => true ] )`,
`Notices::clear( $id )`, `Notices::clear_product( $id )`, `Notices::has( $id )`. Stored in option
`gadgetim_notices`; shown on the dashboard, Gadgetim pages and product screens; dismiss via admin-post + nonce.

## Support

- **Cache** (§7.4): `Cache::suspend()` / `resume()` (nestable; resume does not flush), `purge_all()`,
  `purge_post( $id )` (post + home + shop + term archives incl. ancestors, expanded through the filter
  `gadgetim_cache_purge_urls( $urls, $post_id, $context )` — **I18n must add `/en/` and `/ar/` variants
  there**), `purge_term( $term_id, $taxonomy )`, `purge_url( $url )` (exact URL, no expansion),
  `purge_urls( [...] )`, `flush_pending()`, `adapter()` / `adapter_label()`. Actions
  `gadgetim_cache_purge_all/post/url` (before adapters) and `gadgetim_cache_purged_all` (after, exactly
  once per purge-all). Triggers already wired: `save_post`/`deleted_post` (viewable types), term create/
  edit/delete, `wp_update_nav_menu`, `gadgetim_settings_saved`, review approval. Reprice/import: call
  `suspend()` first, then `resume(); purge_all();` at the end.
- **Context**: `Context::is_storefront_render()` (true between `template_redirect` and `shutdown` on
  non-admin, non-REST, non-CLI, non-cron requests, and inside `Context::with_render( fn )` for emails),
  `Context::$suspend_translations` (int counter) / `with_suspended( fn )` / `translations_suspended()`
  (also true in wp-admin without AJAX, CLI, cron, REST writes), `is_rest_request()` (by path or
  `?rest_route=`), `rest_route()`, `is_read_method()`, `is_wc_ajax()`, `is_cli()`, `is_cron()`,
  `request_path()`, `path_without_home()`.
- **Digits**: `to_persian/to_arabic( $s, $protect = true )` (tokens with Latin letters, URLs, e-mails and
  entities are left alone), `to_latin( $s )` (everything → ASCII), `to_set( $s, 'fa'|'arab'|'latn' )`,
  `for_lang( $s, $lang )`, `set_for_lang( $lang )` (from `gadgetim_languages[lang]['digits']`, filter
  `gadgetim_digit_set`), `format_number( $n, $lang, $decimals )` (fa/ar `٬`/`٫`, en `,`/`.`),
  `html( $html, $lang )` (text nodes only).
- **Jalali**: `to_jalali( gy, gm, gd )`, `to_gregorian( jy, jm, jd )`, `is_leap`, `days_in_month`,
  `parts( $ts )`, `to_timestamp(...)`, `format( $ts, 'short|medium|long|datetime|time|iso' | pattern, $lang )`.
- **Gregorian**: `format( $ts, $pattern, 'en'|'ar'|'fa' )` with built-in month/weekday names — the fallback
  `gadgetim_date()` uses when the active locale is not the requested language.
- **Normalize**: `text( $s )` (search blobs: NFC, ي/ك→ی/ک, ة→ه, marks and ZWNJ stripped, digits→ASCII,
  lowercase), `path( $path_or_url )` (redirect keys), `letters`, `strip_marks`, `nfc`, `persian` (display hygiene).
- **Phone**: `normalize` (digits + leading `+`), `digits_only`, `to_e164_iran`, `is_iran_mobile`, `is_iran`,
  `national( $raw, $lang )`, `tel_href`, `whatsapp_url( $raw, $text )`, `telegram_url( $handle )`, `handle`.
- **RateLimit**: `allow( $key, $limit, $window )` (token bucket per hashed IP, one transient per hour),
  `retry_after(...)`, `reset`, `client_ip()` (REMOTE_ADDR; filter `gadgetim_client_ip`), `ip_hash( $scope )`.
- **Options**: `get/update/delete/bool/int/string/array( 'name' )` (prefix optional), `languages()`
  (merged over `Options::LANGUAGES_DEFAULT`, §3.1), `language( $code )`, `public_languages()`,
  `language_codes()`, `currencies()`, `redirects()`, `strings()`, `home()`, `rate_history()`, `prices_version()`.
- **Html**: `attrs( [...] )`, `tag( $name, $attrs, $inner )`, `text( $name, $text, $attrs )`, `classes(...)`,
  `bdi`, `ltr`, `kses_inline`, `json` / `json_script( $id, $data )` / `data_attr` (§5.10 flags), `icon`.

## Global helpers (theme API, `includes/functions.php`)

| Function | Behaviour | Delegates to |
|---|---|---|
| `gadgetim_lang()` | current language code, `'fa'` without I18n | `I18n\Languages::current()` |
| `gadgetim_lang_dir( $lang = null )` | `'rtl'` / `'ltr'` | `Options::language()` |
| `gadgetim_number( $n, $decimals = 0, $lang = null )` | `۲٬۳۴۰٬۰۰۰` | `Digits::format_number()` |
| `gadgetim_digits( $text, $lang = null )` | digits of a string in the language's set | `Digits::for_lang()` |
| `gadgetim_date( $time, $style = 'medium', $lang = null )` | Jalali for fa; Gregorian for en/ar via `wp_date()` when the active locale matches, else `Gregorian::format()` | `Jalali::format()` |
| `gadgetim_price( $product_or_amount, $opts = [] )` | `<p class="price" data-id><span class="price__now"><bdi>…</bdi> <span class="price__unit">تومان</span></span> <del class="price__was">…</del> <span class="price__off">‎-۱۴٪</span></p>`; filter `gadgetim_price_html`, `gadgetim_price_unit` | `Pricing\Display::html()` when present |
| `gadgetim_setting( $path, $default = null )` | any setting | `Settings::get()` |
| `gadgetim_t( $key, $default = '' )` | per-language settings text: current language → `$default` → fa; filter `gadgetim_t` | `Settings` |
| `gadgetim_term_name( WP_Term $t )` | translated term name, term object untouched | `I18n\TermTranslations::name()` |

Every helper is wrapped in `function_exists()` so the theme's `inc/compat.php` stubs never collide.

## Lifecycle

`Plugin::activate()` seeds `gadgetim_settings` from the schema defaults and stores `gadgetim_core_version`;
`maybe_upgrade()` (admin_init) repeats that when the version changes and fires `gadgetim_core_upgrade`.
`uninstall.php` deletes the `gadgetim_*` options and transients **only** when `GADGETIM_REMOVE_DATA` is true.

Lint: `php -l` on every file (`tools/lint.sh`); the Local PHP is
`C:/Users/GHADER/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe`.
