# Runbook — i18n tests (trilingual routing)

Covers architecture §3 (routing, slugs, visibility, e-mails). Two layers: the PHP test
suite (`tests/I18n/`) that runs against the Local database, and the HTTP matrix from §3.2
that is run with `curl` against Local (and, before launch, against production).

Conventions: `$PHP` is Local's PHP, `$INI` its rendered `php.ini`, `$HOST` the site.

```bash
PHP="C:/Users/GHADER/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
INI="C:/Users/GHADER/AppData/Roaming/Local/run/sqs5AIpIj/conf/php/php.ini"
HOST="http://gadgetim.local"
```

## 1. PHP test suite

```bash
"$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/I18n/run.php
"$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/I18n/run.php --filter=Slugs
```

| Class | What it proves |
|---|---|
| `RequestDetectTest` | `Request::detect()`: prefix wins; `/enzo-brand/` is Persian; `?lang=`/`X-Gadgetim-Lang` only for GET/HEAD to `wc/store` and `gadgetim/v1`; `PUT wc/v3 … ?lang=en` is Persian; wc-ajax/admin-ajax use the referer prefix; install sub-directory stripped |
| `UrlsTest` | the `home_url` filter: inert before `do_parse_request`, prefixed after, never for Persian, excluded paths (`wp-json`, `wp-admin`, `wp-login.php`, `wp-content`…), `relative` branch (`WC_AJAX::get_endpoint`), `with_lang()` nesting; **`delete_option('rewrite_rules')` then first request `/en/shop/` keeps the `robots.txt`/`favicon.ico`/sitemap rules**; `redirect_canonical` `/en` → `/en/` |
| `SlugsTest` | uniqueness (another object's native slug, translated slug in any language, WooCommerce page/endpoint/base slugs, language prefixes → `-2`); native-first inbound resolution; `/en/shop/` with `is_shop()` true; `/en/product/{translated}/` resolves while `/product/{translated}/` 404s; outbound links per language (products, hierarchical categories, hierarchical pages, WooCommerce pages untouched); Persian slug under `/en/` → 301; previous translated slug → 301 (posts and terms); visibility gate on `WP_Query`, `woocommerce_related_products`, `Visibility::filter_ids()` |
| `TermIntegrityTest` | `wp_update_term()` and product saves under `with_lang('en')` leave `wp_terms.name` / `post_title` untouched; term objects never altered (only `TermTranslations::name()` / `gadgetim_term_name()` translate); published-status gate; §5.10 sanitisers; search blobs normalised (Arabic letters → Persian, digits → ASCII, cross-language names) |

The suite creates fixtures with the `gtm-test-` prefix and removes them in `tearDown()`.
After a run, these must all be zero/empty:

```bash
"$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php -r 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name LIKE \"gtm-test-%\""), " ", (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->terms} WHERE slug LIKE \"gtm-test-%\""), "\n";'
```

When the plugin is not active, `run.php` loads it manually (the `init`-time registrations —
menus, settings tabs, term fields — are then not exercised; the routing filters are).

## 2. HTTP matrix (§3.2 day-one list)

Prerequisites on Local: `gadgetim-core` active, pretty permalinks, at least one product
with a **published** English translation (metabox → EN → status *Published*), one with
none, and one top-level category translated. `X` below is the translated English slug,
`P` the Persian slug of the same product, `A/B` a translated category path.

Headers only, no redirects followed. Expected status/location in the right column.

```bash
c() { curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "$@"; }
```

| # | Request | Expect |
|---|---|---|
| 1 | `c "$HOST/en/"` | `200`; `<html dir="ltr" lang="en">`; `<link rel="alternate" hreflang="fa-IR" …>` only when `en` is public; `<meta name='robots' content='noindex, follow'>` while `en` is not public |
| 2 | `c "$HOST/en/shop/"` | `200`, English chrome, only translated products listed, pagination links start with `/en/shop/page/2/` |
| 3 | `c "$HOST/en/product/X/"` | `200`, `<title>` is the English title, canonical `/en/product/X/` |
| 4 | `c "$HOST/en/product/P/"` | `301` → `/en/product/X/` |
| 5 | `c "$HOST/en/product/X"` (no slash) | `301` → `/en/product/X/` (`redirect_canonical`) |
| 6 | `c "$HOST/en/product-category/A/B/page/2/"` | `200` (or `404` when the category has fewer than one page of translated products); every product link under `/en/` |
| 7 | `c "$HOST/en/my-account/orders/"` | `302` to `/en/my-account/` for a guest (login form), `200` logged in; endpoint slugs untranslated |
| 8 | `c "$HOST/en/?s=charger&post_type=product"` | `200`; results match the `_gtm_search_en` blob only; untranslated products never appear |
| 9 | `c -H "Referer: $HOST/en/shop/" "$HOST/en/?wc-ajax=get_refreshed_fragments" -X POST` | `200`, JSON, fragment HTML in English, `Cache-Control: no-store` |
| 10 | `/en/?wc-ajax=checkout` (real checkout from `/en/checkout/`) | order gets `_gtm_lang = en`; line items Persian in wp-admin; admin "New order" e-mail Persian + RTL; customer e-mail English + LTR |
| 11 | `c "$HOST/en/wp-sitemap.xml"` | `404` while `en` is not public; `200` once public, index links `/en/wp-sitemap-posts-product-1.xml`, product list = published translations only |
| 12 | `c "$HOST/en"` | `301` → `/en/` |
| 13 | `c "$HOST/enzo-brand/"` | Persian page (or Persian 404), no `/en/` prefixing anywhere in the HTML |
| 14 | `c "$HOST/en/checkout/order-received/123/?key=wc_order_…"` | `200` in English for an order placed under `/en/`; "View order" links under `/en/my-account/` |
| 15 | `delete_option('rewrite_rules')` then `c "$HOST/en/shop/"`, then `c "$HOST/robots.txt"`, `c "$HOST/favicon.ico"`, `c "$HOST/wp-sitemap.xml"` | `200`, `200`, `200`/`302`, `200` — the first prefixed request must not persist rules without the root-only entries |
| 16 | `curl -s "$HOST/wp-json/wc/store/v1/cart?lang=en"` | English strings/labels in the JSON |
| 17 | `curl -s -X PUT -u key:secret "$HOST/wp-json/wc/v3/products/categories/5?lang=en" -d '{"description":"x"}'` | treated as Persian: `wp_terms.name` unchanged, response `name` Persian |
| 18 | `curl -s "$HOST/wp-json/gadgetim/v1/search?q=xiaomi&lang=en" -D -` | `200`, `Cache-Control: public, max-age=300`, items with English titles and `/en/product/…` URLs; the 31st request within a minute from one IP → `429` with `Retry-After` |
| 19 | `c "$HOST/robots.txt"` | `Disallow: /en/` and `Disallow: /ar/` while not public; `Sitemap: $HOST/en/wp-sitemap.xml` once public |
| 20 | `c "$HOST/en/product/U/"` (untranslated product) | `404` with the default fallback; with *Languages → "show"*: `200`, Persian content blocks with `lang="fa" dir="rtl"`, `noindex, follow`, no hreflang |
| 21 | `c "$HOST/ar/shop/"` | `200`, `<html dir="rtl" lang="ar">`, Arabic WooCommerce strings (language pack + Strings tab overrides) |

Delete-option helper for #15:

```bash
"$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php -r 'delete_option("rewrite_rules"); echo "rules deleted\n";'
```

## 3. E-mail matrix (M3 smoke list, §3.8)

| Scenario | Admin e-mail | Customer e-mail |
|---|---|---|
| Checkout on `/en/` | Persian, RTL, Persian item names | English, LTR, English item names, links under `/en/` |
| Resend invoice from wp-admin for that order | — | English |
| Deferred e-mails on (`woocommerce_defer_transactional_emails = yes`) | Persian | English |
| Checkout on `/ar/` | Persian | Arabic, RTL |
| Customer note from wp-admin on an `/ar/` order | — | Arabic |

## 4. Admin checks

- *Products → Edit*: "Translations" metabox with EN/AR tabs; slug greyed out on *Shop*, *Cart*, *Checkout*, *My account*; "Copy from Persian"; a colliding slug is saved with `-2` and a notice appears.
- *Products → Categories → Edit*: per-language name/slug/description/status; saving "published" without a name downgrades to draft.
- *Gadgetim → Languages*: public flags, digits, fallback mode, readiness checklist; flipping *public* purges the page cache.
- *Gadgetim → Strings*: WooCommerce labels listed with EN/AR inputs; collected missing Arabic strings (when the collector is on and an administrator browsed `/ar/`).
- *Gadgetim → Tools*: *Install language packs* (needs `install_languages`), *Rebuild slug map*, *Rebuild search index*, *Slug conflicts*.
- *WooCommerce → Orders*: "Language" column and meta box.
