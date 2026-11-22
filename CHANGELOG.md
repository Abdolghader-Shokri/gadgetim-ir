# Changelog

Every delivery of the plugin and the theme to the live site is a numbered release (owner, 2026-11-18). Both packages carry the same number:
- Z for fixes only;
- Y for new features or visible changes;
- X for a breaking change.

The release steps are in the README, under "Release". The newest release comes first.

## 1.4.1 — 2026-11-22

The result of an update package can no longer be missed.
- The owner applied the 1.4.0 package the same evening. Live shows the theme installed at 18:37:44 UTC and the content imported at 18:37:48 UTC: the whole update ran in about four seconds. Still, no message was seen. The one-time notices were used up by a page load nobody looked at, most likely the browser preloading the dashboard address while it was typed.
- The dashboard home has a «بسته‌های گجتیم» box at the top. It lists the last five results with Jalali date and time, state (done / done with warnings / not applied) and their lines, whether or not their notice was seen, plus what waits in the drop folder.
- Browser preloads (`Sec-Purpose`/`Purpose` prefetch or prerender, `X-Moz`, `X-Purpose: preview`) neither apply packages nor import content, and do not mark notices seen. Neither does the update's own fatal-error check of the dashboard.
- An info notice says when a package is being applied right now ("refresh in a few seconds") or is waiting.

## 1.4.0 — 2026-11-22

One zip for every update (owner, 2026-11-22: put one file on the host, open the dashboard once, and everything is done).
- `tools/build-update-bundle.php X.Y.Z [drops…]` packs the release zips of the plugin and the theme and the day's drop packages into `gadgetim-update-X.Y.Z-<date>.zip`, with `gadgetim-update.json` listing the SHA-256 and version of every file. It refuses a missing tag, a zip whose header or `.sha256` does not match, and a drop package without a version 1 `manifest.json`, and reads the finished zip back.
- `Import\Bundle` applies it from `wp-content/gadgetim-drop/` on a dashboard page load (GET) of an administrator:
  - every file is checked against its SHA-256 and the plugin and theme against their own Version headers;
  - an older version refuses the whole package, the same version is skipped, a package already applied is not applied twice;
  - a newer plugin or theme is installed with WordPress's own upgraders, as "Replace current with uploaded" does, and the old copy waits in `upgrade-temp-backup/`;
  - the server then loads the home page and the dashboard with WordPress's fatal-error check; on a fatal error both old copies are moved back and the package goes to `failed/`;
  - the drop packages are imported on the next page load, which the browser reaches by itself and which runs the new code; the dashboard notice lists the plugin, the theme and each content package.
- Never from WP-Cron, by a shop manager, during a POST or on the update screens, and never over a plugin or theme folder that is a link (Local's junctions to this repository: PHP's `is_link()` misses them, the real path does not).
- A package that the plugin before 1.4.0 moved into `failed/` ("manifest.json is missing") is taken back once, and that version's red notice is hidden.
- The drop notice lists results oldest first, so an update package comes before the content it brought.

Tested on a copy of the Local site with its own database and real (not linked) plugin and theme folders: the owner's flow through the dashboard (theme 1.3.6 to 1.4.0 and today's content: 3 pages and 11 texts), a newer plugin and theme, a plugin that throws on load (put back automatically, site kept answering), an older package, a damaged one, the same package twice, a package in `failed/`, a blocked self-check (update kept, warning shown) and a linked theme folder (refused).

## 1.3.6 — 2026-11-22

The air-pump category answers «پمپ باد شارژی».
- Its title, description and heading read «پمپ باد شارژی» (`Seo\Meta` TERM_NAMES and HEADING_NAMES): «قیمت و خرید پمپ باد شارژی اصل» and H1 «پمپ باد شارژی». Both products are cordless pumps with an internal battery, and for «پمپ باد شارژی» four of Google's first six results on 2026-11-22 were category pages, so the keyword moved from the buying guide to this category; the guide keeps the comparison phrases («بهترین پمپ باد شارژی»).

1.3.0 to 1.3.5 were built the same day and never uploaded; 1.3.6 contains them all.

## 1.3.5 — 2026-11-22

Persian names for five more brands, and sharp product photos in guides on phones.
- `Seo\Meta::BRAND_LOCAL_NAMES` gives Anker «انکر», JBL «جی بی ال», SanDisk «سن دیسک», Philips «فیلیپس» and ENZO «انزو», spelt as in the keyword bank of 2026-11-22 («انکر» alone is in 114 searched phrases). Their brand pages read «محصولات اصل انکر ANKER: قیمت و خرید» and «محصولات انکر ANKER» instead of the Latin name alone.
- Photos placed in page bodies by URL (the guides' product photos) get their attachment's `wp-image-{id}` class from the file path (`gadgetim_pages_content_image_ids()`), so WordPress adds srcset and sizes. Before, the guides showed the 400 px file at 360 px with no larger source for high-density screens, because the drop importer's wp_kses_post drops srcset.

1.3.0 to 1.3.4 were built the same day and never uploaded; 1.3.5 contains them all.

## 1.3.4 — 2026-11-22

Guides print model numbers as typed and give product photos a caption.
- Page bodies (the guides and other pages) convert digits the way product pages do (`Digits::for_lang()`): a token with Latin letters and a bare number next to a Latin word keep their digits. Before, every digit run became Persian, so the guides' headings, tables and captions read «PDGA۰۵BK» and «Liberty ۴ Pro», which neither reads right nor matches a search for the model. Plain numbers, phone numbers and dates stay Persian.
- Product photos in guides carry the product name as a caption (`.pg__body figcaption`: small, muted, centred under the photo).

1.3.0 to 1.3.3 were built the same day and never uploaded; 1.3.4 contains them all.

## 1.3.3 — 2026-11-22

Brand and category pages list the buying guides that cover their products. A rank check on 2026-11-22 found that the rival pages for «گرین لاین ساخت کجاست» carry a related-articles block and ours had none, and the three guides published that day had links only from the home page, the other guides and the product pages.
- Page 1 of a brand or product-category archive shows «راهنماهای خرید مرتبط» above its description: up to six guides that link at least one of its products, the guide most about the brand or category first (`gadgetim_guides_for_term()`, `templates/archive/guides.php`). Guides are Persian only, so /en/ and /ar/ show nothing; a term no guide covers shows nothing.

1.3.0 to 1.3.2 were built the same day and never uploaded; 1.3.3 contains them all.

## 1.3.2 — 2026-11-22

Brand pages carry the Persian names buyers search. A rank check on 2026-11-22 found the brand pages' heading was the Latin name alone, LePresso's title had no Persian name («محصولات اصل LEPRESSO») and Powerology's used the old site's spelling «پاورلوژی», while buyers search «لپرسو» and «پاورولوژی».
- `Seo\Meta::BRAND_LOCAL_NAMES` gives LePresso «لپرسو» and Powerology «پاورولوژی»; they win over the import aliases in titles and descriptions.
- On Persian brand pages the heading reads «محصولات <Persian name> <brand>» (`Seo\Meta::brand_heading_lead()`), e.g. «محصولات گرین لاین Green Lion»; English and Arabic headings stay the brand name.

1.3.0 and 1.3.1 were built the same day and never uploaded; 1.3.2 contains both.

## 1.3.1 — 2026-11-22

The old theme's filter URLs can now leave Google's index. Its brand filter wrote every combination as a URL (`/shop/?orderby=price-desc&dina_brand=218,298,350…`); a `site:` search on 2026-11-22 showed about 133,000 results for the domain, with these URLs and the old titles among the first. robots.txt kept them out of the crawl because they start with `?orderby=`, so Google could never see that they are gone.
- A request with a parameter of the old theme (`dina_…`) gets a 301 to the same path without a query string (`Catalog\Redirects::drop_legacy_params()`).
- robots.txt allows `/*?dina_brand=` and `/*&dina_brand=`. Each rule is longer than any parameter rule such a URL also matches, so crawlers apply it and see the 301; the new site's own sort and filter URLs stay blocked.

1.3.0 was built the same day and never uploaded; 1.3.1 contains it.

## 1.3.0 — 2026-11-22

Product pages link to the buying guides that feature them. Under the product body, before «we also recommend», a product page now shows the guide cards of every buying guide whose text links to that product («راهنماهایی که این کالا را معرفی کرده‌اند»). The guides get links from the product pages they send buyers to, and a buyer who lands on a product from search finds the guide that compares it. Nothing prints when no guide links to the product, or on /en/ and /ar/ until a guide is translated (`gadgetim_guides_for_product()`, `gadgetim_pdp_guides()`; the guide cards part takes a ready list through `items`).

## 1.2.2 — 2026-11-20

The buying-guide cards show every guide again. They stopped at six, so with the Father's Day and Yalda guides of the same evening (eight guides) the home page left two out, and the last guide in the order was linked from no card at all. The cards now list up to twelve guides (`templates/pages/guides.php`).

1.2.0 and 1.2.1 were built the same evening and never uploaded; 1.2.2 contains both.

## 1.2.1 — 2026-11-20

Fixes from the deep checks of the SEO run of 2026-11-20:
- Product markup keeps a ListPrice only where the page strikes the old price through (from the strike threshold up). A product a few percent off showed one price on the page and an old price in the markup, which Google expects to match (SCH.3-03; `Seo\JsonLd::list_price_as_shown()`, test `tests/Pricing/ListPriceSchemaTest.php`).
- The green WhatsApp and success buttons use a darker green, so white text reaches 4.5:1 (it was 2.5 to 3.7; DES.3-02).
- Buying guides name their author under the title, «نوشتهٔ تیم گجتیم», as their Article markup already did (CNT.2-09).
- The old site's `/order-tracking/` goes to the public order-tracking form instead of the account's order list, which asked guests to log in (TEC.6-03).

1.2.0 was built the same evening and never uploaded; 1.2.1 contains it.

## 1.2.0 — 2026-11-20

The guide pages are marked up as articles (#64). Three of the five page-one results for «راهنمای خرید اسپیکر بلوتوثی» and «شارژر فست چیست» are, and a buying guide is one: the page node of a page with the «Buying guide» template is now an Article with headline, publication and update dates, the shop as author and publisher, and the featured image.

1.1.0 was built the same evening and never uploaded; 1.2.0 contains it.

## 1.1.0 — 2026-11-20

New features for the buying-guide pages of the SEO run of 2026-11-20, and the Search Console proof the move had lost (#63):
- The old site's Google Search Console verification file `/google12fac158692d4dbf.html` answers again. The move on 2026-11-07 copied only WordPress, so it had answered 404 since then; the plugin now serves it itself (`Seo\Verification`).
- Pages get FAQPage data for their own «پرسش‌های متداول» section, like products and category texts (the FAQ page keeps its single FAQPage node).
- A «Buying guide» page template: the usual page body with a guide hero, then cards for the other guides.
- A buying-guides section on the home page, between the featured products and the brands, listing every published guide (Persian home only).

## 1.0.0 — 2026-11-18

First numbered release. The code is the build the owner uploaded on 2026-11-18 (main c1df399). The live site showed that build as plugin 0.1.0 and theme 1.0.0-dev. Only the two version headers and the release tooling changed.

That build brought these pull requests since the previous upload:
- #56: FAQPage data for the FAQ sections of products and category or brand texts, and three category names taken from what buyers search.
- #57: character references such as `&#8211;` keep their digits in page bodies.
- #58: old brand URLs (`/brand/…`, `/en/brand/…`, `/ar/العلامة التجارية/…`) answer with a 301 to the brand archive under the current base.
- #59: big images drawn small on phones keep their smaller srcset candidates.
- #60: a WhatsApp button above the bottom nav on phones.
- #61: a page replaced by a drop package keeps its previous texts (a revision, and `_gtm_drop_prev_translations`), and old brand page numbers land on page one.

Also new: `tools/bump-version.sh` sets the number in both packages.

Deliveries from 2026-11-07 to 11-18 were untagged builds and have no number.
