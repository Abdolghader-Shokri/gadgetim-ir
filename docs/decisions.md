# Binding decisions — where the design spec and the architecture differ

> **Update 2026-11-04 (evening): the «تیمچه» (Timcheh) design was rejected by the client** after seeing it on Local
> («زیبایی نداره، قالب قبلی خیلی قشنگ‌تر بود … خیلی گرافیکی با انیمیشن و افکت‌های زیبا، کاربرپسند و ریسپانسیو … تم تاریک رو اصلاً نمی‌پسندم. از اول بساز»).
> Consequences: `docs/design-spec.md` is **superseded** for everything visual; the theme is rebuilt from scratch on a new,
> light-only, colourful, graphic, animated marketplace direction chosen by the client from prototypes (`/_proto/` on Local).
> **No dark mode at all** (no toggle, no `prefers-color-scheme` dark tokens). The core plugin (`gadgetim-core`), the
> architecture, the pricing/i18n/checkout decisions and everything in "Settled by the client" below still stand.
>
> **2026-11-05: the client chose the Aurora «شفق» prototype** (of three: Aurora, Fresh Market, Candy Tech). Its source
> is kept in `docs/prototypes/aurora/` and is the visual reference for the rebuilt `gadgetim` theme: white/very light
> page, aurora gradient system (indigo #4F46E5 → violet #7C3AED → fuchsia #C026D3 → pink #DB2777, amber highlight),
> glass cards, story rings, animated hero slider + «پیشنهاد لحظه‌ای», «پیشنهاد شگفت‌انگیز» band, gradient promo
> banners, ranked best-sellers, category bento, brand marquee, rich footer, mobile bottom nav. Fonts: Vazirmatn VF
> (text) + Estedad VF (display). Light only. Countdowns must reflect a real deadline (sale end date or an
> admin-set campaign end), never a fake timer.

Date: 2026-11-04. `docs/design-spec.md` (Timcheh v2) and `docs/architecture.md` (v0.2) were written in parallel. Where they disagree, **this file wins**. Everything else in both documents stands.

| Topic | Design spec says | Architecture says | Decision |
|---|---|---|---|
| Brand mark | «مهر و کشیده»: ticks from the strokes of گ, saffron; 12-scallop ink seal; Estedad-based wordmark | "Charge dots", blue accent `#2B5BFF` | **Design spec.** The architecture's §5.1 brand/palette block is superseded. No blue in the identity (the old favicon was blue). |
| Colour tokens | Timcheh palette (paper, ink, pomegranate, saffron, lapis, pistachio…) | `--charge`, `--volt`, `--trust` | **Design spec §3 tokens**, prefix `--gtm-`. |
| Fonts (fa/ar) | Vazirmatn VF + Estedad 700 + 800 (≈ 200 KB) | Vazirmatn VF only (≤ 140 KB) | **Compromise:** Vazirmatn VF (text, UI, H3 and below) + **one** Estedad static weight (800, Arabic subset, ≈ 55 KB) for the hero/section/PDP headlines. Target ≤ 160 KB on fa first view; Estedad is preloaded only on pages whose LCP is a headline (home, category); elsewhere it is lazy. If Lighthouse CLS > 0.05 after metric tuning, drop to `font-display: optional` for Estedad first. |
| Fonts (en) | Bricolage Grotesque + Instrument Sans | Inter | Phase 2 decision; default plan Bricolage (display) + Inter (text). |
| Packshot blend | per-image `_gtm_packshot` flag enables `mix-blend-mode: multiply` | no blend (scroll jank) | **No `mix-blend-mode` in Phase 1.** Plates are `#FBF8F3`, so pure-white packshots show no visible rectangle anyway. Keep the `_gtm_packshot` flag (edge sampling on upload) for later use. |
| Multilingual data model | §8 last bullet: one post per language linked by a translation group | meta on the same object (D2, reviewed) | **Architecture.** One product, one stock, one price; translations are `_gtm_*_{lang}` meta; visibility gate `_gtm_status_{lang}`. The design spec's §8 bullet is wrong. |
| Pricing meta/option names | `_gtm_currency`, `_gtm_base_regular`, `gtm_rates`, `gtm_rounding` | `_gtm_price_currency`, `_gtm_base_regular_price`, `_gtm_base_sale_price`, `gadgetim_currencies` (per-currency step/mode) | **Architecture names.** `Calculator` supports `nearest|up|down|charm`. **Client decision 2026-11-04: default is `nearest` with step 10,000 Toman** (30 AED × 78,000 = 2,340,000). `charm` stays available as an option. |
| Cart badge | plugin cookie `gtm_cart_count` | Store API GET only when a cart cookie exists | **Both:** the cookie feeds the badge with zero requests; the Store API is used only when the drawer opens. |
| Theme folder | `timcheh/` | `gadgetim/` | **`wp-content/themes/gadgetim`** (Theme Name "Gadgetim — Timcheh"). Plugin `wp-content/plugins/gadgetim-core`. |
| Local DB engine | — | switch Local to MariaDB in M0 | **Deferred.** Keep MySQL 8.4 for now; the data-move rehearsal (M3) will use a MariaDB site. Explicit `utf8mb4` charset/collation set in Local wp-config now. |
| Home sections | 7 core + 5 conditional | `gadgetim_home` options array | Both: the 7 core sections are fixed templates with options; conditional sections appear when data exists. No drag-and-drop builder. |
| View transitions | cross-document page turn | name only the clicked card image | Both: the page turn ships, but `view-transition-name` is set only on the clicked card image (`pageswap`) and the PDP plate (`pagereveal`). |
| Reveal animations | reveal at 15 % visibility | never hide the first viewport | Both: nothing in the first viewport starts hidden; reveals only below the fold and only under `html.js`, with the 2 s CSS fallback. |
| Prices in JSON-LD | `priceCurrency: "IRR"`, price × 10 | same, "to confirm" | Use `IRR` × 10 (Toman is not an ISO code). |

## Settled by the client on 2026-11-04
- Design direction: **Timcheh** (the editorial bazaar), as the panel recommended.
- Rounding: computed Toman prices round to the **nearest 10,000 Toman** (not charm rounding).
- Product photos: almost all 409 carry the old «گجتیم GADGETIM.IR» watermark and a faint GT ghost; **proceed with them for now**; a clean-up pass (AI inpainting with per-image review) or original files can replace them later. Until then the hero collage/story spreads should prefer the few clean images or crops that hide the bottom-right band.
- Downloads approved: Vazirmatn and Estedad fonts (OFL, GitHub releases) into the theme; wp-cli.phar into `tools/wp-cli/` for this machine only.
- Rebuild the currency pricing as multi-currency in `gadgetim-core` and migrate the 107 base prices; the old unit is **AED** (rate 78,000 Toman at backup time — confirm the current rate before launch).
- Call-for-price mode ships as an admin toggle, **off** by default; the old phone number is migrated.
- A **new** logo/wordmark is designed with the theme (design spec §2).
- The old `site-custom-payment-gateways` plugin is not ported as code: `cod`/`bacs` simply stay disabled (a per-gateway toggle exists in `Checkout\Gateways`).
- Only WooCommerce, one page-cache plugin and one Iranian payment gateway are installed from outside. Everything else is ours.

## Order of work
Theme and core plugin first (M0–M2 on Local with the real catalogue imported as development data), then links/redirects, production migration and pages (M3–launch). The client's sequencing ("theme first, then links, products, pages") applies to production content.
