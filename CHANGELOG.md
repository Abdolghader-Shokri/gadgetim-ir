# Changelog

Every delivery of the plugin and the theme to the live site is a numbered release (owner, 2026-11-18). Both packages carry the same number:
- Z for fixes only;
- Y for new features or visible changes;
- X for a breaking change.

The release steps are in the README, under "Release". The newest release comes first.

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
