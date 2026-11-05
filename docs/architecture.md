# Gadgetim storefront — technical architecture

Version 0.2 (2026-11-04) · Author: principal engineering pass · Status: revised after two critical reviews; ready for M0

---

## خلاصه فارسی

این سند معماری فنی فروشگاه جدید گجتیم است. نسخه ۰٫۲ همه ایرادهای «مسدودکننده» و «مهم» دو بازبینی را برطرف کرده و تغییرها در بخش ۹ (دفتر تصمیم‌ها) ثبت شده است. تصمیم‌های اصلی:

- **دو بسته کد، هر دو مال خودمان**: قالب کلاسیک «gadgetim» (فقط ظاهر) و افزونه «gadgetim-core» (داده و منطق: سه‌زبانگی، قیمت ارزی، تنظیمات، سئو، ریدایرکت، ایمپورت). ووکامرس موتور فروش است. هیچ افزونه بیرونی جز ووکامرس، یک افزونه کش و یک افزونه درگاه نصب نمی‌شود. نه المنتور، نه پولی‌لنگ، نه افزونه اسلایدر یا آیکون.
- **سه زبان بدون افزونه**: فارسی در ریشه سایت (آدرس‌های قدیمی حفظ می‌شود)، انگلیسی زیر `/en/` و عربی زیر `/ar/`. ترجمه‌ها متادیتای همان محصول/برگه هستند (یک محصول، یک موجودی، یک قیمت، سه متن). **تغییر مهم این نسخه**: ترجمه فقط در لحظه نمایش اعمال می‌شود (نه با دست‌کاری آبجکت‌های کش‌شده وردپرس) تا هیچ نوشتنی در دیتابیس نتواند متن فارسی را با ترجمه جایگزین کند؛ و محصولی که ترجمه منتشرشده ندارد در آرشیو، جست‌وجو و نقشه سایت `/en/` و `/ar/` دیده نمی‌شود، تا گوگل صفحه‌های مخلوط فارسی/انگلیسی نبیند. تا وقتی دسته‌های اصلی ترجمه نشده‌اند، `/en/` و `/ar/` برای موتورهای جست‌وجو بسته می‌ماند.
- **قیمت ارزی**: هر ارز (درهم، دلار، …) نرخ تومانی و گام رُندکردن خودش را دارد. قیمت تومانی محصول هنگام ذخیره و هنگام تغییر نرخ **واقعاً در فیلدهای ووکامرس نوشته می‌شود** (در سایت قدیمی قیمت ذخیره‌شده ۹۸ محصول از ۱۰۷ محصول با قیمت نمایشی فرق داشت). فیلدهای ارزی از REST هسته وردپرس پنهان‌اند تا هیچ راهی برای تغییر قیمت پایه بدون بازمحاسبه نماند؛ تغییر نرخ همیشه زنجیره‌ای و پس‌زمینه‌ای اجرا می‌شود و فقط یک بار در پایان کش پاک می‌شود.
- **اعداد فارسی و تاریخ جلالی فقط در قالب** (با توابع کمکی مشخص)، نه با فیلترهای سراسری؛ چون آن فیلترها نقشه سایت، JSON-LD، REST و پنل مدیریت را خراب می‌کردند.
- **زبان سفارش**: نام اقلام سفارش به فارسی ذخیره می‌شود و فقط برای مشتری ترجمه نمایش داده می‌شود؛ ایمیل‌های مشتری به زبان سفارش و ایمیل‌های مدیر همیشه فارسی، حتی در ارسال مجدد از پنل؛ آدرس بازگشت از درگاه هم به زبان سفارش ساخته می‌شود.
- **حالت «تماس برای قیمت»** از افزونه قدیمی به افزونه هسته منتقل می‌شود (پیش‌فرض خاموش، شماره تلفن مهاجرت می‌کند).
- **قالب**: سی‌اس‌اس و جاوااسکریپت دستی، بدون مرحله بیلد، یک فونت متغیر (وزیرمتن) برای فارسی/عربی تا بودجه سرعت نگه داشته شود، انیمیشن قوی اما فقط زیر خط دید اول و با احترام به prefers-reduced-motion. شمارنده سبد فقط وقتی کوکی سبد وجود دارد از سرور پرسیده می‌شود تا کش صفحه بی‌اثر نشود.
- **امنیت**: تنظیمات حساس (ریدایرکت، ابزارها، نرخ ارز، زبان‌ها) فقط برای مدیر کل؛ مقصد ریدایرکت‌ها فقط مسیر داخلی؛ هیچ نسخه پشتیبان دیتابیس داخل وب‌روت نوشته نمی‌شود.
- **هویت بصری جدید**: لوگوی دوزبانه با ایده «نقطه‌های شارژ»، نسخه روشن/تاریک، فاویکون از همان نقطه‌ها. حدود نیمی از عکس‌های محصول واترمارک قدیمی دارند؛ تصمیم کارفرما لازم است.
- **محیط لوکال به MariaDB** می‌رود تا مثل سرور باشد و انتقال دیتابیس با `wp search-replace` روی لوکال انجام شود، نه با جایگزینی متنی روی فایل دامپ.

پرسش‌هایی که به پاسخ کارفرما نیاز دارد در بخش ۸.۲ فهرست شده است.

---

## 0. What this document is based on (verified facts)

Everything below was read from the backup, the Local install, or the WooCommerce source now installed in Local (11.2.1), not assumed.

| Fact | Source |
|---|---|
| Old stack: WooCommerce **11.0.1**, HPOS enabled, currency `IRT`, 0 decimals, thousands `,`, symbol position `right_space`, timezone Asia/Tehran, default country `IR:FRS`, taxes off, guest checkout on, reviews on | `mysql/gadgetim_wp_database.sql` |
| Permalinks: `/%postname%/`; product base `/product`, category base `product-category`, tag base `product-tag`, brands at `/brand/` (`product_brand` = WooCommerce native brands taxonomy) | same |
| Old currency plugin: single option `gadgetim_currency_rate = 78000`; 107 products carry `_gadgetim_currency_regular_price` / `_gadgetim_currency_sale_price`; prices applied by runtime `woocommerce_product_get_*` filters only | plugin source + dump |
| **98 of 107** products have a stored `_regular_price` that is NOT `base × 78000` (e.g. product 4208: stored 1,125,000, computed 2,340,000). So `_price`, `wc_product_meta_lookup` and every price sort/filter were wrong on the old site | `products.csv` recomputation |
| Call-for-price: `cfp_options` = enabled 0, label "تماس بگیرید", phone `+98917…`, `show_price` 1, WhatsApp number set but disabled, Telegram empty | dump |
| Other old plugins: `site-custom-payment-gateways` (hides `cod` + `bacs`), `bitpayir-woocommerce2` v4.2.0 (classic + block checkout), `woocommerce-compare-list` (third-party), `zhaket-updater-off` (must not come over) | plugin folders |
| Catalog: 107 products, **all simple**, 103 with galleries, descriptions avg 12 kB HTML, SEO fields empty, no SKUs, no weights; `views` counter and `special` flag exist | `products.json` |
| Taxonomies: 61 categories (3 levels, 11 empty, slugs mix Latin and Persian: `smart-watch`, `گوشی-موبایل`), 30 brands with Latin/Persian duplicates, 30 tags of which 2 are used | `categories.json`, `brands.json`, `product-tags.json` |
| Pages: shop, cart, checkout, my-account, privacy, terms, contact, about, FAQ, order-tracking, blog, wishlist, compare, "home-2"; 6 blog posts; 2 menus, 56 items; 226 old URLs (raw Persian paths) | `pages-posts.json`, `menus.json`, `old-urls.txt` |
| Shipping: zone "ایران" with `local_pickup`; "rest of world" with `flat_rate`. 96 legacy `shop_order` posts plus HPOS table; 9 users | dump |
| Images: 408 files (395 WebP, 12 JPG, 1 PNG), 393 WebP are 800×800, 362 files < 50 kB, 14 MB total. 2026 images carry a **baked-in watermark**; 2022 images do not. Backgrounds mixed | `images/`, visual check |
| Brand asset: only a 512 px vivid-blue "GT" monogram favicon | `brand/` |
| Local: WordPress 7.1.4, PHP 8.2.29, **MySQL 8.4** (to be switched, see 6.2), Apache, `fa_IR` core language files, **WooCommerce 11.2.1 installed**, no WP-CLI, no PHP on PATH. Local's CLI PHP: `C:/Users/GHADER/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe -c C:/Users/GHADER/AppData/Roaming/Local/run/sqs5AIpIj/conf/php/php.ini` (gd with WebP, intl, mbstring, curl, zip; no imagick) | filesystem |
| WC 11.2.1 source facts used below: `WC_Data::save()` fires `woocommerce_before_{object_type}_object_save`; `WC_Product_Variation` does **not** override `object_type` (stays `product`), so **one hook `woocommerce_before_product_object_save` covers products and variations** (there is no `..._product_variation_object_save`). Variations save as `woocommerce_admin_process_variation_object` → `$variation->save()` → `woocommerce_save_product_variation`. `WC_Email::setup_locale()` switches locale **only for customer emails** and only via `woocommerce_allow_switching_email_locale` + `woocommerce_email_setup_locale`; `woocommerce_email_recipient_{id}` receives `$this->object`. Admin resends fire `woocommerce_before_resend_order_emails( $order, $email_type )`. `WC_AJAX::get_endpoint()` builds from `home_url( '/', 'relative' )`. `wc_apply_sale_state_for_product()` calls `$product->save()`. Fresh installs get **block** cart/checkout pages and `email_improvements` enabled. `woocommerce_rest_insert_{post_type}_object` exists for `product` and `product_variation`; `woocommerce_order_item_get_name` exists via `WC_Order_Item` (`object_type = 'order_item'`) | `includes/abstracts/abstract-wc-data.php`, `class-wc-product-variation.php`, `admin/meta-boxes/class-wc-meta-box-product-data.php:629-633`, `emails/class-wc-email.php:429-444,644`, `admin/meta-boxes/class-wc-meta-box-order-actions.php:141,163`, `class-wc-ajax.php:54`, `wc-product-functions.php:644-651`, `class-wc-install.php:1329,3166`, `rest-api/.../class-wc-rest-crud-controller.php:221,268` |
| Dev machine: Node 24.19, git, gh, shellcheck; repo has `.gitattributes` (`* text=auto eol=lf`), ignores `first-backup/` and `release/`, has `tools/client-report.js` | repo |

---

## 1. Decisions at a glance

| # | Decision | Alternatives rejected |
|---|---|---|
| D1 | Classic theme `gadgetim` + plugin `gadgetim-core`; theme = presentation only, plugin = data/behaviour. Theme guards every plugin call (`function_exists`) and declares `Requires Plugins: gadgetim-core` | Block (FSE) theme; one-package theme |
| D2 | Trilingual via URL prefix `/`, `/en/`, `/ar/` as a **virtual sub-directory** (`home_url` filter, enabled in two phases); translations stored as **post/term meta on the same object**; **translation applied only at output points, never by mutating cached core objects** | Separate posts per language; subdomains; `?lang=` for pages |
| D3 | Multi-currency base prices **materialised** into `_regular_price/_sale_price/_price` and the lookup table on save and on rate change; base fields reachable only through WC CRUD paths (never core REST meta); rate changes always run as a **chained** Action Scheduler job | Runtime filters (old plugin, 98/107 stale) |
| D4 | Classic (shortcode) cart/checkout/account templates; Store API for the cart drawer, called **only when a cart cookie exists** | Cart/Checkout blocks |
| D5 | Hand-written CSS and native ES modules; **no build step**; one-off Node scripts only for font subsetting and an image audit (LQIP is generated in PHP/GD) | Vite/webpack |
| D6 | Code reaches Local through **Windows directory junctions**, removed only via `[System.IO.Directory]::Delete` after a reparse-point check | robocopy (kept as fallback) |
| D7 | Production deployment = **release zips** from git tags, uploaded via WP admin/cPanel; DB moves once from Local via `wp search-replace` on a cloned DB; **no self-updater** | FTP of working tree; GitHub self-update |
| D8 | Floors: PHP **8.1**, WordPress **6.7**, WooCommerce **9.6**; Local database engine switched to **MariaDB 10.x** to match production | — |
| D9 | SEO, redirects, sitemaps, JSON-LD, Persian digits, Jalali dates, string translations: inside `gadgetim-core`, exposed as **explicit template helpers**, never as global `wp_date`/`number_format_i18n`/`wc_price` filters | Yoast/Rank Math, Redirection, WP-Parsidate |
| D10 | Untranslated content is **invisible** in `/en/` and `/ar/` (archives, search, related, sitemaps, hreflang), not merely noindexed on singular; prefixes stay `Disallow`ed until a launch gate is met | "show Persian fallback" as the default |
| D11 | Capability split: `manage_options` for Redirects, Strings, Tools, Languages, currency rates; `manage_woocommerce` for product-level fields and contact mode | single `manage_woocommerce` |
| D12 | Orders: `_gtm_lang` is the single source of truth for an order's language; line items store Persian names; customer-facing output translated at display time; admin output always Persian | storing translated line items |

---

## 2. Code organisation

### 2.1 Packages

```
gadgetim-ir/                                   (git root, GitHub)
├── wp-content/
│   ├── themes/gadgetim/                       theme (presentation)
│   └── plugins/gadgetim-core/                 plugin (data, behaviour, admin)
├── tools/
│   ├── link-local.ps1                         creates/refreshes the junctions into the Local site (safe removal, 2.4)
│   ├── sync-local.ps1                         robocopy fallback
│   ├── build-release.sh                       git-archive based zips -> release/
│   ├── lint.sh                                php -l on all PHP, shellcheck, optional phpcs
│   ├── wp.ps1                                 wrapper: Local php.exe + rendered php.ini + wp-cli.phar
│   ├── fonts/subset.mjs                       one-off font subsetting (Node)
│   ├── images/audit.mjs                       one-off: size/border audit of the 408 packshots (Node sharp) — audit only
│   ├── deploy/export-db.ps1                   clone Local DB -> wp search-replace -> MariaDB-compatible dump (6.2)
│   ├── deploy/check-redirects.sh              hits the 226 old URLs against a host, expects 200/301/410 as mapped
│   └── client-report.js                       (exists)
├── data/
│   ├── redirects.csv                          old path -> new path, status
│   ├── brand-merge.json                       SamSung + Samsung + ... -> one brand
│   └── category-map.json                      old category id -> new slug/parent (+ drops)
├── docs/
│   ├── architecture.md                        this document
│   ├── design-system.md                       tokens, components, motion rules
│   ├── sanitisation.md                        every _gtm_* key -> sanitiser (5.10)
│   └── runbooks/ (deploy.md, backup.md, rate-change.md, translate.md, i18n-tests.md)
├── tests/                                     PHPUnit (run with Local's php.exe), fixtures = real titles/URLs
├── first-backup/  (gitignored)   release/  (gitignored)
├── .gitattributes  .gitignore  .editorconfig  phpcs.xml.dist  phpunit.xml.dist  package.json (tools only)
```

Why `wp-content/...` inside the repo: the two packages sit at their real WordPress paths, so junctions, `git archive` and zips need no path rewriting.

### 2.2 Plugin `gadgetim-core` layout

```
gadgetim-core/
├── gadgetim-core.php          header, constants (GADGETIM_CORE_VERSION, GADGETIM_CORE_API = 1), autoloader, requirements check, boot
├── uninstall.php              removes options only when GADGETIM_REMOVE_DATA is defined
├── includes/
│   ├── Autoloader.php         spl_autoload_register: Gadgetim\Core\X\Y -> includes/X/Y.php
│   ├── Plugin.php             boots modules in a fixed order (I18n\Request first, at plugin-file load)
│   ├── Support/               Digits.php Jalali.php Cache.php Options.php Html.php Phone.php Normalize.php RateLimit.php
│   ├── I18n/                  Languages.php Request.php Urls.php Locale.php Context.php
│   │                          PostTranslations.php TermTranslations.php Menus.php Strings.php Slugs.php Visibility.php
│   │                          Search.php Sitemaps.php Hreflang.php WooCommerce.php Orders.php Emails.php Comments.php
│   │                          Admin/TranslationMetabox.php Admin/TermFields.php Admin/StringsPage.php
│   ├── Pricing/               Currencies.php Calculator.php Materializer.php ProductFields.php Guard.php
│   │                          Display.php Rest.php Migration.php Reprice.php Admin/CurrenciesPage.php
│   ├── ContactMode/           ContactMode.php (call-for-price)
│   ├── Catalog/               ProductExtras.php (subtitle, specs, FAQ, guarantee, views) Brands.php Lqip.php
│   ├── Settings/              Settings.php Schema.php Admin/SettingsPage.php Home.php Capabilities.php
│   ├── Seo/                   Meta.php JsonLd.php Robots.php Redirects.php Admin/RedirectsPage.php
│   ├── Checkout/              Gateways.php Fields.php ReturnUrls.php
│   ├── Rest/                  Search.php Currencies.php Views.php
│   ├── Import/                Importer.php Images.php Terms.php Cli.php
│   └── Cli/                   Commands.php (WP-CLI: import, reprice, redirects, slugs, translations)
├── assets/admin/              admin.css admin.js
├── languages/                 gadgetim-core.pot, -fa_IR.po/.mo/.l10n.php, -ar.po/.mo/.l10n.php
└── templates/                 admin views, email parts
```

Conventions: namespace `Gadgetim\Core`, PHP 8.1 syntax, options prefixed `gadgetim_`, hooks prefixed `gadgetim_`, meta keys prefixed `_gtm_`, text domain `gadgetim-core`. No Composer at runtime; Composer only for dev PHPCS/PHPUnit.

### 2.3 Theme `gadgetim` layout

```
gadgetim/
├── style.css                  header (Theme Name, Text Domain: gadgetim, Requires PHP 8.1, Requires Plugins: gadgetim-core)
├── functions.php              requires inc/*.php; if gadgetim-core is missing or GADGETIM_CORE_API != expected -> admin notice + safe stubs
├── inc/  setup.php assets.php template-tags.php woocommerce.php menus.php images.php seo-head.php compat.php
├── templates/                 header/*, footer/*, card/*, price/*, drawers/*, sections/*, product/*
├── index.php front-page.php page.php single.php archive.php search.php 404.php
├── woocommerce/               template overrides (see 5.2), copied from the installed WC 11.2.1 templates
├── assets/
│   ├── css/  tokens.css base.css app.css shop.css checkout.css account.css print.css
│   ├── js/   app.js  modules/{motion,drawer,cart,search,gallery,variations,price,filters,toast,i18n,switcher,checkout}.js
│   ├── fonts/ (subsetted woff2)   icons/sprite.svg   img/ (logo svg, og template, placeholders)
├── languages/                 gadgetim.pot, -fa_IR, -ar
└── screenshot.png
```

`inc/compat.php` defines fallbacks for every plugin helper the templates call (`gadgetim_price()`, `gadgetim_date()`, `gadgetim_t()`, `gadgetim_lang()`), so a plugin deactivated by a WooCommerce failure or uploaded in the wrong order degrades to Persian/Latin-digit output instead of a fatal.

### 2.4 Getting code into Local (D6)

`tools/link-local.ps1` (PowerShell 5.1, no admin rights). Removal never uses `Remove-Item` on the link: in 5.1 `Remove-Item` on a junction either fails ("directory is not empty") or, with `-Recurse`, follows the reparse point and deletes the **repo's** files.

```powershell
$site = "C:\Users\GHADER\Local Sites\gadgetim\app\public\wp-content"
$repo = "C:\Users\GHADER\Documents\ProjectGit\gadgetim-ir\wp-content"
foreach ($p in @("themes\gadgetim", "plugins\gadgetim-core")) {
  $target = Join-Path $site $p
  $source = Join-Path $repo $p
  if (Test-Path $target) {
    $item = Get-Item $target -Force
    $isLink = ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0
    if (-not $isLink) { throw "$target exists and is a real directory. Move it away by hand; refusing to delete." }
    [System.IO.Directory]::Delete($target)     # removes only the link, never the target's contents
  }
  New-Item -ItemType Junction -Path $target -Target $source | Out-Null
}
```

Junctions are transparent to PHP and Apache. Fallback: `tools/sync-local.ps1` with `robocopy /MIR` from repo to site (one direction only). Never edit inside `Local Sites`.

### 2.5 Reaching production (D7)

- Tag `vX.Y.Z` → `tools/build-release.sh` runs `git archive` per package → `release/gadgetim-theme-X.Y.Z.zip`, `release/gadgetim-core-X.Y.Z.zip` (LF guaranteed; dev files `export-ignore`).
- Upload order: **plugin first, then theme** (the theme's compat layer tolerates the reverse, but the runbook fixes the order). Through *Plugins/Themes → Add → Upload* ("Replace current with uploaded"), or cPanel unzip.
- **No self-updater.** GitHub is throttled or blocked for many Iranian server IPs, and an update channel is a remote-code path; manual zip upload with a SHA-256 printed by `build-release.sh` is the only path. Revisit only if the client asks.
- Versions: theme `style.css` Version, `GADGETIM_CORE_VERSION`, `CHANGELOG.md`; asset cache-busting uses the package version.

### 2.6 Standards, lint, tests, no build

- PHP: WordPress Coding Standards via `phpcs.xml.dist`; `tools/lint.sh` falls back to `php -l`. Escape late, sanitize early, nonces + capability checks on every admin action, prepared SQL only.
- PHPUnit (via Local's php.exe, WordPress test library in `tests/`): required tests are named in this document (3.3, 3.4, 4.3, 4.4, 6 Redirects, Support\Normalize). They are the regression net for the issues the review found.
- JS: ES2020 modules, no transpile; optional `npx eslint`.
- CSS: `@layer tokens, base, layout, components, shop, pages, utilities`; two site stylesheets + a ≤ 12 kB inline critical block. Server gzip/brotli replaces minification.
- Only two one-off scripts need Node (font subsetting, image audit). Production never runs Node.
- WP-CLI: `tools/wp.ps1` downloads `wp-cli.phar` into `tools/wp-cli/` (gitignored) and runs it with Local's php.exe + rendered php.ini. Used for `wp i18n make-pot/make-php`, `wp search-replace` (6.2), and `wp gadgetim …`. Every command also has a plain-PHP entry point (`includes/Import/Cli.php` bootstraps `wp-load.php`).

---

## 3. Trilingual storefront without Polylang/WPML

### 3.1 Languages and URL scheme

```php
// option gadgetim_languages (seeded on activation, editable in Settings -> Languages, manage_options)
[
  'fa' => [ 'locale' => 'fa_IR', 'dir' => 'rtl', 'prefix' => '',   'hreflang' => 'fa-IR', 'digits' => 'fa',   'public' => true  ],
  'en' => [ 'locale' => 'en_US', 'dir' => 'ltr', 'prefix' => 'en', 'hreflang' => 'en',    'digits' => 'latn', 'public' => false ],
  'ar' => [ 'locale' => 'ar',    'dir' => 'rtl', 'prefix' => 'ar', 'hreflang' => 'ar',    'digits' => 'arab', 'public' => false ],
]
```

- Persian stays at the root so all 226 old URLs keep working. English and Arabic live under `/en/…` and `/ar/…` with the same bases (`/en/product/…`). WooCommerce page slugs (shop, cart, checkout, my-account, terms) and account endpoint slugs are **language-independent** (3.4).
- `public => false` is the launch gate (3.5): the prefix resolves (so translators can review), but it is `Disallow`ed in `robots.txt`, `noindex`, absent from the sitemap index and from hreflang sets until an admin flips it on. Flip condition (runbook): every top-level category has at least one published translation and the shop/cart/checkout/account pages are translated.
- Assets, `/wp-json/`, `/wp-admin/`, `/wp-login.php`, `/wp-cron.php`, `/xmlrpc.php`, `/wp-comments-post.php` are never prefixed.
- `?lang=xx` / `X-Gadgetim-Lang` are accepted **only for GET/HEAD requests to `/wp-json/wc/store/…` and `/wp-json/gadgetim/v1/…`** and for `?wc-ajax=` requests under a prefixed URL. They never switch a normal page, and never apply to `wc/v3`, `wp/v2` or any write request (see 3.3 for why).

### 3.2 Early request parsing, two-phase prefixing, locale

Detection runs when the plugin file loads. REST/AJAX are detected **by path and query**, because `REST_REQUEST`/`WC_DOING_AJAX` are not yet defined at that point:

```php
final class Request {
    public static function detect(): string {
        if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) return 'fa';
        if ( is_admin() && ! wp_doing_ajax() ) return 'fa';            // context only; admin locale is NOT filtered (user_locale wins)
        $path = self::path_without_home();                             // strips the install sub-directory, if any
        if ( preg_match( '#^/(en|ar)(?:/|$)#', $path, $m ) ) return $m[1];
        if ( self::is_readonly_api() ) {                               // GET/HEAD to /wp-json/wc/store/* or /wp-json/gadgetim/v1/*, or ?rest_route= to those
            $q = $_GET['lang'] ?? $_SERVER['HTTP_X_GADGETIM_LANG'] ?? '';
            if ( Languages::exists( $q ) ) return $q;
        }
        if ( isset( $_GET['wc-ajax'] ) || wp_doing_ajax() ) {           // storefront AJAX: language from the referer prefix
            if ( preg_match( '#://[^/]+/(en|ar)(?:/|$)#', $_SERVER['HTTP_REFERER'] ?? '', $m ) ) return $m[1];
        }
        return 'fa';
    }
    private static function is_readonly_api(): bool {
        $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ( $m !== 'GET' && $m !== 'HEAD' ) return false;
        $p = self::path_without_home();
        $rest = '/' . rest_get_url_prefix() . '/';
        $route = str_starts_with( $p, $rest ) ? substr( $p, strlen( $rest ) - 1 ) : ( $_GET['rest_route'] ?? '' );
        return str_starts_with( $route, '/wc/store/' ) || str_starts_with( $route, '/gadgetim/v1/' );
    }
}
```

**Two-phase prefixing.** The language is known from load, but the `home_url` filter does not prefix until `WP::parse_request()` is about to run. Reason: `WP_Rewrite::rewrite_rules()` regenerates and persists `rewrite_rules` lazily (WooCommerce sets `woocommerce_queue_flush_rewrite_rules` on every upgrade; core deletes the option on permalink save), and it emits the `robots\.txt$`, `favicon\.ico$` and sitemap-stylesheet rules only when the `home_url()` path is empty. If the first request after a flush were `/en/shop/` with a prefixed `home_url()`, the persisted rules would lack those entries for every language.

```php
add_filter( 'do_parse_request', function ( $do, WP $wp ) {
    global $wp_rewrite;
    $wp_rewrite->wp_rewrite_rules();      // lazy regeneration, if due, happens here with the UNPREFIXED home
    Urls::enable_prefix();                // from now on home_url() carries /en or /ar for the detected language
    return $do;
}, 1, 2 );
```

Every explicit `flush_rewrite_rules()` the plugin triggers runs inside `Languages::with_lang( 'fa', … )`. `WP::parse_request()` then computes `$home_path` from the prefixed `home_url()` and strips it before matching — the same mechanism WordPress uses for sub-directory installs — so every generated link (`get_permalink`, `get_term_link`, `wc_get_page_permalink`, `wc_get_endpoint_url`, pagination, `?wc-ajax=`) carries the prefix, `redirect_canonical()` compares like with like, and core sitemaps resolve at `/en/wp-sitemap.xml`.

```php
add_filter( 'home_url', function ( string $url, string $path, $scheme ) {
    static $home = null, $pattern = null;
    if ( ! Urls::prefix_enabled() ) return $url;
    $lang = Languages::current();
    if ( $lang === 'fa' || in_array( $scheme, [ 'rest', 'json' ], true ) ) return $url;
    $p = ltrim( $path, '/' );
    if ( $p !== '' && preg_match( '#^(wp-json|wp-admin|wp-login\.php|wp-comments-post\.php|wp-content|wp-includes|xmlrpc\.php|wp-cron\.php)#', $p ) ) return $url;
    if ( $scheme === 'relative' || ( $url !== '' && $url[0] === '/' ) ) {           // WC_AJAX::get_endpoint() uses home_url('/', 'relative')
        return str_starts_with( $url, "/$lang/" ) || $url === "/$lang" ? $url : "/$lang" . $url;
    }
    $home    ??= untrailingslashit( get_option( 'home' ) );
    $pattern ??= '#^' . preg_quote( preg_replace( '#^https?:#', '', $home ), '#' ) . '#'; // scheme-agnostic, built once per request
    $bare = preg_replace( '#^https?:#', '', $url );
    if ( str_starts_with( $bare, preg_replace( '#^https?:#', '', $home ) . "/$lang/" ) || $bare === preg_replace( '#^https?:#', '', $home ) . "/$lang" ) return $url;
    $scheme_prefix = str_starts_with( $url, 'https:' ) ? 'https:' : 'http:';
    return $scheme_prefix . preg_replace( $pattern, preg_replace( '#^https?:#', '', $home ) . "/$lang", $bare, 1 );
}, 10, 3 );
```

**Locale.** On the storefront (and for the read-only API/AJAX cases above) the plugin filters `locale` and `determine_locale` to the language's locale from load, so `load_default_textdomain()` and every just-in-time textdomain load pick `fa_IR`, `en_US` or `ar`. **In wp-admin the filters bail out** (`is_admin() && ! wp_doing_ajax()`), so `WPLANG = fa_IR` and per-user `user_locale` behave normally and an English-speaking translator can have an English dashboard. The only admin-side locale override is the email context (3.8). `language_attributes` emits `lang="en" dir="ltr"` etc.

**Context vs locale switch.** `Languages::with_lang( $lang, callable )` changes only the *language context* (URL prefix, slug maps, translation-meta lookups) — cheap, no gettext reload. `Languages::with_locale( $lang, callable )` additionally wraps `switch_to_locale()`/`restore_previous_locale()` and is used **only** for email rendering. Switcher links, hreflang, cache-purge URL building and gateway return URLs use `with_lang`. WordPress 6.5+ `.l10n.php` files are shipped for our textdomains so the one place that does switch is as cheap as it can be.

Language packs: `ar` must be installed for core and WooCommerce. *Settings → Tools* (manage_options) gets an "Install language packs" button: `wp_download_language_pack( 'ar' )` for core, then `Language_Pack_Upgrader::bulk_upgrade( wp_get_translation_updates() )` for WooCommerce. The WooCommerce `ar` pack is incomplete; missing strings are listed on the Strings page (3.3) and supplied through `gettext`/`gettext_with_context` overrides scoped to the `woocommerce` domain when `Languages::current() === 'ar'`.

**Day-one test matrix** (`docs/runbooks/i18n-tests.md`): `/en/`, `/en/shop/`, `/en/product/x/`, `/en/product-category/a/b/page/2/`, `/en/my-account/orders/`, `/en/?s=x&post_type=product`, `/en/?wc-ajax=get_refreshed_fragments`, `/en/?wc-ajax=checkout` (with the real WC), `/en/wp-sitemap.xml`, `/en` → 301 `/en/`, `/enzo-brand/` stays Persian, `/en/checkout/order-received/123/?key=…`, `redirect_canonical` on `/en/product/x` without slash, **and**: `delete_option('rewrite_rules')` then first request `/en/shop/` then `/robots.txt`, `/favicon.ico`, `/wp-sitemap.xml` must all still work; `GET /wp-json/wc/store/v1/cart?lang=en` returns English; `PUT /wp-json/wc/v3/products/categories/5?lang=en` is treated as Persian.

### 3.3 What is translatable and where it lives

| Content | Persian (primary) | en / ar | Edited in |
|---|---|---|---|
| Product & page title | `post_title` | `_gtm_title_{lang}` | translation metabox |
| Slug | `post_name` | `_gtm_slug_{lang}` (auto from title, editable, validated for uniqueness; **disabled for WooCommerce pages**) | metabox |
| Short / long description | `post_excerpt` / `post_content` | `_gtm_excerpt_{lang}` / `_gtm_content_{lang}` (wp_editor) | metabox |
| Subtitle | `_gtm_subtitle` | `_gtm_subtitle_{lang}` | metabox |
| Spec table | `_gtm_specs` (JSON `[["label","value"],…]`) | `_gtm_specs_{lang}` ("copy rows from Persian") | metabox repeater |
| FAQ | `_gtm_faq` (JSON `[{"q","a"}]`) | `_gtm_faq_{lang}` | metabox repeater |
| Guarantee text override | `_gtm_guarantee` | `_gtm_guarantee_{lang}` | metabox |
| SEO title / description | `_gtm_seo_title`, `_gtm_seo_desc` | `_gtm_seo_title_{lang}`, `_gtm_seo_desc_{lang}` | metabox |
| Translation status | — | `_gtm_status_{lang}` = `published` / `draft` / absent — **the visibility gate** (3.5) | metabox |
| Term name / slug / description | term fields | term meta `_gtm_name_{lang}`, `_gtm_slug_{lang}`, `_gtm_desc_{lang}`, `_gtm_status_{lang}` | term edit screen |
| Global attribute labels & terms | WC attribute | term meta as above; label via `woocommerce_attribute_label` | strings page |
| Menus | locations `primary_fa`, `footer_fa`, `legal_fa` | `primary_en`, `primary_ar` … | Appearance → Menus |
| Site title, tagline, trust badges, footer, contact block, home sections, email footer | `gadgetim_settings[...]['fa']` | `[...]['en']`, `[...]['ar']` | settings page |
| WooCommerce-owned admin strings (gateway title/description, shipping labels, account menu) | WC settings | `gadgetim_strings[ md5(source) ][lang]` via `woocommerce_gateway_title`, `woocommerce_gateway_description`, `woocommerce_shipping_rate_label`, `woocommerce_account_menu_items` | Strings page |
| Missing `woocommerce` ar-pack strings | — | `gadgetim_strings` applied via `gettext` for domain `woocommerce` when lang = ar | Strings page |
| Theme/plugin UI strings | English source | `.po/.mo/.l10n.php` | Poedit |
| WordPress + WooCommerce core strings | language packs | — | WP updates |

Why meta on the same object (D2): one product = one stock, one price, one set of reviews, one order line; the translation is only text.

**Translation is applied at output points only.** The previous draft mutated `name`/`description` inside `get_term`/`get_terms`. That is unsafe: `wp_update_term()` starts from the filtered `get_term()` and merges the caller's args, so any term write during a non-Persian context (a `wc/v3` category PUT with `?lang=en`, or WooCommerce's own `wp_update_term` calls) would persist the English name as the Persian one. Same risk class for `the_title`-style filters feeding back into saves. Rules:

- No filter on `get_term`, `get_terms`, `get_post`, or any WC `woocommerce_product_get_*` getter in `edit` context.
- Posts: `the_title` (view only, bails when `is_admin()`), `the_content` and `get_the_excerpt` at priority 1 (front end only), `woocommerce_product_get_name/description/short_description` **only when `$context`-less view calls originate on the storefront** — implemented by checking `Context::is_storefront_render()` (true between `template_redirect` and `shutdown` on non-admin, non-REST, non-CLI requests; also true inside email rendering with a customer-language context), `woocommerce_page_title`, `single_post_title`, `document_title_parts`.
- Terms: translated through `single_term_title`, `get_the_archive_title`, `get_the_archive_description`, `wp_setup_nav_menu_item` (menu labels), `woocommerce_get_breadcrumb`, `term_links-{taxonomy}` (for `get_the_term_list`), `woocommerce_layered_nav_term_html`, and a theme helper `gadgetim_term_name( WP_Term $t ): string` used in all theme-owned markup (cards, mega-menu, filters, brand strip). Term objects are never altered; `slug` and `name` in `WP_Term` always stay Persian.
- Hard guard: `Context::$suspend_translations` is set around every write the plugin performs (importer, slug map rebuild, reprice) and automatically when `is_admin()`, WP-CLI, REST with a non-GET method, cron. All output filters return the source untouched while it is set.
- **Test** (`tests/I18n/TermIntegrityTest.php`): update a `product_cat` description with language context `en`; assert `wp_terms.name` unchanged. Same for a product save under `en` asserting `post_title` unchanged.

Reading translations never queries: `PostTranslations::get( $id, 'title', $lang )` reads from the post-meta cache WP_Query already primed; term meta is primed with `update_termmeta_cache()` on the archive's term list.

### 3.4 Translated slugs

Storage is meta (`_gtm_slug_{lang}` on posts and terms). Resolution uses an in-memory map per language (`wp_cache` + transient `gadgetim_slugmap_{lang}`, rebuilt on save/delete): `[ 'post:product' => [ 'xiaomi-33w-charger' => 4208 ], 'post:page' => [...], 'term:product_cat' => [ 'chargers' => 5341 ], … ]`. At 107 products this is a few kB; past ~5 000 items it moves to a small table with a unique index.

**Uniqueness.** On save, `Slugs::validate( $slug, $lang, $type, $object_id )` checks the candidate against (a) every `_gtm_slug_*` in the same post type/taxonomy for any language, (b) every native `post_name`/term `slug` of the same type (old category slugs already include Latin ones such as `smart-watch`), (c) WooCommerce page slugs and account endpoint slugs. Collisions get `-2`, `-3` like `wp_unique_post_slug()` and an admin notice. Previous translated slugs are kept in `_gtm_slug_{lang}_prev` (array) and 301 to the current one, because `wp_old_slug_redirect` knows nothing about meta slugs. Tools has a "slug conflicts" report.

**Precedence.** Inbound resolution prefers the **native** slug when the segment matches a native slug of the same type; the translated map is consulted only when no native object owns that slug. With the uniqueness rule above this case cannot arise for new data, but legacy data and manual DB edits are covered.

**WooCommerce pages are not slug-translatable.** The shop archive is matched by the `shop/?$ => post_type=product` rule WooCommerce registers from the shop page's native `post_name`; a translated shop slug would route to a plain page and `is_shop()` would be false. The metabox greys out the slug field for the pages registered in `woocommerce_*_page_id` options, `/en/shop/`, `/en/cart/`, `/en/checkout/`, `/en/my-account/` are the canonical English URLs, and the `page_link` filter leaves them alone. The same applies to `wc-api`, `order-received`, `lost-password` and all account endpoints.

Inbound (`/en/product/xiaomi-33w-charger/`):

```php
add_filter( 'request', function ( array $qv ) {
    $lang = Languages::current();
    if ( $lang === 'fa' ) return $qv;
    foreach ( [ 'product' => 'product', 'name' => null, 'pagename' => 'page' ] as $key => $type ) {
        if ( ! empty( $qv[ $key ] ) ) $qv[ $key ] = Slugs::native_post_path( $qv[ $key ], $lang, $type );   // native-first, then map
    }
    foreach ( [ 'product_cat', 'product_tag', 'product_brand', 'category_name', 'tag' ] as $tax ) {
        if ( ! empty( $qv[ $tax ] ) ) $qv[ $tax ] = Slugs::native_term_path( $qv[ $tax ], $lang, $tax );
    }
    return $qv;
} );
```

Unknown segments fall through unchanged, so a Persian slug under `/en/` still resolves; `Seo\Robots` then marks it canonical to the translated URL and the plugin 301s to the translated slug when one exists (and the translation is published).

Outbound: `post_type_link`, `page_link` (non-WC pages), `post_link` replace `/{post_name}/` with the translated slug; `term_link` rebuilds the hierarchical path with each ancestor's translated slug. Slugs are stored `sanitize_title()`-ed.

**Test** (`tests/I18n/SlugsTest.php`): translated slug equal to another object's native slug is rejected; `/en/shop/` resolves with `is_shop()` true; previous slug 301s.

### 3.5 Visibility gate, fallbacks, hreflang, canonical, switcher

A translation "exists" when `_gtm_status_{lang} === 'published'`. **Existence gates visibility everywhere in that language, not only on the singular page** — otherwise `/en/product-category/chargers/` would be an English-chrome page listing 107 Persian titles with hreflang claiming it is the English equivalent.

- `I18n\Visibility` adds, when `lang ≠ fa` and the query is for `product` (main query, shop, category/brand/tag archives, search, related/upsell/cross-sell loops via `woocommerce_related_products`, `woocommerce_product_get_upsell_ids`/`cross_sell_ids`, the home sections' queries, `Rest\Search`): `meta_query [ key = _gtm_status_{lang}, value = published ]`. One indexed meta JOIN on 107 rows is cheap; the slug-map transient also carries the published-id list so most loops can use `post__in` instead.
- Terms: an archive whose filtered query returns zero products is `noindex,follow`, excluded from sitemaps and hreflang, and shows a translated "nothing here yet in English" state with a link to the Persian page. Category/brand tiles in the mega-menu and home sections are filtered by `_gtm_status_{lang}` on the term as well.
- Singular untranslated item under a prefix: setting `gadgetim_settings['i18n']['fallback']`: `404` (default while `public = false`) or `show` (Persian content with `lang="fa" dir="rtl"` on the content blocks, a note, `noindex,follow`, no hreflang, not in sitemap). With `show`, the page is still unreachable from any `/en/` listing because of the gate; only direct links reach it.
- Launch gate (`public` flag, 3.1): while false, `robots.txt` has `Disallow: /en/` (resp. `/ar/`), every page under the prefix is `noindex`, the sitemap index omits it, hreflang omits it, the switcher hides it for anonymous visitors (translators see it when logged in).
- `Hreflang::links()` on every page: one `<link rel="alternate" hreflang=…>` per public language where the equivalent exists and is published, plus `x-default` → Persian. Equivalent URL = `Languages::with_lang( $lang, fn() => current_object_url() )`.
- Canonical: `Seo\Meta` outputs canonical for every page type and removes core `rel_canonical`; search, cart, checkout, account, `?orderby=`/filter variants are `noindex` with canonical to the clean URL.
- Switcher: server-rendered links (same HTML for every visitor → cacheable). **No language cookie and no redirect.** A client-side hint ("View in English?") appears once when `navigator.language` disagrees, remembered in `localStorage`, only for public languages.

### 3.6 Search across languages

On every product save the plugin writes `_gtm_search_{lang}` = normalised blob (title + subtitle + brand + category names + spec values) and filters `posts_search`/`posts_join` so that in `en`/`ar` the query matches the blob of that language (plus the visibility gate), and in `fa` the native title plus the blob. Normalisation (`Support\Normalize::text`): Arabic ي/ك → Persian ی/ک, ة → ه, strip tashkeel and ZWNJ variants, NFC via `Normalizer` (intl available), all digit sets → ASCII, lowercase Latin.

Instant search `GET /wp-json/gadgetim/v1/search?q=…&lang=…` (public, GET only):

- `q` capped at 64 characters after normalisation; fewer than 2 characters → empty result.
- **No transient per query** (a bot cycling random `q` would grow `wp_options` without bound on a host with no object cache). 107 products: a `LIKE` on the indexed-enough `_gtm_search_*` meta via `WP_Query` with `fields => ids`, `posts_per_page => 8`, `no_found_rows`, is cheaper than an option write. Results use `wp_cache` only (non-persistent, request-scoped).
- Response headers `Cache-Control: public, max-age=300` so the page-cache plugin or an edge can serve repeats; `Support\RateLimit` token bucket per hashed IP (30 requests/min) stored in one transient keyed by hour bucket, 429 on overflow.
- Returns id/title/subtitle/url/thumb/price-html (price HTML produced by `Pricing\Display::html()` for the requested language, 4.5).

**Test**: `tests/Support/NormalizeTest.php` with the real 107 titles as fixtures (Arabic-letter variants must match Persian spellings).

### 3.7 Sitemaps

Core sitemaps stay on. `/wp-sitemap.xml`, `/en/wp-sitemap.xml`, `/ar/wp-sitemap.xml` are the three indexes (only public languages are listed in `robots.txt` and cross-linked). `wp_sitemaps_posts_query_args` and `wp_sitemaps_taxonomies_query_args` add the `_gtm_status_{lang} = published` clause when `lang ≠ fa`; `wp_sitemaps_post_types` adds `product`; cart/checkout/account pages are excluded in all languages. `lastmod` is untouched (no date filters exist, D9).

### 3.8 WooCommerce specifics: orders, emails, gateway round trip

- Shop/cart/checkout/account pages are the same pages in every language (`/en/shop/`, `/en/cart/` …); titles translated via page meta; slugs and endpoint slugs language-independent (3.4).
- **Order language.** `woocommerce_checkout_create_order` and `woocommerce_store_api_checkout_update_order_meta` store `_gtm_lang` on the order. It is the single source of truth for everything below. Admin order screen shows it (column + meta box).
- **Line items stay Persian.** `woocommerce_checkout_create_order_line_item` (and the Store API path, same hook family) sets the item name from `$product->get_name( 'edit' )`. Reports, CSV exports, packing slips, admin screens and admin emails therefore read Persian regardless of where the shopper checked out. Customer-facing output is translated at display time: `woocommerce_order_item_name` (templates, emails) and `woocommerce_order_item_get_name` (view context, storefront/customer-email context only) look up `_gtm_title_{order lang}` via the item's product id. Variation attribute summaries are translated the same way through `woocommerce_order_item_display_meta_value`.
- **Emails.** Facts from `class-wc-email.php`: `setup_locale()` switches only when `is_customer_email()` and `woocommerce_allow_switching_email_locale`/`woocommerce_email_setup_locale` allow it, and it runs before `$this->object` is set; `trigger()` is called directly by admin resends (`woocommerce_before_resend_order_emails`) and by deferred sends inside Action Scheduler. Therefore context is set **from the order object**, never from the action name:
  - `woocommerce_email_recipient_{email_id}` for every email id (receives `$recipient, $object, $email`): if `$object` is an order, `Emails::enter( $order->get_meta('_gtm_lang') ?: 'fa', $email )`. This runs before subject, heading and content are built.
  - `Emails::enter()`: for **customer emails** → `switch_to_locale( locale of order lang )` and set the language context (so `with_lang`-based links such as "View order" point at `/en/my-account/…`, and `woocommerce_order_item_name` translates); for **admin emails** (`! $email->is_customer_email()`) → `switch_to_locale( 'fa_IR' )` and Persian context, regardless of the request locale (a checkout on `/en/?wc-ajax=checkout` runs the whole request in `en_US`, so without this the admin would receive an English LTR "New order").
  - `woocommerce_allow_switching_email_locale` → `false` (we switch ourselves and know the order; WooCommerce's own switch would only reload `fa_IR`), and `woocommerce_email_restore_locale`/`woocommerce_allow_restoring_email_locale` → our `Emails::leave()` restores the previous locale and context.
  - `woocommerce_before_resend_order_emails( $order, $type )` additionally pre-sets the context so plugins hooking before the recipient filter already see the right language.
  - Customer notes (`woocommerce_new_customer_note_notification`) go through the same recipient filter.
  - Email templates (`emails/email-header.php`, `email-styles.php`) read direction and per-language footer text from `Languages::current()`; Jalali dates only via `gadgetim_date()` in the templates (never a global filter). Emails are rendered with `email_improvements` **disabled** (set in M0) so the classic template overrides apply.
  - **M3 smoke list**: checkout on `/en/` → admin email fa + RTL, customer email en + LTR; resend invoice from wp-admin for that order → en; deferred emails on (`woocommerce_defer_transactional_emails` = yes) → same results; `/ar/` → customer ar + RTL.
- **Gateway round trip.** `get_checkout_order_received_url()`, `wc_get_checkout_url()`, `get_cancel_order_url()` are `home_url()`-based, and Iranian gateways build the return URL inside their callback handler (`/wc-api/…`, `?wc-api=`, `admin-ajax.php`) where detection yields `fa`. `Checkout\ReturnUrls` filters `woocommerce_get_checkout_order_received_url`, `woocommerce_get_cancel_order_url`, `woocommerce_get_cancel_order_url_raw`, `woocommerce_get_checkout_url` (when an order is resolvable from the args or `$_GET['key']`) to rebuild the URL inside `Languages::with_lang( $order_lang )`. In callback requests the language context is set as soon as the order is known: `woocommerce_api_{gateway}` (priority 1, resolves the order from the gateway's known query/post keys — configured per gateway in settings), `woocommerce_pre_payment_complete`, `woocommerce_order_status_changed`. Emails sent from that request already use the order-based context above. Tested with the gateway sandbox in `en` and `ar` in M3.
- **AJAX**: `?wc-ajax=` runs at `/en/?wc-ajax=…` (the `relative` branch of the `home_url` filter) so notices and fragment HTML return translated. `wc-cart-fragments` is dequeued; the drawer uses the Store API (5.6).
- **Store API**: `Request::detect()` honours `?lang=`/header for GET only; the cart drawer's POSTs (`add-item`, `update-item`) return the cart, so the drawer re-fetches with GET `?lang=` after a write (one extra small request, only during interaction).
- Country/state names, "Toman" label, validation messages come from language packs; `woocommerce_currency_symbol` returns `تومان` for fa/ar and `Toman` for en.
- **Reviews**: the comment form posts to `/wp-comments-post.php` (never prefixed). A hidden `gtm_lang` field is added via `comment_form_default_fields`; `comment_post_redirect` rebuilds the redirect inside `with_lang( $lang )`; `preprocess_comment` stores `_gtm_lang` as comment meta for moderation context.

### 3.9 Caching interaction

Prefix URLs are distinct cache keys, so any page cache works without "vary by cookie". Rules: no language cookie, no server-side redirect based on `Accept-Language`, no per-visitor markup in cached HTML, REST and `wc-ajax` responses carry `Cache-Control: no-store` **except** `gadgetim/v1/search` and `gadgetim/v1/currencies` (`public, max-age=300`). WooCommerce's own cookies make cache plugins bypass for shoppers with a cart — standard. The header cart count is **not** fetched for visitors without a cart cookie (5.4), so a cached page costs the server nothing.

### 3.10 RTL/LTR CSS strategy

One stylesheet for all directions: logical properties everywhere. Truly directional things use `[dir="rtl"]`: chevrons (`scale: -1 1`), marquee direction, carousel maths (JS reads `document.dir`). Fonts switch with `:lang()`. Mixed-script runs wrapped in `<bdi>` or `unicode-bidi: isolate`. No `rtl.css`, no `rtlcss` build.

---

## 4. Multi-currency base pricing

### 4.1 Data model

```php
// option gadgetim_currencies (autoload yes, small)
[
  'AED' => [ 'code' => 'AED', 'symbol' => 'AED', 'label' => [ 'fa' => '...', 'en' => 'UAE Dirham', 'ar' => '...' ],
             'rate' => 78000, 'rounding_step' => 1000, 'rounding_mode' => 'nearest', 'decimals' => 2,
             'enabled' => true, 'updated_at' => 1762252800, 'updated_by' => 2 ],
  'USD' => [ ... ],
]
// option gadgetim_rate_history (autoload no): last 500 changes [code, old, new, user, time, products_affected]

// product / variation meta (sanitisers in 5.10)
_gtm_price_currency      'AED' | ''   ('' = fixed Toman price; WooCommerce fields authoritative)
_gtm_base_regular_price  '30'         (decimal string, as entered)
_gtm_base_sale_price     '25' | ''
_gtm_rate_used           '78000'
_gtm_priced_at           '1762252800'
```

Toman stays WooCommerce's store currency (`IRT`, 0 decimals). WooCommerce never "sees" AED.

### 4.2 Computation and rounding

```php
final class Calculator {
    public static function to_toman( string $base, array $currency ): int {
        $raw  = (float) wc_format_decimal( $base ) * (float) $currency['rate'];
        $step = max( 1, (int) $currency['rounding_step'] );
        $q    = $raw / $step;
        $q    = match ( $currency['rounding_mode'] ) { 'up' => ceil( $q ), 'down' => floor( $q ), default => round( $q ) };
        return (int) ( $q * $step );
    }
}
```

Default step 1 000 Toman, mode `nearest`; live preview in admin. Regular and sale are converted independently. Sale ≥ regular rejected on save.

### 4.3 Materialisation (D3)

| | Runtime filters (old plugin) | Materialise into WooCommerce fields |
|---|---|---|
| `_price`, `wc_product_meta_lookup` | wrong unless every query is filtered (98/107 stale) | correct by construction |
| REST / Store API / structured data / analytics / coupons | each needs its own filter | correct |
| Rate change | instant, but recomputes on every render | chained background batch (seconds for 107) |
| Risk | hidden inconsistencies everywhere | one hook + one guard to get right |

**Hook order (verified in WC 11.2.1).** Variations are saved as `woocommerce_admin_process_variation_object` → `$variation->save()` → `woocommerce_save_product_variation`, and `WC_Product_Variation` keeps `object_type = 'product'`, so `woocommerce_before_product_object_save` fires for simple products, variable parents **and** variations. Our meta is therefore written on the `process_*_object` hooks (before the save), and the materialiser runs in the single `before_product_object_save` hook:

```php
// 1. Admin fields -> meta, BEFORE save (so the materialiser sees final values in the same save):
add_action( 'woocommerce_admin_process_product_object',   [ ProductFields::class, 'read_post_fields' ], 10, 1 );      // simple/variable parent
add_action( 'woocommerce_admin_process_variation_object', [ ProductFields::class, 'read_variation_fields' ], 10, 2 ); // each variation
// woocommerce_save_product_variation is used for nothing but UI messages.

// 2. Materialise on every save by anyone (admin, importer, REST wc/v3, quick edit, reprice, wc_scheduled_sales):
add_action( 'woocommerce_before_product_object_save', [ Materializer::class, 'apply' ], 10, 2 );

public static function apply( WC_Product $product, $data_store ): void {
    $code = (string) $product->get_meta( '_gtm_price_currency', true );
    if ( $code === '' ) return;
    $cur = Currencies::get( $code );
    if ( ! $cur || ! $cur['enabled'] ) { Admin\Notices::flag_product( $product->get_id(), "currency $code disabled" ); return; } // keep last prices
    $reg  = (string) $product->get_meta( '_gtm_base_regular_price', true );
    $sale = (string) $product->get_meta( '_gtm_base_sale_price', true );
    if ( $reg === '' ) return;
    $product->set_regular_price( (string) Calculator::to_toman( $reg, $cur ) );
    $product->set_sale_price( $sale === '' ? '' : (string) Calculator::to_toman( $sale, $cur ) );
    $product->update_meta_data( '_gtm_rate_used', (string) $cur['rate'] );
    $product->update_meta_data( '_gtm_priced_at', (string) time() );
    // WooCommerce's data store derives _price (respecting the sale schedule) and updates wc_product_meta_lookup.
}
```

**Sale lifecycle.** `wc_apply_sale_state_for_product()` calls `$product->save()` at sale start/end, which re-runs the materialiser; since the base sale price is the source, the base sale field is the **only** way to end a sale for a currency product. The UI says so (Toman sale field read-only), and on admin save the plugin clears `_gtm_base_sale_price` when `date_on_sale_to` has passed.

**Guard against writes that bypass the CRUD.** `register_post_meta()` is used for the three `_gtm_*price*` keys **only** for `sanitize_callback`/`auth_callback`, with `show_in_rest => false` (WooCommerce registers `product` with `show_in_rest => true`, so a `PUT /wp/v2/product/{id}` with `meta` would otherwise write postmeta directly and recreate the stale-price defect). As a safety net, `Pricing\Guard` hooks `updated_post_meta`/`added_post_meta`/`deleted_post_meta` for those keys and, unless the write came from inside a `WC_Data::save()` (tracked with a static flag set in `woocommerce_before_product_object_save` and cleared in `woocommerce_after_product_object_save`), schedules `wc_get_product( $id )->save()` on `shutdown`. A daily cron lists products whose `_gtm_rate_used` ≠ current rate or whose stored `_regular_price` ≠ recomputed value and surfaces them as a persistent admin notice with a "fix now" button.

**Rate change — chained batches, one purge.**

```php
public static function reprice_currency( string $code, int $user ): void {
    $ids = Products::ids_with_currency( $code );                  // product + product_variation, any status
    update_option( 'gadgetim_prices_version', (string) time() );   // part of woocommerce_get_variation_prices_hash
    RateHistory::append( $code, $old, $new, $user, count( $ids ) );
    if ( count( $ids ) <= 40 ) { Cache::suspend(); foreach ( $ids as $id ) self::reprice_product( $id ); self::finish( $code ); return; }
    $job = wp_generate_uuid4();
    set_transient( "gadgetim_reprice_$job", [ 'code' => $code, 'ids' => $ids, 'done' => 0, 'parents' => [] ], DAY_IN_SECONDS );
    as_enqueue_async_action( 'gadgetim_reprice_batch', [ $job, 0 ], 'gadgetim' );   // ONE action; each batch enqueues the next
}
public static function batch( string $job, int $offset ): void {
    $state = get_transient( "gadgetim_reprice_$job" ); if ( ! $state ) return;
    Cache::suspend();                                               // no per-product purges inside batches
    foreach ( array_slice( $state['ids'], $offset, 50 ) as $id ) { self::reprice_product( $id, $state['parents'] ); }
    $state['done'] = min( count( $state['ids'] ), $offset + 50 );
    set_transient( "gadgetim_reprice_$job", $state, DAY_IN_SECONDS );
    if ( $state['done'] < count( $state['ids'] ) ) as_enqueue_async_action( 'gadgetim_reprice_batch', [ $job, $state['done'] ], 'gadgetim' );
    else as_enqueue_async_action( 'gadgetim_reprice_finish', [ $job ], 'gadgetim' );   // enqueued only by the LAST batch
}
public static function reprice_product( int $id, array &$parents ): void {
    $p = wc_get_product( $id ); if ( ! $p ) return;
    $p->save();                                                     // before_save hook does the work
    if ( $p->is_type( 'variation' ) ) $parents[ $p->get_parent_id() ] = true;
}
public static function finish( string $job ): void {
    $state = get_transient( "gadgetim_reprice_$job" );
    foreach ( array_keys( $state['parents'] ?? [] ) as $pid ) WC_Product_Variable::sync( $pid );
    wc_delete_product_transients(); Cache::resume(); Cache::purge_all(); delete_transient( "gadgetim_reprice_$job" );
    do_action( 'gadgetim_rates_applied', $state['code'] );
}
```

The admin progress bar polls the job transient (`check_ajax_referer`, `manage_options`). `Cache::suspend()`/`resume()` is also used by the importer. Products whose currency is later disabled keep their last Toman prices and are flagged. Before each batch the runbook requires a fresh backup (7.2); the plugin shows the time of the last known backup next to the "Apply rates" button and warns if older than 24 h.

**Tests** (`tests/Pricing/`): saving a variation with a new base price yields the new `_price` and `wc_product_meta_lookup.min_price` in one save; a `PUT /wp/v2/product/{id}` with `meta._gtm_base_regular_price` does not expose the field (400/ignored) and a direct `update_post_meta()` triggers the guard; a 120-product reprice ends with every `_gtm_rate_used` equal to the new rate and exactly one `gadgetim_cache_purged_all`.

### 4.4 Admin

- *Product → General*: "Pricing basis" select (Fixed Toman / AED / USD …), base regular, base sale. With a currency chosen, Toman inputs are read-only with the computed value and the note "computed from 30 AED × 78 000"; server side ignores Toman edits for currency products (`woocommerce_admin_process_product_object`). Same fields per variation row (`woocommerce_variation_options_pricing`, read on `woocommerce_admin_process_variation_object`). "Set currency for all variations" bulk action.
- Quick edit / bulk edit: price fields hidden for currency products; CSV import/export column mapping in a later milestone.
- *Gadgetim → Currencies* (**manage_options**): rates inline, rounding, enable toggle, affected count, "Apply rates" → confirmation (shows last-backup age) → progress bar, history.
- WP-CLI: `wp gadgetim rate set AED 80000 --apply`, `wp gadgetim reprice --currency=AED`.

### 4.5 Display — explicit helpers, no global filters

The old draft converted digits through `wc_price`, `formatted_woocommerce_price` and `number_format_i18n`, and dates through `date_i18n`/`wp_date`. Those functions also feed machine output (sitemap `lastmod`, `<time datetime>`, JSON-LD, Store API JSON, `data-product_variations`, admin forms where `wc_format_decimal()` must parse the value back). **None of those global filters exist in this design (D9).** Instead:

- `gadgetim_price( WC_Product|int|float $x, array $opts = [] ): string` → theme price partial HTML for the current language (Persian digits + `٬`, Arabic-Indic digits by default for `ar` — admin toggle —, Latin for `en`), unit label, optional base hint. Used in cards, product page, cart drawer JSON (as `price_html`), search endpoint.
- `gadgetim_number( int|float $n ): string` and `gadgetim_date( int|DateTimeInterface $t, string $style = 'medium' ): string` (Jalali for `fa` via `Support\Jalali::format()`, Gregorian with locale month names for `en`/`ar`). Used in templates, account order list/detail overrides, email template overrides.
- `woocommerce_get_price_html` (priority 20) wraps WooCommerce's HTML with digit conversion **only** when `Context::is_storefront_render()` (never admin, AJAX writes, REST, feeds, cron, JSON-LD building) — this covers the few WooCommerce-owned places the theme does not template (cart totals table, order received table). `wc_price()` itself is never filtered, so `data-*` attributes, Store API and admin stay ASCII.
- Jalali conversion never touches `wp_date`/`date_i18n`; sitemaps, feeds, REST, JSON-LD and `<time datetime>` keep ISO/Gregorian by construction.

```html
<p class="price" data-id="4208">
  <span class="price__now"><bdi>۲٬۳۴۰٬۰۰۰</bdi> <span class="price__unit">تومان</span></span>
  <del class="price__was"><bdi>۲٬۷۳۰٬۰۰۰</bdi></del>
  <span class="price__off">‎-۱۴٪</span>
  <span class="price__base" dir="ltr" title="…">≈ AED 30</span>
</p>
```

Variable products show "from …". Old prices and badges only when a sale is active. The base-currency hint is an admin toggle (default on for the product page, off in cards).

### 4.6 Call-for-price mode (from the old `call-for-price` plugin)

Lives in `ContactMode`, settings tab "Contact instead of price" (manage_woocommerce), **off by default**; migration copies `cfp_options` into `gadgetim_settings['contact']`. Optional per-product override `_gtm_contact_only` (inherit / yes / no). When active:

- Price area: optional price line, then three pills — Call (`tel:` with the stored phone normalised to ASCII digits by `Support\Phone`, `esc_url`), WhatsApp (`esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $phone ) . '?text=' . rawurlencode( $name . ' ' . $url ) )`), Telegram (`https://t.me/<id>` after `sanitize_key`). On cards a single "Ask for price" button opens a bottom sheet. Buttons use brand tokens.
- Purchasing disabled: add-to-cart templates not rendered, `woocommerce_is_purchasable` → false, `woocommerce_add_to_cart_validation` → false, cart/checkout redirect to shop, drawer and header cart hidden, mobile sticky bar shows the pills.
- SEO: JSON-LD `offers` omitted unless `show_price` is on.

### 4.7 REST exposure

- Base fields are **not** exposed through `register_post_meta` (`show_in_rest => false`, see 4.3 guard). The sole REST surface is `wc/v3`: `woocommerce_rest_prepare_product_object` and `woocommerce_rest_prepare_product_variation_object` add `gadgetim_pricing { currency, base_regular, base_sale, rate_used, priced_at }`; `woocommerce_rest_insert_product_object` / `woocommerce_rest_insert_product_variation_object` read it, set the meta on the object and let the subsequent CRUD save materialise.
- `GET /wp-json/gadgetim/v1/currencies` (public, cacheable: code, label, rate, updated_at) and `PUT …/currencies/{code}` (manage_options, nonce).

### 4.8 Migration of the old AED meta

`wp gadgetim migrate-currency` (also Tools → Migrate, manage_options): per product with `_gadgetim_currency_regular_price`: set `_gtm_price_currency = AED`, copy regular/sale, save (materialises), delete old keys; seed `AED.rate = 78000`. Base sale values like `14.16666` are stored as entered. **Confirm the current AED rate before launch.**

---

## 5. Theme architecture

### 5.1 Brand: a new bilingual mark (proposal)

Concept **"Charge dots"**: in «گجتیم» exactly three dots exist (one under ج, two above ت). The mark turns those dots into charge-indicator LEDs — three rounded squares in the accent colour — and they become the brand's motion motif. The Latin companion "Gadgetim" echoes it with the dot of the *i*.

- **Primary wordmark (Persian)**: custom-lettered «گجتیم», geometric low-contrast monoline, slightly condensed, the two strokes of گ cut parallel like a connector; dots replaced by accent squares. Delivered as SVG paths (no webfont dependency — Estedad or any display face is used only as the drawing starting point, 5.3).
- **Latin companion**: "Gadgetim" in a modified geometric grotesk (start from Manrope/Space Grotesk, customise the *G* and the dot); alone on `/en/`.
- **Monogram / favicon / app icon**: the three dots in a 2+1 arrangement inside a squircle; light variant ink squircle + accent dots, dark variant accent squircle + ink dots; maskable PWA icon.
- **Colour**: `--ink #0B1020`, `--paper #F7F7F5`, accent `--charge #2B5BFF` (continuity with the existing favicon blue), `--volt #B8FF3B` (sale badges and "fully charged" only), `--trust #0FA37F` (authenticity), greys from one OKLCH ramp. Nothing like DinaKala's teal/white.
- **In the header**: inline SVG wordmark (≈ 2 kB), dots animate once on first paint (600 ms; reduced motion → static). On scroll the wordmark compresses to the monogram (`scroll-timeline` when supported, IntersectionObserver fallback).
- **Loader**: no blocking splash. A 3-px top progress bar made of the three dots during fetches; pulsing dots inside pending buttons.
- **Page transitions**: cross-document View Transitions (`@view-transition { navigation: auto }`). `view-transition-name` is assigned **only to the clicked card's image** in a `pageswap` handler and to the hero image in `pagereveal` — never to all 24–48 cards (snapshotting dozens of elements is slow on mid-range Android). Header static, content fades. Unsupported browsers just navigate.
- Deliverables: `logo.svg` (fa, en, stacked, mono), favicon set, OG template (1200×630), `brand.md`.
- **Watermark conflict** (8.1 #3).

### 5.2 Template hierarchy and WooCommerce overrides vs hooks

Theme support: `add_theme_support( 'woocommerce' )`; **no** gallery zoom/lightbox/slider support; `woocommerce_enqueue_styles` → empty; dequeue `woocommerce`, `wc-cart-fragments`, `sourcebuster-js`, `wc-order-attribution`, `wc-add-to-cart-variation` (own module), `jquery` except on checkout/account.

**M0 step (WC 11 defaults):** replace the auto-created block Cart/Checkout page content with `[woocommerce_cart]` / `[woocommerce_checkout]`; disable the `email_improvements` feature (WooCommerce → Settings → Advanced → Features); copy every override from the installed `woocommerce/templates/` (11.2.1) and record that version in each `@version` header. *WooCommerce → Status → Templates* is part of the update runbook.

| Area | Override | Hooks instead |
|---|---|---|
| Archive | `archive-product.php`, `content-product.php`, `loop/pagination.php`, `loop/result-count.php`, `loop/orderby.php` | remove `woocommerce_template_loop_*` defaults, add theme card parts |
| Single | `single-product.php`, `content-single-product.php`, `single-product/add-to-cart/{simple,variable}.php`, `single-product/product-image.php`, `single-product/tabs/tabs.php`, `single-product/related.php` | `woocommerce_single_product_summary` kept |
| Cart | `cart/cart.php`, `cart-totals.php`, `cart-empty.php`, `proceed-to-checkout-button.php` | — |
| Checkout | `checkout/form-checkout.php`, `form-billing.php`, `form-shipping.php`, `review-order.php`, `payment.php`, `payment-method.php`, `thankyou.php`, `form-login.php`, `form-coupon.php` | gateway hooks untouched |
| Account | `myaccount/*` | — |
| Global/notices | `global/wrapper-start/end.php`, `global/breadcrumb.php`, `notices/*.php` | — |
| Emails | `emails/email-header.php`, `email-footer.php`, `email-styles.php`, `email-order-details.php` | `woocommerce_email_styles` |

Theme hierarchy: `front-page.php` (sections from `gadgetim_home`), `page.php`, `page-contact.php`, `single.php`/`archive.php` (blog, if kept), `search.php`, `404.php`. `inc/woocommerce.php` holds all hook wiring.

### 5.3 Design tokens, CSS, fonts

`tokens.css` (inlined, ≤ 3 kB):

```css
:root {
  --ink: #0b1020; --paper: #f7f7f5; --charge: #2b5bff; --volt: #b8ff3b; --trust: #0fa37f;
  --surface-1: #ffffff; --surface-2: #eeeeea; --line: color-mix(in oklab, var(--ink) 12%, transparent);
  --text: var(--ink); --text-2: color-mix(in oklab, var(--ink) 68%, var(--paper));
  --radius-1: .5rem; --radius-2: 1rem; --radius-3: 1.75rem;
  --space-1: .25rem; /* … to --space-9 */
  --font-ui: "Vazirmatn", "Vazirmatn Fallback", system-ui, sans-serif;   /* fa + ar; headings use the weight axis */
  --font-latin: "Inter", "Inter Fallback", system-ui, sans-serif;         /* en only */
  --ease-out: cubic-bezier(.2,.8,.2,1); --ease-spring: linear(0, .6 25%, 1.05 55%, .98 75%, 1);
  --dur-1: 120ms; --dur-2: 240ms; --dur-3: 480ms;
  --dir: 1;
}
:root[dir="rtl"] { --dir: -1; }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --paper:#0b0d12; --ink:#f3f4f6; --surface-1:#12151c; --surface-2:#1a1e27; } }
:root[data-theme="dark"] { --paper:#0b0d12; --ink:#f3f4f6; --surface-1:#12151c; --surface-2:#1a1e27; }
:lang(en) body { font-family: var(--font-latin); }
@media (prefers-reduced-motion: reduce) { :root { --dur-1: 1ms; --dur-2: 1ms; --dur-3: 1ms; } }  /* 1ms, not 0: transitionend still fires */
```

Layers `@layer tokens, base, layout, components, shop, pages, utilities`. BEM-ish components, state via `data-*`/`aria-*`. Container queries for cards. `content-visibility: auto` only on sections with a fixed `contain-intrinsic-size` and no in-page anchors (the long description keeps normal rendering; its "read more" is a height clamp).

**Fonts — one variable font per script, within budget.** The earlier two-font plan (Vazirmatn + Estedad, both above the fold) could not fit ≤ 180 kB. Now:

- **Vazirmatn** variable (OFL), one file, subset to Arabic script + Latin + punctuation + both digit sets; headings use the weight axis (700–800) with tighter tracking. ≈ 100–140 kB woff2. Preloaded on fa/ar.
- **Inter** variable (OFL) on `/en/` only (≈ 40 kB subset). Preloaded on en.
- **Estedad** is **not** loaded on the site; it is a drawing reference for the SVG wordmark and may be used in the OG-image template only.
- Loading: `font-display: swap` with a `"Vazirmatn Fallback"` `@font-face` whose `size-adjust`/`ascent-override`/`descent-override` are tuned against the Android default Arabic fallback (Noto Naskh Arabic) and measured; if CLS stays above 0.05 on the fa home page in Lighthouse mobile throttling, switch to `font-display: optional` (first view falls back, repeat views get Vazirmatn from cache). Fonts served with 1-year immutable caching.
- Arabic review: Vazirmatn draws Arabic with Persian conventions; an Arabic reader reviews `/ar/` once; swapping the `ar` UI font (e.g. IBM Plex Sans Arabic) is a one-token change.

### 5.4 JS module structure

`app.js` (`type="module"`, deferred, ≈ 6 kB) boots: theme toggle (pre-paint inline script), `motion`, `drawer`, `switcher hint`, `toast`, and lazily imports page modules by `data-page`.

| Module | Role | Loads |
|---|---|---|
| `motion.js` | Reveal (`.reveal` → `.is-in`) **only on elements below the first viewport and only under `html.js`** (class set by the inline pre-paint script); the LCP candidate and anything in the first screen never start hidden; a 2 s CSS fallback (`@keyframes` with `animation-fill-mode` ending visible) guarantees content appears if the module fails; scroll-driven header; `pageswap`/`pagereveal` view-transition naming; reduced motion → immediate state | all |
| `drawer.js` | `<dialog>` drawers/sheets, focus trap, `inert`, swipe-to-close; completion awaited via `element.getAnimations()` → `Promise.allSettled`, never a bare `transitionend` | all |
| `cart.js` | Store API client. **Only runs the initial `GET /wc/store/v1/cart` when `document.cookie` contains `woocommerce_items_in_cart` or `wordpress_logged_in_`**; otherwise the header shows the cached count 0 and nothing is fetched. Loaded on first cart interaction or when the cookie exists; `Nonce`/`Cart-Token` handling on the first POST; after writes, re-fetches with `?lang=`; `aria-live` announcements | conditional |
| `search.js` | overlay, debounce 250 ms, min 2 chars, `gadgetim/v1/search`, recent searches (localStorage), keyboard nav | on open |
| `gallery.js` | scroll-snap gallery, thumbs, pinch/zoom, keyboard | product |
| `variations.js` | reads `data-product_variations` (ASCII prices), renders through `price.js` | variable products |
| `price.js` / `i18n.js` | `Intl.NumberFormat` per language (`fa-IR`, `ar-u-nu-arab`, `en`), strings from `<script type="application/json" id="gadgetim-config">` encoded with `wp_json_encode( $cfg, JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_UNESCAPED_UNICODE )` | all |
| `filters.js` | filter drawer as a `<form method="get">` (buttons, not anchors — no crawlable URL explosion), URL sync, "load more" fetching the next real paginated URL | archive |
| `switcher.js` | one-time "view in English?" hint | all |
| `checkout.js` | step indicator, mobile number normalisation (Persian digits → ASCII), error summary focus | checkout |

No jQuery in theme code. `<link rel="modulepreload">` for `app.js`, `motion.js`, `drawer.js`. Budget ≤ 60 kB gzipped total.

Motion inventory (transform/opacity only): hero float + parallax, card hover lift + image swap, add-to-cart morph, drawer spring, price ticker, charge-bar for fetches, header compress, below-fold section reveals with stagger, 404 "low battery", skeleton shimmer. Every effect has a reduced-motion variant.

### 5.5 Images

- Sources are 800×800 WebP. Sizes: `gtm-thumb` 160², `gtm-card` 400², `gtm-hero` 800²; defaults `medium_large`/`1536`/`2048` disabled; `big_image_size_threshold` → false. GD with WebP on Local; production GD/Imagick WebP support is in the host questionnaire. AVIF skipped.
- Cards: `srcset` 400/800, `sizes`, `loading="lazy" decoding="async"`, fixed aspect box. Images sit on a **flat `--surface-1` plate in both themes** (no `mix-blend-mode`: per-card compositing layers jank while scrolling, and the 2026 watermark forbids dark plates anyway). Off-white backgrounds are normalised at import where the audit flags them.
- Product page LCP: first gallery image 800 px, `fetchpriority="high"`, `<link rel="preload">`, LQIP background.
- **LQIP in PHP**: `Catalog\Lqip` generates a 16-px data URI with GD on `wp_generate_attachment_metadata` (and a one-off Tools action for existing attachments), stored as attachment meta `_gtm_lqip`. Works for every future upload; no Node dependency. `tools/images/audit.mjs` only reports sizes and white-border lifestyle photos for manual re-cropping.

### 5.6 Cart drawer, checkout, account

Store API for the drawer (cookie-gated, 5.4), classic templates for cart/checkout/account (D4). Checkout fields: first/last name, mobile (required, normalised), email (required), province (`IR` states), city, address, postcode (10 digits); company/address-2 removed; notes kept. Shipping from WooCommerce zones. "Order tracking" becomes a themed account entry later if wanted.

### 5.7 SEO

`Seo\Meta`: `<title>` template per language, meta description, canonical, robots (incl. the launch gate), Open Graph/Twitter, hreflang. `Seo\JsonLd`: `Organization`, `WebSite` + `SearchAction`, `BreadcrumbList`, `ItemList`, `FAQPage`, and extends `woocommerce_structured_data_product`: `brand`, `sku`, `image[]`, `offers.priceCurrency = "IRR"` with `price = toman × 10` (to confirm), `availability`, `itemCondition`, `hasMerchantReturnPolicy`. Dates in JSON-LD are built from `DateTime::format( DATE_W3C )`, never from `wp_date` (which is anyway unfiltered, D9). WooCommerce's breadcrumb schema disabled.

Titles: the importer's **default** splits the boilerplate «اصل و اورجینال … با ضمانت اصالت کالا» out of `post_title` into a badge and keeps the old slug (so URLs and SEO continuity are unaffected); the full old title is stored in `_gtm_seo_title` for review. The client can override (8.2) but the SEO-safe variant is the default.

### 5.8 Accessibility

Skip link, landmarks, visible focus, `<dialog>` drawers with focus trap/`Escape`/`inert`, touch targets ≥ 44 px, AA contrast in both themes, labels + error summary + `aria-invalid`, `aria-live` for cart/search, keyboard-operable gallery/carousels, no hover-only information, `lang` on fallback content, reduced motion everywhere, inline SVG sprite with `aria-hidden` + text labels.

### 5.9 Performance budget (mid-range Android, 4G, page cache on)

| Metric / asset | Budget |
|---|---|
| LCP / CLS / INP | ≤ 2.5 s / ≤ 0.05 / ≤ 200 ms (Lighthouse mobile ≥ 90 on home, category, product) |
| HTML | ≤ 60 kB gzipped |
| CSS | inline critical ≤ 12 kB; `app.css` ≤ 30 kB gz; `shop.css` ≤ 15 kB gz |
| JS | ≤ 60 kB gz total; no jQuery outside checkout/account |
| Fonts | **1 file** on first view, ≤ 140 kB |
| LCP image | ≤ 60 kB |
| Server requests per cached page view for an anonymous visitor without a cart | **0** (no cart fetch, no view beacon unless sampled) |
| Requests above the fold | ≤ 15 |

Measurement: `npx lighthouse … --preset=perf --form-factor=mobile` before each milestone sign-off; WebPageTest from Tehran after launch.

### 5.10 Sanitisation and output escaping (new)

`docs/sanitisation.md` is normative; summary:

| Key / input | Sanitiser on save | Escape on output |
|---|---|---|
| `_gtm_title_*`, `_gtm_subtitle*`, `_gtm_seo_title*`, `_gtm_seo_desc*`, `_gtm_name_*` | `sanitize_text_field` | `esc_html` |
| `_gtm_slug_*` | `sanitize_title` + uniqueness (3.4) | `esc_attr`/`esc_url` |
| `_gtm_excerpt_*`, `_gtm_content_*`, `_gtm_desc_*`, `_gtm_guarantee*`, FAQ answers | `wp_kses_post` (Shop Managers lack `unfiltered_html`) | `wp_kses_post` |
| `_gtm_specs*`, `_gtm_faq*` | JSON decoded, each cell `sanitize_text_field` / `wp_kses_post`, re-encoded | per cell |
| `_gtm_status_*` | whitelist `published\|draft` | — |
| `_gtm_price_currency` | whitelist of enabled codes | — |
| `_gtm_base_*_price` | `wc_format_decimal`, ≥ 0 | — |
| Settings text fields | `sanitize_text_field` / `wp_kses_post` by schema | `esc_html`/`wp_kses_post` |
| Phone / WhatsApp / Telegram | `Support\Phone::normalize` (digits only) / `sanitize_key` | `esc_url` |
| Redirect targets | same-host relative path **or** host in an explicit allow-list; normalised (6) | `esc_url_raw` |
| `gadgetim-config` JSON | — | `wp_json_encode( …, JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_UNESCAPED_UNICODE )` |

All applied in the metabox save, term save, settings sanitize callback, `register_post_meta` sanitize callbacks and REST handlers.

---

## 6. Core plugin modules (besides i18n and currency)

| Module | What it does | Capability / hooks |
|---|---|---|
| `Settings` | *Gadgetim → Settings*, tabs: Brand & contact, Trust badges, Contact mode, Home sections, SEO (manage_woocommerce); Languages, Currencies, Redirects, Strings, Tools (manage_options). Tools: install language packs, migrate currency, rebuild slug map, rebuild search index, regenerate LQIP, purge cache, export redirects, slug conflicts, translation export/import | `register_setting` with schema sanitizer; per-action nonces; `check_ajax_referer` on progress polls |
| `Catalog\ProductExtras` | subtitle, specs, FAQ, guarantee override, featured from old `special`, views (below) | metabox, `register_post_meta` |
| `Catalog\ProductExtras` — popularity | "Popular" sort uses `wc_product_meta_lookup.total_sales` first, `_gtm_views` as the tiebreaker; `_gtm_views` is written as `0` on product creation/import so the meta join never drops products. View counting is **sampled**: client sends `navigator.sendBeacon` to `POST /gadgetim/v1/view/{id}` on 1 of 5 page views; server dedupes per hashed IP + product for 24 h in a transient, increments by 5, rate-limited. Can be switched off in Settings | `woocommerce_get_catalog_ordering_args`, `Rest\Views` |
| `Catalog\Brands` | native `product_brand` helpers (logo term meta `_gtm_logo_id`, per-language intro), brand strip, merge rules for the importer | term meta |
| `Catalog\Lqip` | GD-generated 16-px placeholder per attachment (5.5) | `wp_generate_attachment_metadata` |
| `Seo\Redirects` | option `gadgetim_redirects` (autoload no) keyed by **normalised** path: `rawurldecode` → NFC (`Normalizer`) → Arabic→Persian letters (`Support\Normalize`) → lowercase ASCII → trailing slash. Request paths normalised identically before lookup. Runs only on `is_404()` at `template_redirect` priority 1. Targets validated (5.10); changes logged with user and time. Admin page (manage_options) with CSV import/export + "test URL". `tools/deploy/check-redirects.sh` hits all 226 old URLs and compares | `template_redirect` |
| `Checkout\Gateways` | hides `cod`/`bacs` on the storefront (port of the old plugin), admin toggle per gateway | `woocommerce_available_payment_gateways` |
| `Checkout\Fields` | Iranian field set, mobile normalisation, 10-digit postcode | `woocommerce_checkout_fields`, `woocommerce_checkout_process` |
| `Checkout\ReturnUrls` | language-correct gateway return/cancel/checkout URLs (3.8) | `woocommerce_get_checkout_order_received_url`, `woocommerce_get_cancel_order_url(_raw)`, `woocommerce_get_checkout_url`, `woocommerce_api_*`, `woocommerce_pre_payment_complete` |
| `I18n\Orders` / `I18n\Emails` | `_gtm_lang`, Persian line items + display-time translation, order-based email locale (3.8) | see 3.8 |
| `Support\Digits` / `Support\Jalali` | pure helpers behind `gadgetim_number()`, `gadgetim_price()`, `gadgetim_date()`; **no global filters** | — |
| `Support\Cache` | purge adapter with `suspend()/resume()` (7.4) | `gadgetim_cache_purge_*` |
| `Support\RateLimit` | hashed-IP token bucket in a transient (search, views, currencies) | — |
| `Rest\Search` | instant search (3.6) | `gadgetim/v1/search` GET |
| `Import` | migration tooling (6.1) | WP-CLI + admin batch runner (manage_options) |

### 6.1 Import tooling

`wp gadgetim import --from="C:/Users/GHADER/Documents/ProjectGit/gadgetim-ir/first-backup/extracted" [--terms] [--products] [--images] [--pages] [--dry-run] [--limit=N]` (plain-PHP fallback via `includes/Import/Cli.php`). Idempotent through `_gtm_import_old_id`. Runs with `Cache::suspend()` and `Context::$suspend_translations`.

1. **Terms**: categories by `data/category-map.json`; brands by `data/brand-merge.json`; tags: only the 2 used. `_gtm_status_*` left absent (untranslated).
2. **Products** via `WC_Product_Simple`: name (title split per 5.7 default), slug = old slug, status, stock, featured, categories, brands, tags, `_gtm_subtitle`, `_gtm_views` (old value, or 0), currency fields (`AED` + base prices) → materialised Toman prices; date preserved.
3. **Descriptions**: strip inline `style`/`dir`/`lang`, empty paragraphs; `<strong>Label:</strong> value` lines → `_gtm_specs`; remaining text → excerpt. In-content image URLs rewritten to the new attachment URLs via the `src → attachment` map.
4. **Images**: sideload each file; during each sideload an `upload_dir` filter forces `/<Y>/<M>/` from the source path (`media_handle_sideload` would otherwise use the parent post's date), so original paths survive and in-content references stay valid even before rewriting. Skip already imported (`_gtm_import_src`). LQIP generated by `Catalog\Lqip` on the metadata hook.
5. **Pages**: privacy, terms, about, contact, FAQ with cleaned content; redirects written for dropped pages.
6. **Menus**: built from categories by the theme; old menus inform the order.
7. **Redirects**: `data/redirects.csv` for every old URL not resolving 1:1, keys normalised (6).
8. **Orders/customers**: out of scope; separate command later if wanted.

Admin batch runner: *Tools → Import* in 20-item AJAX batches with progress (manage_options).

### 6.2 Launch data move (Local → production)

**Engine parity first.** Local currently runs MySQL 8.4; production is MariaDB 10.11. A MySQL 8.4 dump carries `/*!80016 DEFAULT ENCRYPTION */`, `utf8mb4_0900_ai_ci` collations and `utf8mb3` spellings that MariaDB rejects, and the fresh database default collation would be `utf8mb4_0900_ai_ci`. **In M0, before WooCommerce creates more tables**, the Local site is recreated (or its database switched) with Local's **MariaDB** engine, and `wp-config.php` sets `DB_CHARSET 'utf8mb4'` and `DB_COLLATE 'utf8mb4_unicode_520_ci'` explicitly. The launch move:

1. `tools/deploy/export-db.ps1`: clone the Local DB to a scratch database, run `wp search-replace 'http://gadgetim.local' 'https://gadgetim.ir' --all-tables --precise --skip-columns=guid` through `tools/wp.ps1` on the clone (serialized values handled correctly; a plain text replace would corrupt lengths inside serialized options), then `mysqldump --default-character-set=utf8mb4 --skip-comments --no-tablespaces --set-gtid-purged=OFF` and strip any remaining `/*!80xxx */` directives.
2. Import into the production MariaDB; copy `wp-content/uploads`; upload the release zips; regenerate `.htaccess` on the server (Local's Apache config is not the host's); flush permalinks from wp-admin in Persian context.
3. By design the importer stores root-relative image URLs and our options never contain absolute URLs; `tools/deploy/check-serialized.php` still lists any option containing `gadgetim.local` after step 1 (expect zero).
4. **Rehearsal in M3** into a second Local MariaDB site from the exported dump, followed by `check-redirects.sh` and the i18n test matrix; the rehearsal is in `docs/runbooks/deploy.md`.

---

## 7. Security, backups, updates, page cache

### 7.1 Security

- `wp-config.php` (production): unique salts, `DISALLOW_FILE_EDIT`, `WP_AUTO_UPDATE_CORE = 'minor'`, `FORCE_SSL_ADMIN`, `WP_ENVIRONMENT_TYPE = 'production'`, debug log outside webroot; Local: `WP_ENVIRONMENT_TYPE = 'local'`, `WP_DEBUG` + `SCRIPT_DEBUG`.
- **Capabilities (D11)**: `manage_options` for Redirects, Strings, Tools (language packs, migration, import, purge, LQIP), Languages, currency rates and "Apply rates"; `manage_woocommerce` for product-level fields, contact mode, badges, home sections, brand/contact settings. Reason: Shop Managers hold `manage_woocommerce`; an open redirect or a language-pack download must not be one compromised shop-manager account away. `wp_download_language_pack()` is additionally gated behind `current_user_can( 'install_languages' )`.
- Redirect targets validated as same-host relative paths (or an explicit host allow-list), stored normalised, change-logged.
- Core plugin: capability checks + nonces on every admin action and REST write; `$wpdb->prepare` everywhere; no `eval`, no remote code; REST user enumeration off; XML-RPC disabled; author archives off; version strings stripped; comments only as reviews; generic login errors.
- Headers via `.htaccess` (and `send_headers`): HSTS after SSL confirmed, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options: SAMEORIGIN`; CSP report-only first.
- Admin access: strong passwords, optional IP allow-list in `.htaccess`; limit-login left to the host. 2FA would need a plugin → client decision.
- WooCommerce: guest checkout on, "Coming soon" while building on production, webhooks off, REST keys only when needed.

### 7.2 Backups

- Code: git (GitHub); releases are tags.
- Content: cPanel backup wizard weekly (full) + daily cron `tools/server/backup.sh` (`mysqldump` + `tar` of uploads) into `~/backups` **outside the webroot**, keep 7 daily / 4 weekly, off-site copy (rclone or scheduled download) depending on host (8.2). Restore drill in `docs/runbooks/backup.md`, tested once on Local.
- **No wp-admin "snapshot" feature.** A PHP-written dump would land inside `public_html` (the only writable place on most shared hosts) and expose hashes, salts and order PII if a deny rule ever failed; `exec('mysqldump')` is usually disabled anyway. Instead the plugin records the last backup time written by `backup.sh` into a small marker file read by the Currencies page, and the rate-change runbook requires a backup younger than 24 h before "Apply rates". If the host offers SSH, `wp db export` to `~/backups` is the manual path.

### 7.3 Update strategy

- WordPress: minor auto-updates; majors after Local testing.
- WooCommerce: Local first, run the smoke list (3.2 matrix + checkout + rate change + email matrix), check *Status → Templates*, then production.
- Our packages: release zips, plugin before theme; every release notes WC/WP versions tested.
- Gateway and cache plugins: Local first; settings documented in the runbook.

### 7.4 Page cache plugin: what it must support

Must: full-page disk cache under Apache/LiteSpeed; automatic WooCommerce exclusions; per-URL/per-post purge callable from PHP; purge-all; query strings as separate keys (or ignore only UTM); no HTML/JS "optimisation" features; no language cookie logic.

`Support\Cache` adapter:

```php
private static int $suspended = 0;
public static function suspend(): void { self::$suspended++; }
public static function resume(): void  { self::$suspended = max( 0, self::$suspended - 1 ); }
public static function purge_all(): void {
    if ( self::$suspended ) { self::$pending_all = true; return; }
    if ( has_action( 'litespeed_purge_all' ) ) do_action( 'litespeed_purge_all' );
    elseif ( function_exists( 'wp_cache_clear_cache' ) ) wp_cache_clear_cache();
    elseif ( has_action( 'cache_enabler_clear_complete_cache' ) ) do_action( 'cache_enabler_clear_complete_cache' );
    elseif ( function_exists( 'wpfc_clear_all_cache' ) ) wpfc_clear_all_cache( true );
    do_action( 'gadgetim_cache_purged_all' );
}
public static function purge_post( int $post_id ): void { if ( self::$suspended ) return; /* post + home + shop + its archives in all public languages via with_lang() */ }
```

Purge triggers: product/term save (post + archives, all languages), rate apply (one purge-all at `finish`), settings/menu save (all), importer end (all). Order status changes purge nothing.

Candidates (choose at M3 once the host confirms its web server): **LiteSpeed Cache** if LiteSpeed (page cache only, everything else off), otherwise **Cache Enabler**; WP Super Cache / WP Fastest Cache as fallbacks; W3TC and Surge rejected.

---

## 8. Risks, open questions, milestones

### 8.1 Risks (with mitigation)

1. **URL-prefix via `home_url` edge cases**. Mitigation: two-phase prefixing (3.2), explicit `relative` handling, the day-one matrix including the rewrite-regeneration case; fallback plan is rewrite-rule duplication with a `lang` query var — same storage, different routing.
2. **Hook behaviour drift in future WC releases** (the ones used here are verified against 11.2.1). Mitigation: PHPUnit tests named in 3.3/3.4/4.3 run on every WooCommerce update on Local.
3. **Old watermark baked into ~200 images**. Options: accept during transition; obtain clean packshots and re-watermark with the new mark at import; crop the bottom-right wordmark only. Decision needed before the logo work finishes.
4. **Production environment unknown** (PHP minor, LiteSpeed vs Apache, GD WebP, SSH/CLI, backup tooling). Mitigation: host questionnaire; code targets PHP 8.1 and GD-only.
5. **Translation workload** and the **launch gate**: `/en/` and `/ar/` stay closed to search engines until key categories have translations. Mitigation: "copy from Persian" buttons, `wp gadgetim translations export/import` (JSON) for offline drafting and in-admin review.
6. **Motion vs performance**. Mitigation: budget table is a release gate; first viewport never animates in; one font; Lighthouse before each sign-off.
7. **Classic checkout longevity**. Mitigation: overrides keep standard hook points; block checkout remains possible later.
8. **Rate change while shoppers hold carts**. Mitigation: materialised prices update instantly, WooCommerce recalculates at checkout; cart note; quiet hours; backup-age check.
9. **Search quality**. Mitigation: normaliser unit tests with real titles.
10. **Data move**. Mitigation: MariaDB on Local, `wp search-replace` on a clone, rehearsal in M3.
11. **Scope creep in the home builder**: fixed sections with options, no drag-and-drop.
12. **Abuse of public endpoints** (search, views). Mitigation: GET-only, length caps, rate limits, sampling, no per-query transients; views can be disabled.

### 8.2 Open questions for the client / host (not guessed)

- Current AED rate (old: 78 000) and other currencies at launch (USD?).
- Titles: accept the default (boilerplate moved to a badge, slug unchanged) or keep the long titles verbatim?
- Drop or keep: 6 blog posts, wishlist/compare pages, 11 empty categories, unused tags; brand merge list and display language of brand names.
- Watermark policy for existing images and for new uploads.
- Gateway choice (BitPay.ir again?) and whether `cod`/`bacs` stay hidden.
- Old orders/customers: migrate or start fresh.
- Hosting: LiteSpeed or Apache, PHP version, SSH/CLI, backup tooling, static IP for admin allow-list, GD WebP.
- Arabic digits: Arabic-Indic (default) or Western on `/ar/`.
- Schema.org `priceCurrency`: `IRR` × 10 — confirm.
- SMS notifications out of scope — confirm. 2FA (one more plugin) — wanted?
- When should `/en/` and `/ar/` open to search engines (launch-gate criteria above acceptable)?

### 8.3 Milestones

| M | Scope | Done when |
|---|---|---|
| **M0 (day 1)** | Repo scaffold, junctions (safe script), lint, PHPUnit bootstrap; **Local DB switched to MariaDB with explicit utf8mb4 charset/collation**; WooCommerce 11.2.1 with `fa_IR` + `ar` packs; **cart/checkout pages switched to shortcodes, email improvements off**; `gadgetim-core` skeleton (autoloader, Settings shell, capabilities); **two-phase i18n routing + locale** with the 3.2 matrix green (incl. rewrite regeneration case); theme skeleton with tokens and compat layer; Vazirmatn subset loading; `wp.ps1` running `wp i18n make-pot` | `/`, `/en/`, `/ar/` render a hello-world shop in the right locale/direction; `/robots.txt` survives a rewrite flush under `/en/` |
| **M1 (day 2–3)** | Pricing (currencies page, product/variation fields on `process_*_object`, materialiser, guard, chained reprice, migration), Contact mode, ProductExtras, helpers (`gadgetim_price/number/date`), term-integrity + pricing tests, Importer v1 on Local (with LQIP and `upload_dir` preservation) | real catalogue on Local with correct Toman prices; price sort/filter correct; 107-product reprice ends with one purge; tests green |
| **M2 (day 3–6)** | Theme: header/nav/mega-menu/drawers/switcher, home sections, cards, archive + filters (form-based) + load-more, product page, search overlay + endpoint (rate-limited), cookie-gated cart drawer, motion system (below-fold only), dark mode, logo v1 SVG, visibility gate on all product loops | design review on phone; Lighthouse mobile ≥ 90 on home/category/product; `/en/` archives show only published translations |
| **M3 (day 6–8)** | Cart/checkout/account templates, gateway in sandbox incl. `en`/`ar` round trip and return URLs, emails (order-based locale, admin fa, resend, deferred), SEO (meta, JSON-LD, hreflang, sitemaps, robots with launch gate), redirects + normalisation + check script, cache plugin + purge adapter, accessibility pass, security hardening, **data-move rehearsal onto a second Local MariaDB site** | end-to-end test orders in fa/en/ar with the email matrix passing; sitemaps/hreflang validated; purge works; rehearsal import clean |
| **M4 (day 8–10)** | Translation admin UX, strings page (incl. ar-pack gaps), translation export/import, first en/ar batch, logo/favicon/OG final, QA matrix (3 languages × page types × themes × devices), runbooks, release zips `v1.0.0-rc1` with SHA-256 | staging-quality site on Local, release artefacts ready |
| **Launch** | Host questionnaire answered, DB/uploads move per 6.2, redirects verified (226 URLs), Search Console sitemap (Persian; `/en/`/`/ar/` after the gate), backups scheduled, monitoring | old URLs 200/301/410 as mapped; CWV green from a Tehran test |

The client's stated order (theme first, then links/products/pages) is respected for *production* content; importing the catalogue onto Local in M1 is only so the design is built against real data.

---

## 9. Decisions log (changes from v0.1 after review)

| # | Issue raised (severity) | What changed |
|---|---|---|
| 1 | Term translation by mutating `get_term`/`get_terms` objects + `?lang=` honoured on REST writes could overwrite Persian names via `wp_update_term` (blocker, both critics) | 3.1/3.2/3.3: `?lang=`/header accepted only for GET/HEAD to Store API and `gadgetim/v1`; no filters on `get_term`/`get_terms`/edit-context getters; translation only at output points (`single_term_title`, breadcrumb, menu items, `term_links-*`, theme helper `gadgetim_term_name()`); `Context::$suspend_translations` around writes/admin/REST writes/CLI; `TermIntegrityTest` |
| 2 | Untranslated products visible and indexable in `/en/`,`/ar/` archives/search; hreflang pointing at mixed-language pages (blocker) | D10, 3.1, 3.5, 3.6, 3.7: `_gtm_status_{lang}=published` gates every product query (archives, search, related, home sections, REST search, sitemaps); empty archives noindex; `public` launch gate with `Disallow` + noindex + sitemap/hreflang exclusion; default fallback `404` until public |
| 3 | Jalali/Persian digits via global `date_i18n`/`wp_date`/`number_format_i18n`/`wc_price` filters corrupting sitemaps, JSON-LD, REST, admin (blocker + major) | D9, 4.5, 6: all global filters removed; explicit helpers `gadgetim_price/number/date()`; only `woocommerce_get_price_html` wrapped, guarded by `Context::is_storefront_render()` |
| 4 | Lazy rewrite-rule regeneration under a prefixed `home_url()` drops `robots.txt`/`favicon.ico` rules (major) | 3.2: two-phase prefixing — rules regenerated in `do_parse_request` before `Urls::enable_prefix()`; explicit flushes wrapped in Persian context; test added to the matrix |
| 5 | Translated shop-page slug routes to a plain page (major) | 3.4: WooCommerce pages and endpoints are not slug-translatable; metabox greys the field; `page_link` leaves them alone |
| 6 | Variation currency meta written after the save that materialises (major) | 4.3: meta read on `woocommerce_admin_process_product_object` / `woocommerce_admin_process_variation_object`; verified that `woocommerce_before_product_object_save` covers variations (no separate variation hook exists); test asserts one-save correctness |
| 7 | Order line items frozen in shopper's language (major) | D12, 3.8: items stored Persian (`get_name('edit')`), translated at display via `woocommerce_order_item_name`/`woocommerce_order_item_get_name` in customer context; `_gtm_lang` single source of truth |
| 8 | Emails: admin "New order" inherits request locale; resends and deferred sends bypass action wrappers; `setup_locale()` runs before the object is known (major, both critics) | 3.8: context from the order via `woocommerce_email_recipient_{id}`; admin emails forced `fa_IR`; `woocommerce_allow_switching_email_locale` → false; `woocommerce_before_resend_order_emails` handled; restore on `woocommerce_email_restore_locale`; M3 email matrix |
| 9 | Gateway return/cancel URLs built in Persian context (major) | 6 `Checkout\ReturnUrls`, 3.8: filters on `woocommerce_get_checkout_order_received_url`, `woocommerce_get_cancel_order_url(_raw)`, `woocommerce_get_checkout_url`; order-based context set in `woocommerce_api_*`/`woocommerce_pre_payment_complete`; sandbox test in en/ar |
| 10 | Reprice: `finish` could run before batches; per-product purges and 300 synchronous saves time out (major, both critics) | 4.3: chained batches (each enqueues the next, last enqueues `finish`), sync threshold 40, `Cache::suspend()/resume()`, parents synced in `finish`, one purge-all |
| 11 | `Remove-Item` on a junction in PowerShell 5.1 can delete repo files (major + minor) | 2.4: reparse-point check, `[System.IO.Directory]::Delete`, refuse on real directories |
| 12 | MySQL 8.4 → MariaDB 10.11 dump incompatibilities; no rehearsal (major) | D8, 6.2, M0/M3: Local switched to MariaDB with explicit utf8mb4 charset/collation; dump flags; rehearsal on a second Local site |
| 13 | `Languages::with()` wrapping `switch_to_locale()` on every uncached render (major + minor) | 3.2: `with_lang()` (context only) vs `with_locale()` (emails only); `.l10n.php` shipped |
| 14 | `register_post_meta(show_in_rest)` lets `wp/v2` writes bypass the materialiser (major) and is ineffective for `wc/v3` (minor) | 4.3 guard, 4.7: `show_in_rest => false`; `wc/v3` `gadgetim_pricing` on products and variations as the only REST surface; `updated_post_meta` safety net; daily mismatch check as admin notice |
| 15 | Store API cart call on every page view defeats the page cache (major) | 5.4, 5.9: cookie-gated fetch; "0 server requests per cached anonymous view" budget line |
| 16 | `manage_woocommerce` for redirects/tools; unvalidated redirect targets (major) | D11, 7.1, 5.10: capability split; same-host/allow-list targets; change log; `install_languages` check |
| 17 | wp-admin DB snapshot inside webroot (major) | 7.2: feature dropped; cron `mysqldump` outside webroot is primary; backup-age indicator before rate apply |
| 18 | Public search transients per query and unsigned view beacon (major) | 3.6, 6: no per-query transient, 64-char cap, rate limit, `Cache-Control`; views sampled, deduped, batched, optional; popularity from `total_sales` |
| 19 | Translated slugs collide with native slugs; no uniqueness; no old-slug redirects (major + minor) | 3.4: uniqueness validation across native and translated slugs and WC pages, `-2` suffixing, native-first precedence, `_gtm_slug_{lang}_prev` 301s, conflicts report |
| 20 | Reveal animations hide the LCP; two above-the-fold fonts exceed budget; fallback metrics cannot match Persian text (major) | 5.3, 5.4, 5.9: reveal only below first viewport under `html.js` with CSS fallback; one variable font (Vazirmatn) with tuned fallback metrics, `optional` if CLS fails; Estedad not loaded; budget ≤ 1 file / 140 kB |
| 21 | Plain text replace on the dump breaks serialized data (major) | 6.2: `wp search-replace --precise` on a cloned DB via `tools/wp.ps1`; `.htaccess` regenerated on server |
| 22 | REST detection before `REST_REQUEST` is defined (minor) | 3.2: detection by path/`rest_route`/`wc-ajax` |
| 23 | Admin locale forced to `fa_IR` overrides `user_locale` (minor) | 3.2: locale filters bail in admin |
| 24 | Review form redirect flips language (minor, both) | 3.8: hidden `gtm_lang` field + `comment_post_redirect` |
| 25 | Popular sort drops products without `_gtm_views` (minor) | 6: `_gtm_views = 0` on create/import; `total_sales` primary |
| 26 | `media_handle_sideload` ignores source date folders (minor) | 6.1: `upload_dir` filter per file + in-content URL rewrite |
| 27 | Redirect keys not normalised for Persian/Arabic variants (minor) | 6: normalisation pipeline on both sides; 226-URL check script |
| 28 | WC 11 fresh install uses block cart/checkout and email improvements (minor) | 5.2, M0: shortcodes, feature off, overrides copied from installed 11.2.1 |
| 29 | `home_url('/', 'relative')` handled by accident; pattern rebuilt per call (minor) | 3.2: explicit `relative` branch; static pattern |
| 30 | Sale end for currency products; `wc_apply_sale_state_for_product` re-saves (minor) | 4.3: base sale field is the only way to end a sale; auto-clear after `date_on_sale_to` |
| 31 | Missing sanitisation table; JSON config XSS; WhatsApp/tel URL building (minor) | 5.10 table; `JSON_HEX_TAG`; `rawurlencode`/`esc_url` in 4.6 |
| 32 | Theme fatals without plugin; self-updater risk (minor) | 2.3 compat layer + `Requires Plugins`; 2.5 no self-updater, SHA-256 in release |
| 33 | `view-transition-name` on every card; `mix-blend-mode` jank; `0ms` durations break `transitionend`; `content-visibility` anchors (minor) | 5.1, 5.3, 5.4, 5.5: name only the clicked card; flat plate; `1ms` + `getAnimations()`; restricted `content-visibility` |
| 34 | Filter anchors crawlable; boilerplate titles; ar pack gaps (minor) | 5.4 form-based filters; 5.7 SEO-safe title default; 3.2/3.3 ar gettext overrides on Strings page |
| 35 | LQIP only via Node for existing files (minor) | 5.5, 6: GD-based `Catalog\Lqip` on upload; Node script is audit-only |

Kept unchanged because the reviews confirmed them: classic theme + owned core plugin, URL-prefix i18n via `home_url`, meta-on-same-object translations, materialised Toman pricing, classic checkout + Store API drawer, no-cookie caching rules, own cache adapter, release-zip deployment, brand concept, milestone order.

---

## Appendix A — Hook index (what the plugin touches)

- Routing/i18n: `do_parse_request`, `home_url`, `locale`, `determine_locale` (storefront only), `language_attributes`, `request`, `post_type_link`, `page_link`, `post_link`, `term_link`, `the_title`, `the_content`, `get_the_excerpt`, `single_term_title`, `get_the_archive_title`, `get_the_archive_description`, `wp_setup_nav_menu_item`, `term_links-{taxonomy}`, `woocommerce_get_breadcrumb`, `woocommerce_layered_nav_term_html`, `woocommerce_page_title`, `woocommerce_product_get_name`, `woocommerce_product_get_description`, `woocommerce_product_get_short_description` (storefront render context only), `pre_get_posts` (visibility gate), `woocommerce_related_products`, `woocommerce_product_get_upsell_ids`, `woocommerce_product_get_cross_sell_ids`, `posts_search`, `posts_join`, `wp_sitemaps_posts_query_args`, `wp_sitemaps_taxonomies_query_args`, `wp_sitemaps_post_types`, `robots_txt`, `wp_robots`, `wp_head` (hreflang), `comment_form_default_fields`, `comment_post_redirect`, `preprocess_comment`, `gettext`/`gettext_with_context` (domain `woocommerce`, lang ar), `woocommerce_currency_symbol`, `woocommerce_gateway_title`, `woocommerce_gateway_description`, `woocommerce_shipping_rate_label`, `woocommerce_account_menu_items`, `woocommerce_attribute_label`.
- Orders/emails/gateway: `woocommerce_checkout_create_order`, `woocommerce_store_api_checkout_update_order_meta`, `woocommerce_checkout_create_order_line_item`, `woocommerce_order_item_name`, `woocommerce_order_item_get_name`, `woocommerce_order_item_display_meta_value`, `woocommerce_email_recipient_{id}`, `woocommerce_allow_switching_email_locale`, `woocommerce_email_restore_locale`, `woocommerce_allow_restoring_email_locale`, `woocommerce_before_resend_order_emails`, `woocommerce_email_styles`, `woocommerce_get_checkout_order_received_url`, `woocommerce_get_cancel_order_url`, `woocommerce_get_cancel_order_url_raw`, `woocommerce_get_checkout_url`, `woocommerce_api_{gateway}`, `woocommerce_pre_payment_complete`, `woocommerce_order_status_changed`.
- Pricing: `woocommerce_admin_process_product_object`, `woocommerce_admin_process_variation_object`, `woocommerce_before_product_object_save` (covers variations), `woocommerce_after_product_object_save`, `updated_post_meta`/`added_post_meta`/`deleted_post_meta` (guard), `woocommerce_product_options_general_product_data`, `woocommerce_variation_options_pricing`, `woocommerce_variable_product_bulk_edit_actions`, `woocommerce_get_variation_prices_hash`, `woocommerce_get_price_html` (guarded), `woocommerce_rest_prepare_product_object`, `woocommerce_rest_prepare_product_variation_object`, `woocommerce_rest_insert_product_object`, `woocommerce_rest_insert_product_variation_object`, Action Scheduler `gadgetim_reprice_batch` / `gadgetim_reprice_finish`, `woocommerce_structured_data_product`.
- Contact mode: `woocommerce_is_purchasable`, `woocommerce_add_to_cart_validation`, `template_redirect`, `woocommerce_available_payment_gateways`.
- Theme wiring: `after_setup_theme`, `wp_enqueue_scripts`, `woocommerce_enqueue_styles`, `intermediate_image_sizes_advanced`, `big_image_size_threshold`, `wp_get_attachment_image_attributes`, `wp_generate_attachment_metadata`, `woocommerce_get_catalog_ordering_args`, `woocommerce_checkout_fields`, `woocommerce_checkout_process`, `document_title_parts`, `upload_dir` (importer only).
- Deliberately **not** filtered: `get_term`, `get_terms`, `wp_date`, `date_i18n`, `number_format_i18n`, `wc_price`, `formatted_woocommerce_price`.
- Own actions/filters: `gadgetim_language_detected`, `gadgetim_rates_applied`, `gadgetim_cache_purge_all/post/url`, `gadgetim_cache_purged_all`, `gadgetim_translation_fields`, `gadgetim_home_sections`, `gadgetim_trust_badges`, `gadgetim_price_html`.

## Appendix B — Data shapes

```
Options
  gadgetim_settings      { brand:{...}, contact:{phone, whatsapp, telegram, label:{fa,en,ar}, enabled, show_price}, i18n:{fallback}, seo:{...}, badges:[...], theme:{...}, views:{enabled, sample} }
  gadgetim_languages     { fa:{..., public:true}, en:{..., public:false}, ar:{..., public:false} }
  gadgetim_currencies    { AED:{...}, USD:{...} }
  gadgetim_rate_history  [ {code, old, new, user, time, affected} ]
  gadgetim_home          [ {type:"hero", options:{...}}, ... ]
  gadgetim_redirects     { "<normalised old path>": ["/new/", 301, user, time], "/gone/": ["", 410, ...] }
  gadgetim_strings       { "<md5 of source>": { source, domain, en, ar } }
  gadgetim_prices_version
  transients: gadgetim_slugmap_{lang}, gadgetim_reprice_{job}, gadgetim_rl_{bucket}, gadgetim_view_{hash}

Post meta (product/page)
  _gtm_title_{lang} _gtm_slug_{lang} _gtm_slug_{lang}_prev[] _gtm_excerpt_{lang} _gtm_content_{lang} _gtm_subtitle[_{lang}]
  _gtm_specs[_{lang}] _gtm_faq[_{lang}] _gtm_guarantee[_{lang}] _gtm_seo_title[_{lang}] _gtm_seo_desc[_{lang}]
  _gtm_status_{lang} _gtm_search_{lang}
  _gtm_price_currency _gtm_base_regular_price _gtm_base_sale_price _gtm_rate_used _gtm_priced_at
  _gtm_contact_only _gtm_views _gtm_import_old_id

Attachment meta
  _gtm_lqip _gtm_import_src

Term meta
  _gtm_name_{lang} _gtm_slug_{lang} _gtm_slug_{lang}_prev[] _gtm_desc_{lang} _gtm_status_{lang} _gtm_logo_id _gtm_import_old_id

Order meta
  _gtm_lang

Comment meta
  _gtm_lang
```

## Appendix C — Files read for this document

- `C:/Users/GHADER/AppData/Local/Temp/claude/43e744201fac73d7/bc97a74a-5dff-4675-b112-445c811eb37c/scratchpad/extract/backup-11.4.2026_12-51-56_gadgetim/homedir/public_html/wp-content/plugins/gadgetim-currency-pricing/gadgetim-currency-pricing.php`
- `C:/Users/GHADER/AppData/Local/Temp/claude/43e744201fac73d7/bc97a74a-5dff-4675-b112-445c811eb37c/scratchpad/extract/backup-11.4.2026_12-51-56_gadgetim/homedir/public_html/wp-content/plugins/call-for-price/call-for-price.php`
- `…/plugins/site-custom-payment-gateways/site-custom-payment-gateways.php`, `…/plugins/bitpayir-woocommerce2/index.php` (headers)
- `C:/Users/GHADER/AppData/Local/Temp/claude/43e744201fac73d7/bc97a74a-5dff-4675-b112-445c811eb37c/scratchpad/extract/backup-11.4.2026_12-51-56_gadgetim/mysql/gadgetim_wp_database.sql` (options and counts only)
- `C:/Users/GHADER/Documents/ProjectGit/gadgetim-ir/first-backup/extracted/{products.csv, products.json, categories.json, brands.json, product-tags.json, menus.json, pages-posts.json, old-urls.txt, images/, brand/}`
- `C:/Users/GHADER/Local Sites/gadgetim/app/public/` (version, config, plugins, themes, languages), `C:/Users/GHADER/AppData/Roaming/Local/run/sqs5AIpIj/conf/php/php.ini`
- WooCommerce 11.2.1 source in `C:/Users/GHADER/Local Sites/gadgetim/app/public/wp-content/plugins/woocommerce/`: `includes/abstracts/abstract-wc-data.php`, `includes/abstracts/abstract-wc-product.php`, `includes/class-wc-product-variation.php`, `includes/class-wc-order-item.php`, `includes/admin/meta-boxes/class-wc-meta-box-product-data.php`, `includes/admin/meta-boxes/class-wc-meta-box-order-actions.php`, `includes/emails/class-wc-email.php`, `includes/class-wc-ajax.php`, `includes/wc-product-functions.php`, `includes/class-wc-install.php`, `includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php`
