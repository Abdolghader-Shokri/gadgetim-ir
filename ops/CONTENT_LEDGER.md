# Content ledger — gadgetim.ir

The reference for every daily SEO run (daily-seo-operations skill, started 2026-11-26). It records each keyword
worked, its search intent, the URL that answers it and its status, so no two pages compete for the same query.
Before creating a page, search this file and the site's page list; if a page already answers the intent, improve it.

- Site: https://gadgetim.ir (WordPress + WooCommerce, theme `gadgetim`, plugin `gadgetim-core`). Local copy:
  http://gadgetim.local. Languages: fa (main), en (`/en/`), ar (`/ar/`). Guides are Persian only.
- Delivery: the owner uploads one `gadgetim-update-X.Y.Z-<date>.zip` into `wp-content/gadgetim-drop/`; content
  travels as drop packages inside it (`tools/build-content-drop.php`, `tools/build-product-drop.php`).
- Status values: `new` (prepared on Local, not uploaded), `improve` (an existing URL improved, not uploaded),
  `published` (live), `planned`, `rejected` (with the reason).
- Search volume: no Search Console export and no keyword tool are available yet (owner need #5). Demand signals
  are Google's live autocomplete and the live results page, never a volume figure.
- Older keyword history (5,815 autocomplete phrases with intent bands and target pages, rank checks since
  2026-11-16) lives in the SEO engineer state: `~/.seo-engineer/sites/gadgetim.ir/data/keywords/bank.json` and
  `data/ranks/history.json`.

## Pages that answer a query (newest first)

| Keyword (main) | Intent | URL | Type | Date | Status | Notes |
|---|---|---|---|---|---|---|
| تشخیص شارژر اصل سامسونگ | informational | /تشخیص-شارژر-اصل-سامسونگ/ | guide (Local #6518; live ID once imported) | 2026-11-26 | new | spoke of /تشخیص-لوازم-جانبی-اصل/; Samsung 25 W and 45 W only (EP-T2510, EP-T4511), cable, serial, «ساخت ویتنام» |
| تبدیل سه به دو / تبدیل ۳ به ۲ | transactional | /product-category/تبدیل-3-به-2/ | category text | 2026-11-26 | improve | the text was empty; a guide would miss the intent |
| شارژر ۴۵ وات سامسونگ | transactional | /product/شارژر-45-وات-سامسونگ-ep-t4511-با-کابل/ | product (mahsool 22) | 2026-11-26 | improve | internal links only; the out-of-stock duplicate #675 gets no contextual links |
| کتری برقی دماسنج دار | transactional | /product/کتری-برقی-کلاسیک-گرین-لاین-green-lion-gl-ek3/ | product | 2026-11-26 | improve | internal links only (category «گجت‌های خانگی», Yalda guide); the thermometer only shows the temperature |
| جت فن شارژی چیست | informational | /جت-فن-شارژی-چیست/ | guide (live #6425) | 2026-11-22 | published | |
| پمپ باد شارژی یا فندکی؛ راهنمای خرید پمپ باد | informational / comparison | /راهنمای-خرید-پمپ-باد/ | guide (live #6423) | 2026-11-22 | published | «پمپ باد شارژی» itself is the category /product-category/پمپ-باد/ |
| کادو تولد | commercial (gift ideas) | /کادو-تولد/ | guide (live #6421) | 2026-11-22 | published | |
| هدیه شب یلدا | commercial (gift ideas) | /هدیه-شب-یلدا/ | guide (live #6417) | 2026-11-20 | published | |
| هدیه روز پدر | commercial (gift ideas) | /هدیه-روز-پدر/ | guide (live #6415) | 2026-11-20 | published | |
| تشخیص لوازم جانبی اصل | informational | /تشخیص-لوازم-جانبی-اصل/ | guide (live #6413) | 2026-11-20 | published | general hub: power bank, charger, cable, earbuds, flash |
| شارژر فست چیست | informational | /شارژر-فست-چیست/ | guide (live #6411) | 2026-11-20 | published | fast-charging standards; not authenticity |
| راهنمای خرید پاوربانک | commercial investigation | /راهنمای-خرید-پاوربانک/ | guide (live #6409) | 2026-11-20 | published | |
| راهنمای خرید اسپیکر بلوتوثی | commercial investigation | /راهنمای-خرید-اسپیکر-بلوتوثی/ | guide (live #6407) | 2026-11-20 | published | |
| راهنمای خرید هندزفری بلوتوثی | commercial investigation | /راهنمای-خرید-هندزفری-بلوتوثی/ | guide (live #6405) | 2026-11-20 | published | |
| هدیه روز مادر | commercial (gift ideas) | /هدیه-روز-مادر/ | guide (live #6403) | 2026-11-20 | published | |

Brand and category introductions written by the SEO engineer (2026-11-22, live since the 1.4.0 upload): brands
green-lion, porodo, lepresso, powerology; categories پمپ باد، گجت‌های موبایل، لوازم جانبی کامپیوتر، گجت‌های صوتی و
تصویری، کالای دیجیتال و برقی.

Products are listed through the product-listing skill (mahsolat/ → products package); each product page answers its
own model query. Batches: 2026-11-11 to 2026-11-25 (mahsool 2–23).

## Daily runs

Each run lists the keywords chosen (at most five), the decision and the evidence. Newest first.

### 2026-11-26 (first daily-seo-operations run)

Inputs:
- No Search Console export (owner need #5 open since 11-12). The last rank check (11-22) found #1 only for «گجتیم».
- Intent therefore comes from the live Google results page (hl=fa, gl=ir), read today, and Google's live autocomplete. No search volume is available, and none is estimated.

| Keyword | Intent (Google, 11-26) | Decision | URL | Status |
|---|---|---|---|---|
| تشخیص شارژر اصل سامسونگ (+ ۴۵ وات، ۲۵ وات، سری، کلگی) | informational: articles and videos only; dedicated 45W articles rank | **new** page. The general guide /تشخیص-لوازم-جانبی-اصل/ keeps its short charger section and links here (hub → spoke). | /تشخیص-شارژر-اصل-سامسونگ/ | new |
| تبدیل سه به دو / تبدیل ۳ به ۲ (+ آمپر بالا، شارژر سامسونگ، برای سرخ کن، تایمر دار) | transactional: category and product pages | **improve** the category, which had no text; a guide would miss the intent | /product-category/تبدیل-3-به-2/ | improve |
| شارژر ۴۵ وات سامسونگ (+ مناسب چه گوشی‌هایی است، t4511) | transactional: product pages | **improve** through links to the in-stock product. The two old links to the out-of-stock duplicate #675 now point to it. | /product/شارژر-45-وات-سامسونگ-ep-t4511-با-کابل/ | improve |
| کتری برقی دماسنج دار | transactional: product and category pages | **improve** through links: the home gadgets category text (it had none) and the Yalda guide | /product/کتری-برقی-کلاسیک-گرین-لاین-green-lion-gl-ek3/ | improve |

Also improved, without a new keyword:
- /هدیه-روز-مادر/: the sleep mask, before Mother's Day on 11-30, and a link to /کادو-تولد/ (it had no contextual link).
- The home gadgets category text: /product-category/گجت-های-خانگی/ (it was empty).
- Categories شارژر, کابل شارژ, گجت‌های موبایل and لوازم جانبی موبایل:
  - each now names the copy charger (mahsool 23) as the one exception to "all original";
  - the two links to #675 now go to mahsool 22;
  - the 45 W cable sentences in شارژر and کابل شارژ now carry Samsung India's exception: Galaxy S25 and later get SFC 2.0 with the bundled 3 A cable.
- Brand samsung: a «محصولات سامسونگ در گجتیم» section with links.
- /شارژر-فست-چیست/: its closing «همهٔ شارژرها و کابل‌های گجتیم اصل‌اند» now names the copy charger as the exception.
- Owner's choice on 2026-11-27 («همین‌طور بماند»): the closing «همهٔ کالاهای گجتیم اصل و اورجینال‌اند» stays as it is in /هدیه-روز-مادر/, /هدیه-شب-یلدا/ and /تشخیص-لوازم-جانبی-اصل/, like the site-wide lines kept on 11-25. Do not raise it again unless the owner asks.

Delivery: `release/deploy/gadgetim-update-1.5.0-2026-11-26.zip`. It carries `content-2026-11-26.zip`:
- 1 new page;
- 4 pages replaced: live 6411, 6413, 6417 and 6403;
- 5 term texts replaced: 66, 67, 96, 68 and brand 140;
- 2 term texts filled: 83 and 90;
- Persian only: no en/ar fields.

Report: `ops/DAILY_REPORT_2026-11-26.md`. Status changes to `published` once the owner confirms the upload.

Not done today, for the next runs:
- product titles over 60 characters (289 tags);
- 3 brand gaps in product markup;
- 60 en/ar meta descriptions out of range;
- 22 products without a contextual link (flash, memory cards, OTG, belt silencers and others).

Products can only change through the products package, which overwrites every field and resets stock, so the first two wait for a safe "fields only" import in the plugin.

Source of truth: the Local database. These guides were first published from `page.cjs` files in the scratchpad (seo1120/seo1122). From 11-26 their texts were edited in the database, so never republish them from those old files:
- /شارژر-فست-چیست/
- /تشخیص-لوازم-جانبی-اصل/
- /هدیه-شب-یلدا/
- /هدیه-روز-مادر/
