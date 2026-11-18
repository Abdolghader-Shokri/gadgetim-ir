# Changelog

Every delivery of the plugin and the theme to the live site is a numbered release (owner, 2026-11-18). Both packages carry the same number:
- Z for fixes only;
- Y for new features or visible changes;
- X for a breaking change.

The release steps are in the README, under "Release". The newest release comes first.

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
