# Gadgetim storefront — final design specification
## «تیمچه» Timcheh v2 — the editorial bazaar, after surgery

Version 1.0 · 2026-11-04 · Design lead synthesis of the panel winner (Timcheh, 74.8) with both judge reports resolved and grafts from Vitrine, Shabtab and KASHI.

---

## خلاصه فارسی

**جهت طراحی.** قالب جدید گجتیم «تیمچه» است: یک مجلهٔ فارسی که اتفاقاً گجت می‌فروشد. سطح‌های کاغذی گرم، تایپوگرافی سنگین فارسی، برچسب‌های زعفرانی، و یک «مهر اصالت» که روی هر کالای اصل می‌خورد. هیچ اثری از قالب قبلی (دیناکالا، رنگ فیروزه‌ای، کارت‌های یک‌شکل، المنتور) باقی نمی‌ماند. میراث ایرانی فقط از راه تایپوگرافی، نام رنگ‌ها (انار، زعفران، لاجورد، گچ، مرکب) و رفتار «کاغذی» حرکت‌ها منتقل می‌شود؛ نه با نقش اسلیمی، کاشی یا گرادیان.

**چه چیزهایی از نسخهٔ اول عوض شد (نظر داورها اعمال شد).**
- عکس‌های محصول: ترکیب multiply دیگر پیش‌فرض نیست؛ هر عکس روی یک «صفحهٔ ویترین» روشن می‌نشیند و multiply فقط برای عکس‌هایی فعال می‌شود که پس‌زمینهٔ سفید خالص دارند (پرچم خودکار + کنترل دستی). پاک کردن واترمارک قدیمی از ۴۰۹ عکس، **پیش‌نیاز انتشار** است، نه ریسک.
- برچسب تخفیف فقط برای تخفیف ۱۵٪ به بالا (قابل تنظیم) نمایش داده می‌شود؛ وگرنه با ۱۰۲ محصول «حراج‌شده» از ۱۰۷ تا، کل سایت برچسب می‌خورد و بی‌اعتماد می‌شود.
- صفحهٔ اصلی از ۱۲ بخش به ۷ بخش اصلی رسید؛ ۵ بخش دیگر فقط وقتی محتوایشان وجود داشته باشد ظاهر می‌شوند.
- واژگان بازاری («راسته»، «پیشخوان») فقط در تیترهای کوچک تزئینی می‌ماند؛ منو، نوار پایین موبایل، بردکرامب و جست‌وجو از کلمات معمولی («دسته‌بندی»، «محصولات»، «پربازدیدها») استفاده می‌کنند.
- مهر اصالت دیگر «دایرهٔ آبی با یک حرف» نیست (که شبیه فاویکون قدیمی بود). مهر جدید یک مهر لاستیکی مرکبی ۱۲‌دندانه با دو «تیک» زعفرانی است که از دو خط بالای حرف «گ» گرفته شده‌اند.
- گرد کردن قیمت: پیش‌فرض «قیمت روانی» است (…۹٬۰۰۰) چون ۹۶ قیمت از ۱۰۲ قیمت فروش فعلی همین‌طورند؛ گرد کردن به ۱۰٬۰۰۰ تومان قیمت ۹۹۹٬۰۰۰ را ۱٬۰۰۰٬۰۰۰ می‌کرد.
- سبد، تسویه حساب، ورود و پیگیری سفارش طراحی شدند (فرم تک‌ستونه با موبایل به‌عنوان شناسهٔ اصلی، استان/شهر، کد پستی ۱۰ رقمی، پیام انتقال به درگاه).
- شمارندهٔ سبد از کوکی اختصاصی افزونهٔ ما خوانده می‌شود، چون کوکی ووکامرس تعداد را ندارد.
- نرخ ارز که عوض شود، قیمت تومانی در `_price` ووکامرس و جدول lookup بازنویسی می‌شود تا مرتب‌سازی و فیلتر قیمت درست بماند.
- حالت شب پیش‌فرض نیست؛ پیش‌فرض «روز» است و کاربر می‌تواند خودکار/روز/شب را انتخاب کند.

**افزونه‌ها.** فقط ووکامرس، افزونهٔ کش، درگاه پرداخت ایرانی و افزونهٔ هستهٔ اختصاصی خودمان (`gadgetim-core`). سه‌زبانه بودن، چندارزی بودن، حالت «تماس برای قیمت»، جست‌وجوی زنده، اعلان موجودی و خبرنامه همه در همین افزونهٔ هسته هستند؛ Polylang/WPML، المنتور، اسلایدر و آیکون‌پک نصب نمی‌شود.

**مراحل.** فاز ۱: نمونهٔ ایستای صفحهٔ اصلی + صفحهٔ محصول با فونت و رنگ واقعی و سه محصول واقعی برای تأیید شما؛ بعد قالب فارسی کامل + افزونهٔ هسته. فاز ۲: انگلیسی و عربی (زیرساختش از فاز ۱ ساخته می‌شود، محتوایش به مترجم انسانی نیاز دارد). پرسش‌های باز برای شما در بخش ۱۲ آمده است (مهم‌ترین‌ها: فایل‌های بدون واترمارک عکس‌ها، ورود با پیامک، متن ضمانت و بازگشت، لوگوی برندها).

---

## 1. Direction & principles

### 1.1 The direction in one paragraph
Timcheh is a covered bazaar hall read like a well-made Persian magazine. Warm paper surfaces, heavy Persian display type, saffron paper stickers, pomegranate for the one thing you can do (buy), lapis for the one thing you can follow (links), and an ink rubber-stamp "seal of authenticity" that the whole brand is built on. Products sit on lit "vitrine" plates so the existing white packshots look intentional. Motion behaves like paper: it turns, slaps and stamps; nothing glows, floats or loops. The shop is Persian-first and RTL-first; English and Arabic are full translations of the same system, not mirrored afterthoughts.

### 1.2 Principles (binding for every component)
1. **Products are the brightest thing on the page.** Surfaces are paper and plaster; only the plates are near-white; colour is reserved for actions (pomegranate), links/focus (lapis), stickers (saffron) and status (pistachio).
2. **Trust is an object, not a slogan.** The seal appears on every card, under every buy box, on the thank-you page and the invoice. The phrase «اصل و اورجینال با ضمانت اصالت کالا» is spoken once per screen by the seal, never by the product title.
3. **Bazaar in the eyebrows, plain nouns in the chrome.** Section eyebrows and copy may say «راسته», «پیشخوان», «حرف بازار». Navigation, the app bar, breadcrumbs, search groups, filters and buttons say «دسته‌بندی», «محصولات», «پربازدیدها», «سبد خرید».
4. **Honest numbers.** No fake issue numbers, no invented order counts, no countdowns, no "best-sellers" until there are sales. Prices never animate.
5. **Paper motion, compositor only.** Transform and opacity; clip-path for at most two large reveals per page; one hero-scale animation per viewport; every animation has a reduced-motion twin.
6. **Thumb first.** Primary actions in the bottom 40 % of a phone viewport; 44 px targets; no hover-only information.
7. **Cache-safe by construction.** Full-page cache serves identical HTML to everyone; every personal or volatile piece (cart count, currency hint, wishlist, theme, recent searches) is client-side or cookie-driven.
8. **Progressive enhancement.** View Transitions, scroll-driven animations, `linear()` easings and `backdrop-filter` are enhancements behind `@supports`; every template works and looks finished without them and without JavaScript.

### 1.3 What was fixed from the panel version (judge findings → decisions)
| Finding | Decision |
|---|---|
| Multiply blend magnifies the baked-in watermark and tints white products | Plates are the default; multiply is a per-image opt-in flag (`_gtm_packshot`, auto-detected by edge sampling, manual override). Watermark clean-up is a launch blocker (§9). |
| 102/107 products "on sale" → sticker wallpaper | Discount sticker only at ≥ 15 % (admin threshold); struck price only at ≥ 10 %; one sticker per card; «حراج» section picks top-8 by percentage, in stock (§5.6). |
| 12-section home, 5 sections without day-one content | 7 core sections; 5 conditional sections render only when their content exists (§6.1). |
| Bazaar vocabulary in nav | Principle 3. |
| Seal = blue ring with a letter = old favicon | New seal: 12-scallop ink stamp with two saffron ticks; no circle, no letter, no blue (§2). |
| Dark-mode light plates = glare on grids | Light is default; dark is opt-in; dark plates are #E8E2D8 (not #F3EDE3) at 12 px inset; PDP main plate stays brighter (§3, §5.4). |
| Kashida hover inside a snap rail shifts layout | Kashida gesture restricted to the hero H1 and category H1, with reserved width; only if the Estedad build carries KSHD; otherwise the saffron underline draw is the gesture (§7.3). |
| Nearest-10,000 rounding kills 999,000 prices | Charm rounding is the default (§5.5). |
| `woocommerce_items_in_cart` has no count | Core-plugin cookie `gtm_cart_count` (§5.10). |
| `_price` rewrite without lookup-table update | Rate change → CRUD `save()` per product via Action Scheduler + transient purge + cache purge (§5.5). |
| Persian digits in `input type=number` | `type=text inputmode=numeric` + normaliser (§5.9). |
| Cart/checkout/login/tracking undesigned | §6.4–6.5. |
| Bricolage loaded on fa pages; 250 KB fonts | fa pages load Vazirmatn VF + Estedad 700/800 only (≈ 200 KB); Latin on fa pages uses Vazirmatn's Latin; Bricolage/Instrument only on /en/ (§3, §11). |
| 11 px rotated sticker text | Sticker text ≥ 12 px; on compact mobile cards the sticker is un-rotated (§5.6). |
| Spread cards break comparison in category grids | Spreads only in the home bento; category grids uniform (§6.2). |
| Reading-progress line on PDP | Blog posts only. |
| «پرفروش‌های این هفته» seeded from views | Labelled «پربازدیدها» until ≥ 30 orders exist; then «پرفروش‌ها» (§6.1). |
| `#۱` rank stickers, issue number | «رتبهٔ ۱» stickers; date-line and issue number removed. |
| Marquee | Replaced by a static brand sticker wall (§6.1). |
| Seal unreadable at 20 px (three colours) | Below 32 px: two-colour version, ticks merged into one wedge (§2.5). |
| Scope | Phase 1 = fa only with the language architecture in place; Phase 2 = en/ar (§12). |

### 1.4 Grafts from the other concepts
- **Vitrine:** "quick buy" secondary CTA straight to checkout; category description truncated at the top and rendered fully at the bottom for SEO; a toast with «مشاهده سبد» instead of an interrupting mini-cart on phones; the digit-roll on *variation change only* (never on load); the per-session power-on stored in sessionStorage; the empty state that offers a «محبوب در این دسته» rail.
- **Shabtab:** badges confined to the top corners and the mobile «+» at the plate's bottom-end corner (watermark-aware, kept even after clean-up because it is the thumb corner); the `gtm_num()` rule that never converts digits inside Latin tokens (USB-C, 65W, GKM-70); the honest numbers strip when fewer than 6 reviews exist; wattage quick-picks on the charger lane; the cleaned 8-group IA; `html.lite` for Save-Data; the "any animation costing > 4 ms/frame is cut" rule; mega menu as plain anchors; per-locale default of the base-currency hint (fa off, en USD, ar AED).
- **KASHI:** `is_packshot` auto-flag with manual override; trust micro-tiles under the buy box that open policy popovers instead of leaving the page; transient-cached computed prices per rate snapshot; the long legacy description collapsed behind «بیشتر بخوانید»; the "which charger for my phone" helper (Phase 2, needs attribute data); the gaf-double-stroke as the shared device across both scripts (Timcheh already had it; KASHI's double-crossbar G confirmed the Latin echo).

---

## 2. Brand expression — the new bilingual mark

### 2.1 Concept «مهر و کشیده» (the seal and the stretch)
Two ideas, one system:
- The two slanted strokes that make a «گ» a گ (the سرکش pair) become the brand's **ticks**: two short parallel saffron bars. They read as energy/charge without drawing a lightning bolt, they live on the Persian wordmark, on the Latin wordmark (as the dot of the i), inside the seal, on the favicon and as the spinner.
- The **kashida** that stretches a Persian word becomes the brand's gesture: the wordmark carries a deliberate kashida between «ت» and «ی» so «گجتــیم» sits like a masthead.

### 2.2 Persian wordmark «گجتیم» — construction (draw in SVG from this)
- Base: Estedad 900, converted to outlines (OFL permits logo use; the outlines are then edited, so no font axis is needed at runtime).
- Grid: 1000 units per em; cap/ascender height of «ا» = 720; baseline y = 0; descender of «ی» = −220 (final ی keeps its hook; it is not flattened).
- Letterforms, right to left: **گ** — bowl kept from Estedad 900; the two سرکش strokes are *removed from the glyph* and redrawn as the ticks (§2.4) at the same position and angle as Estedad's own strokes (measure the angle from the font; nominally 50–55° to the baseline). **ج** — open bowl, counter opened 8 % wider than Estedad so it stays legible at 24 px. **ت** — dots are two squares with 2-unit corner radius, 60 units, spaced 90 units centre-to-centre; they stay ink (only the گ ticks are saffron, so the word has exactly one colour accent). **کشیده** between ت and ی — a straight horizontal stroke, length 380 units at the primary lockup (240 at compact), stroke height equal to the glyph stem (≈ 150 units in 900 weight), joined with the ت and ی stems with 20-unit fillets. **ی** — final form with the hook; the two dots below are removed (گجتیم is spelled with Persian ی, no dots). **م** — closed head, tail cut straight at baseline +40.
- Overall width at primary kashida ≈ 2100 units; height from descender to tick top ≈ 1040 units. Wordmark aspect ≈ 2 : 1.
- Stroke contrast is zero (monoline heavy); no serifs, no calligraphic swash; the word must read as a product, not a signature.

### 2.3 Latin companion "Gadgetim"
- Bricolage Grotesque ExtraBold, optical size 96, converted to outlines. Lowercase with capital G; tracking +0.01 em; single-storey g.
- The **t** crossbar is extended toward the **i** by 0.55 em so it echoes the kashida; the extension sits at x-height − 40 units and ends in a square cut.
- The dot of the **i** is replaced by the two ticks (§2.4), scaled so each tick is 0.16 em long; ticks saffron, letters ink.
- Baseline of the Latin companion sits at −420 units under the Persian wordmark in the stacked lockup; its height is 36 % of the Persian word's x-height-to-tick height; aligned to the inline-start edge of the Persian word.

### 2.4 The ticks (shared device)
- On a 100 × 100 construction square: two rounded rectangles, each 34 units long and 10 units thick, cap radius 5. Tick A from (28, 66) to (50, 34); tick B from (50, 70) to (72, 38). They are parallel; the gap between their centre-lines is 14 units. They rise toward the inline-end in the drawing (the same slant as the سرکش on Estedad's گ; verify against the font and keep whichever matches — the ticks must be the strokes of گ, not a generic "//").
- Colour: saffron #F2A33A (light), #F5B04A (dark). The ticks are the only element allowed to be saffron inside the identity.
- Below 32 px the two ticks merge into one wedge: a single rounded parallelogram from (30, 68) to (70, 32), 18 units thick.

### 2.5 The seal «مهر اصالت» (secondary mark)
- Silhouette: a 12-scallop rubber stamp, not a circle, not a ring, no letter. Construction on a 100-unit square: base circle r = 44 centred (50, 50); 12 scallop lobes r = 9 centred on the base circle at 30° intervals; the union is the stamp body. A 3-unit paper gap, then an inner 12-scallop line (r = 34 base, lobes r = 6) drawn at 2.5 units stroke. The ticks (§2.4) sit in the centre scaled to 60 %.
- Colours: body ink #1F1B17, inner line paper #F7F3EC, ticks saffron. Dark mode: body paper #F3EDE3, inner line night-paper, ticks night-saffron. This is deliberately not blue and not circular, so nothing recalls the old blue monogram.
- Sizes: ≥ 32 px full version (three colours). 20–31 px: body + merged wedge only (two colours, no inner line). Minimum 20 px (card mini-seal) — at 20 px it reads as an ink stamp with a saffron wedge.
- Ring text: none on web (unreadable); the print version (invoice stamp, packaging sticker ≥ 24 mm) may carry «اصل و اورجینال · ضمانت اصالت کالا» around the inner line at 7 pt.
- Where it lives: every product card (20 px, bottom inline-start of the plate), the PDP receipt (40 px), the home trust band (120 px), checkout order review, thank-you page (stamps on arrival), the 404 page, emails (PNG), the optional photo watermark (outline version, 10 % opacity, bottom inline-end).

### 2.6 Lockups
A. Persian wordmark alone (fa/ar header). B. Bilingual stack: Persian over Latin, inline-start aligned (packaging, invoices, OG image, social avatars). C. Latin wordmark alone (en header) with a small «گجتیم» above it at 40 % — the Persian presence is the origin story. D. Seal alone. E. Wordmark + seal: seal at the inline-start of the wordmark, gap = one tick length, seal height = 72 % of the wordmark height (desktop header only).

### 2.7 Clear space, minimum sizes, don'ts
Clear space = one tick length (34/100 of the tick square at the wordmark's scale) on all sides. Minimum: wordmark 96 px wide on web, 20 mm in print; Latin companion alone 72 px; seal 20 px. Never: gradient in the letters, outline version on photos, recolouring the ticks to pomegranate or lapis, stretching the kashida with CSS transforms, placing the mark on a product photo, placing it next to the old blue monogram anywhere (social avatars, favicon, watermark, email signature all get replaced).

### 2.8 Favicon and app icon
- SVG favicon: an ink rounded square (radius 22 %) containing the ticks at 60 % with a 10 % safe zone; `<style>@media (prefers-color-scheme: dark){ .tile{fill:#F3EDE3} .tick{fill:#F5B04A} }</style>` inside the SVG. 16 px and 32 px PNG fallbacks use the merged wedge.
- 180 px apple-touch-icon and 512/192 px maskable icons: ink tile, saffron ticks, 10 % safe zone; theme-color #F7F3EC light / #141210 dark.
- The seal is not the app icon (too detailed below 48 px); the ticks tile is.

### 2.9 Colour variants
Light: ink wordmark, saffron ticks, ink seal. Dark: paper wordmark #F3EDE3, night-saffron ticks, paper seal. One-colour: all ink or all paper (ticks keep their geometry) for print, stamps, eNamad-adjacent placements and the engraving on packaging stickers. On pomegranate (email header only): paper wordmark and paper ticks.

### 2.10 The mark in the product
- **Header:** inline SVG symbol (≈ 3 KB, `currentColor` letters, `.tick` class). 28 px tall desktop, 24 px mobile, at the inline-start, with the 20 px seal beside it on desktop (lockup E). When the header compresses on scroll the wordmark crossfades (200 ms) to the seal alone.
- **Loader («سردر» overture):** no overlay, once per session (sessionStorage `gtm_overture=1`): the seal's inner line draws (stroke-dashoffset, 350 ms), the ticks stamp (scale 1.3→1, 240 ms, stamp easing), the wordmark letters ink in with a 40 ms stagger (opacity only, letters pre-split into `<g>` groups). Total < 700 ms, inside the header, while the hero text is already readable. Reduced motion: appears.
- **Spinner:** the two ticks rotating 360° about their shared centre (600 ms, ease-in-out) is the only spinner (buttons, mini-cart, search).
- **Page transitions:** during a cross-document View Transition the header keeps `view-transition-name: brand-bar` so the mark never moves; a 16 px ticks tile pulses once (opacity .4→1→.4, 400 ms) at the inline-start of the header as the page-turn indicator.
- **Empty states:** empty cart, 404, "notify me" and empty search use the seal as the illustration anchor (404: the seal rotated −12° with a saffron «۴۰۴» sticker slapped across it).
- **Hover on the wordmark:** none in Phase 1 (the outlines have no axis). If the kashida gesture ships (§7.3), the header wordmark gets a second SVG with the kashida 30 % longer and crossfades to it on hover (200 ms). The ticks never animate independently except in the spinner.

### 2.11 Deliverables
SVG masters for lockups A–E in light/dark/mono; favicon.svg; icon-192/512 PNG (maskable); apple-touch-icon; OG template 1200 × 630 (paper, lockup B beside a product plate); email PNG set; invoice stamp (PDF); a one-page usage sheet.

---

## 3. Design tokens

```json
{
  "meta": {
    "name": "gadgetim-timcheh",
    "version": "1.0.0",
    "cssPrefix": "--gtm",
    "notes": "All colour contrast figures are WCAG 2.x ratios against the surface named in 'role'. Dark values apply under :root:not([data-theme=light]) inside @media (prefers-color-scheme: dark) and under :root[data-theme=dark]. Light is the default theme; 'auto' is a user choice."
  },
  "color": {
    "light": {
      "paper":            { "value": "#F7F3EC", "role": "page background" },
      "plate":            { "value": "#FBF8F3", "role": "product image plate; near-white so pure-white packshots show no visible rectangle" },
      "surface":          { "value": "#FFFDF9", "role": "cards, sheets, popovers, buy box, inputs" },
      "plaster":          { "value": "#EDE6DA", "role": "secondary surface: section bands, chips, skeletons, receipt slips" },
      "clay":             { "value": "#D9CFC0", "role": "hairlines, card borders, perforation dashes, input borders" },
      "smoke":            { "value": "#6B655D", "role": "muted text: meta, eyebrows, old prices (5.2:1 on paper, 5.6:1 on surface)" },
      "ink":              { "value": "#1F1B17", "role": "primary text, footer band, wordmark, sticker hard shadow, seal body" },
      "pomegranate":      { "value": "#B3233B", "role": "primary action fill, sale price (5.9:1 on paper; white on it 6.5:1)" },
      "pomegranateDeep":  { "value": "#8F1D2C", "role": "primary hover/pressed, error text and borders" },
      "saffron":          { "value": "#F2A33A", "role": "stickers, discount, stars, underline draw, ticks (ink text on it 8.1:1; never text on paper)" },
      "lapis":            { "value": "#2B3F8C", "role": "links, focus ring, selected chips, brand chip outline (8.7:1 on paper)" },
      "pistachio":        { "value": "#2E7D4F", "role": "in-stock dot, success toast, WhatsApp icon tint (4.6:1 on paper; use >= 14px bold or as icon)" },
      "scrim":            { "value": "rgba(31,27,23,0.40)", "role": "backdrop behind dialogs and sheets" },
      "onPomegranate":    { "value": "#FFFFFF", "role": "text on pomegranate" },
      "onSaffron":        { "value": "#1F1B17", "role": "text on saffron" },
      "onInk":            { "value": "#F7F3EC", "role": "text on ink (footer)" }
    },
    "dark": {
      "paper":            { "value": "#141210", "role": "page background (night bazaar)" },
      "plate":            { "value": "#E8E2D8", "role": "card image plate: lit vitrine, dimmed 10% vs PDP plate to limit grid glare" },
      "platePdp":         { "value": "#F1ECE3", "role": "PDP main gallery plate and hero cut-out plate" },
      "surface":          { "value": "#1C1917", "role": "cards, sheets, header" },
      "surfaceRaised":    { "value": "#26211D", "role": "hover surface, popovers, inputs" },
      "plaster":          { "value": "#26211D", "role": "section bands, chips, skeletons" },
      "clay":             { "value": "#3A332C", "role": "hairlines and borders" },
      "smoke":            { "value": "#A89F93", "role": "muted text (7.2:1 on paper)" },
      "ink":              { "value": "#F3EDE3", "role": "primary text, wordmark, seal body (16:1)" },
      "pomegranate":      { "value": "#E04A5F", "role": "primary action fill with night-paper text (4.7:1 with #141210 text)" },
      "pomegranateDeep":  { "value": "#F06B7C", "role": "primary hover; error text (6.1:1 on paper)" },
      "saffron":          { "value": "#F5B04A", "role": "stickers, ticks, stars (night-paper text 9.9:1)" },
      "lapis":            { "value": "#8FA3F0", "role": "links, focus ring, selected chips (7.7:1)" },
      "pistachio":        { "value": "#6CC08B", "role": "in-stock dot, success (8.0:1)" },
      "scrim":            { "value": "rgba(10,9,8,0.60)", "role": "dialog backdrop" },
      "onPomegranate":    { "value": "#141210", "role": "text on pomegranate (dark)" },
      "onSaffron":        { "value": "#141210", "role": "text on saffron (dark)" },
      "onInk":            { "value": "#141210", "role": "text on ink-coloured elements in dark mode (the seal body is paper, so its ticks stay saffron)" },
      "stickerShadow":    { "value": "#0A0908", "role": "hard 2px offset under stickers in dark mode" }
    }
  },
  "font": {
    "families": {
      "faDisplay":  { "stack": "\"Estedad\", \"Vazirmatn\", \"Vazirmatn-fb\", Tahoma, system-ui, sans-serif", "files": ["Estedad-Bold.woff2 (700, static, Arabic subset)", "Estedad-ExtraBold.woff2 (800, static, Arabic subset)"], "license": "OFL-1.1", "source": "github.com/aminabedi68/Estedad", "selfHost": "/wp-content/themes/timcheh/assets/fonts/estedad/" },
      "faText":     { "stack": "\"Vazirmatn\", \"Vazirmatn-fb\", Tahoma, \"Segoe UI\", system-ui, sans-serif", "files": ["Vazirmatn[wght].woff2 (variable 100-900, Arabic + Latin basic + Persian/Arabic digits + ZWNJ subset)"], "license": "OFL-1.1 (Latin glyphs Apache-2.0, ship both licenses)", "source": "github.com/rastikerdar/vazirmatn", "selfHost": "/wp-content/themes/timcheh/assets/fonts/vazirmatn/" },
      "arDisplay":  { "stack": "\"Vazirmatn\", \"Vazirmatn-fb\", \"Noto Naskh Arabic\", Tahoma, system-ui, sans-serif", "note": "Vazirmatn 800 via the variable file; Estedad used for ar only after its locl ARA forms are verified" },
      "arText":     { "stack": "\"Vazirmatn\", \"Vazirmatn-fb\", \"Noto Naskh Arabic\", Tahoma, system-ui, sans-serif", "note": "lang=ar enables locl forms (kaf/yeh shapes, Arabic-Indic 456 shapes)" },
      "enDisplay":  { "stack": "\"Bricolage Grotesque\", \"Bricolage-fb\", \"Segoe UI\", system-ui, sans-serif", "files": ["BricolageGrotesque[opsz,wdth,wght].woff2 (Latin subset)"], "license": "OFL-1.1", "selfHost": "/assets/fonts/bricolage/" },
      "enText":     { "stack": "\"Instrument Sans\", \"Instrument-fb\", \"Segoe UI\", system-ui, sans-serif", "files": ["InstrumentSans[wdth,wght].woff2 (Latin subset)"], "license": "OFL-1.1", "selfHost": "/assets/fonts/instrument-sans/", "note": "if the build lacks tnum, swap to Inter" },
      "fallbackMetrics": { "Vazirmatn-fb": "src: local(Tahoma), local('Segoe UI'), local('Noto Naskh Arabic'); size-adjust: 96%; ascent-override: 105%; descent-override: 40%; line-gap-override: 0%", "Bricolage-fb": "src: local('Segoe UI'), local(Arial); size-adjust: 98%; ascent-override: 92%; descent-override: 24%", "Instrument-fb": "src: local('Segoe UI'), local(Arial); size-adjust: 99%; ascent-override: 93%; descent-override: 23%", "note": "tune with a fallback-metrics tool before launch; values here are starting points" },
      "loading": "font-display: swap; preload Vazirmatn[wght] and Estedad-ExtraBold on fa pages (the hero H1 is Estedad 800); preload Bricolage and Instrument on en pages; immutable cache headers; subset once with pyftsubset (documented command in the repo)"
    },
    "weights": { "body": 400, "label": 500, "strong": 700, "price": 700, "display": 700, "hero": 800 },
    "size": {
      "2xs":   { "value": "0.75rem",  "lineHeight": 1.5,  "use": "stickers, brand eyebrow (12px floor everywhere)" },
      "xs":    { "value": "0.8125rem","lineHeight": 1.6,  "use": "meta, counts, breadcrumbs, old price" },
      "sm":    { "value": "0.875rem", "lineHeight": 1.75, "use": "card title mobile, captions, chips" },
      "base":  { "value": "1rem",     "lineHeight": { "fa": 1.9, "ar": 1.9, "en": 1.6 }, "use": "body" },
      "md":    { "value": "1.125rem", "lineHeight": 1.8,  "use": "lead, card title desktop, nav" },
      "lg":    { "value": "clamp(1.25rem, 1.1rem + 0.7vw, 1.5rem)",   "lineHeight": 1.5,  "use": "H4, PDP deck" },
      "xl":    { "value": "clamp(1.5rem, 1.2rem + 1.5vw, 2.25rem)",   "lineHeight": 1.35, "use": "H3, PDP H1" },
      "2xl":   { "value": "clamp(2rem, 1.4rem + 3vw, 3.5rem)",        "lineHeight": 1.2,  "use": "section H2, category H1" },
      "3xl":   { "value": "clamp(2.5rem, 1.4rem + 5vw, 5.5rem)",      "lineHeight": 1.08, "use": "hero masthead (en capped at 4.75rem)" },
      "priceCard": { "value": "clamp(1.125rem, 1rem + 0.6vw, 1.375rem)", "use": "card price" },
      "pricePdp":  { "value": "1.75rem", "use": "PDP price" },
      "priceBar":  { "value": "1.25rem", "use": "mobile buy bar price" }
    },
    "measure": { "fa": "60ch", "ar": "58ch", "en": "68ch" },
    "tracking": { "arabicScript": "0", "latinUppercaseLabel": "0.08em", "bricolageAbove48px": "-0.01em" },
    "numerals": { "fa": "U+06F0-06F9, thousands U+066C, no decimals", "ar": "U+0660-0669, thousands U+066C", "en": "ASCII, thousands ','", "tabular": "font-variant-numeric: tabular-nums on prices, counts, ratings, tables" }
  },
  "space": {
    "unit": "4px",
    "scale": { "1": "4px", "2": "8px", "3": "12px", "4": "16px", "5": "20px", "6": "24px", "8": "32px", "10": "40px", "12": "48px", "16": "64px", "20": "80px", "24": "96px" },
    "section": { "mobile": "48px", "desktop": "96px" },
    "gutter":  { "mobile": "16px", "tablet": "24px", "desktop": "32px" },
    "gridGap": { "mobile": "12px", "tablet": "20px", "desktop": "24px" }
  },
  "radius": { "xs": "6px", "sm": "10px", "md": "14px", "lg": "20px", "xl": "28px", "pill": "999px", "note": "cards lg, buy box xl, chips/stickers xs, inputs sm, thumbnails sm, sheets xl on the top corners" },
  "border": { "hair": "1px solid var(--gtm-clay)", "perforation": "1px dashed var(--gtm-clay)", "focus": "2px solid var(--gtm-lapis)", "focusOffset": "2px" },
  "shadow": {
    "1": "0 1px 2px rgba(31,27,23,0.06)",
    "2": "0 12px 32px -12px rgba(31,27,23,0.25)",
    "sheet": "0 24px 64px -24px rgba(31,27,23,0.35)",
    "sticker": "0 2px 0 var(--gtm-ink)",
    "darkNote": "in dark mode shadows 1/2/sheet become 'none' plus a 1px clay border and a surfaceRaised background; sticker shadow uses stickerShadow",
    "rule": "shadows live on a ::after pseudo-element whose opacity animates; box-shadow itself never animates"
  },
  "motion": {
    "duration": { "1": "120ms", "2": "200ms", "3": "320ms", "4": "480ms", "5": "640ms", "stagger": "50ms", "staggerCap": 8, "exitRatio": 0.6 },
    "easing": {
      "out":    "cubic-bezier(0.16, 1, 0.3, 1)",
      "inOut":  "cubic-bezier(0.65, 0, 0.35, 1)",
      "stamp":  "cubic-bezier(0.34, 1.56, 0.64, 1)",
      "stampLinear": "linear(0, 0.006, 0.025 2.8%, 0.101 6.1%, 0.539 18.9%, 0.721 25.3%, 0.849 31.5%, 0.937 38.1%, 0.985 45.3%, 1.004 52.5%, 1)",
      "exit":   "cubic-bezier(0.4, 0, 1, 1)"
    },
    "reducedMotion": "durations 1ms, transforms removed, opacity fades <= 150ms kept, no parallax, no view transitions, marquee/loops none",
    "reveal": { "distance": "24px", "threshold": 0.15, "rootMargin": "0px 0px -10% 0px", "once": true }
  },
  "breakpoint": { "xs": "360px", "sm": "480px", "md": "768px", "lg": "1024px", "xl": "1280px", "note": "mobile-first min-width queries; 1 column below 340px" },
  "container": { "max": "1280px", "prose": "720px", "checkout": "880px" },
  "zIndex": { "base": 0, "raised": 1, "sticky": 10, "header": 20, "appBar": 20, "buyBar": 25, "popover": 30, "drawer": 40, "dialog": 50, "toast": 60, "progress": 70 },
  "layout": { "headerHeight": { "desktop": "64px", "desktopCompressed": "56px", "mobile": "56px", "laneBar": "44px", "microBar": "32px" }, "appBar": "60px + env(safe-area-inset-bottom)", "buyBar": "64px + env(safe-area-inset-bottom)" }
}
```

Implementation notes for the tokens: they are declared once on `:root` in `@layer tokens`, dark overrides are declared twice (media-guarded and attribute-guarded), `color-scheme: light dark` is set on `:root` so native controls follow, a 1-line inline script in `<head>` applies `data-theme` from localStorage before paint, and `<meta name="theme-color">` is updated by the toggle.

---

## 4. Layout system

- **Containers:** page max 1280 px, centred, with the gutter tokens; prose max 720 px; checkout max 880 px. Full-bleed bands (trust band, footer) use `margin-inline: calc(50% - 50vw)` safely via a `.bleed` utility with `scrollbar-gutter: stable`.
- **Grid:** 12 columns on ≥ 1024 px with 24 px gaps; 6 columns on 768–1023 px with 20 px gaps; 4 columns below 768 px with 12 px gaps. Product grids: 4 / 3 (beside a sidebar) / 3 (tablet) / 2 (mobile) / 1 (< 340 px). All asymmetric compositions (hero collage 7/5, story spread 5/7, curated edits 7/5 then 4/8) are declared with `grid-column` logical placement so `dir` mirrors them.
- **Density rules:** two densities, chosen by a desktop toggle in the category toolbar and persisted in localStorage. *Comfortable* (default): card padding 12 px, title 16 px, 24 px gaps. *Compact*: padding 8 px, title 14 px, 16 px gaps, 5 columns at ≥ 1280 px. Mobile is always compact.
- **Vertical rhythm:** sections separated by 48 px (mobile) / 96 px (desktop); a ledger hairline closes every section that has no band background; section header (eyebrow 12 px smoke + H2 + optional ledger link) sits 24 px above its content.
- **Rails:** `scroll-snap-type: x mandatory`, `scroll-padding-inline: var(--gutter)`, `overscroll-behavior-x: contain`; items snap to start; the last item has an inline-end gutter via `::after`. Rails never use JS carousels; desktop gets prev/next buttons mapping to logical start/end with `scrollBy({left: dir * amount})` where `dir` is −1 in RTL.
- **Sticky stack (mobile):** header 56 px at top; app bar 60 px at bottom; on the PDP the buy bar replaces the app bar (never both). On category pages the toolbar is not sticky on phones (it would eat 27 % of the viewport); it is a 48 px row above the grid and the «فیلترها» button is duplicated as a floating 44 px pill at the bottom inline-end when the toolbar is off-screen.

---

## 5. Components

### 5.1 Header and mega menu
- **Micro-bar (optional, 32 px, plaster):** one admin sentence («ارسال رایگان بالای ۵ میلیون تومان») with a close button (dismissal stored in sessionStorage; the bar's height is reserved by CSS when the `gtm_micro` cookie is absent so cached HTML does not jump — the bar is rendered; JS removes it only after the dismissal cookie exists, and the removal adds `padding-top` compensation so there is no CLS). Language/currency chip at the inline-end.
- **Main bar:** 64 px desktop, 56 px mobile; `position: sticky`; paper at 92 % with `backdrop-filter: blur(12px)` only under `(hover: hover) and (min-width: 1024px)`; solid paper otherwise; clay bottom hairline that fades in after 8 px of scroll (opacity).
  - Inline-start: wordmark (28 / 24 px) + seal on desktop; the whole lockup is one link to home.
  - Centre (desktop): search as a paper slip, 48 px tall, radius sm, clay border that turns lapis on focus; rotating placeholder from an admin list; `role="combobox"` with a listbox popover fed by `/wp-json/gtm/v1/suggest?q=&lang=` (250 ms debounce, min 2 chars, aborted on new input, results transient-cached 10 min server-side). Rows: 40 px plate thumb, short title, price; groups «محصولات / دسته‌ها / برندها»; keyboard: arrows, Enter, Escape; Enter without a selection submits the normal `?s=&post_type=product` page. The suggest endpoint searches the short title, the full title, SKU and brand name, so «GR-22» and «K163» resolve.
  - Inline-end: account (icon + «ورود» label on desktop; initial avatar when logged in), wishlist count (localStorage), cart button with a badge rendered from the `gtm_cart_count` cookie (§5.10). On mobile only search and cart icons.
- **Lane bar (desktop only, 44 px):** «همهٔ دسته‌ها» mega trigger + top links: شارژر و پاوربانک · صوتی · خودرو · خانه و آشپزخانه · سلامت و زیبایی · سفر و کمپ · برندها · تخفیف‌ها · راهنمای خرید. Plain anchors, no ARIA menu roles.
- **Mega panel:** full-width paper panel under the lane bar, 3 columns of grouped lanes with 32 px plate thumbnails and counts, plus an inline-end «پیشنهاد سردبیر» product card. Opens on hover intent (150 ms) or click/Enter (`aria-expanded`), closes on Escape, outside click and focus-out; translateY −8→0 + opacity in 200 ms with 40 ms column stagger. Never rendered in the mobile DOM.
- **Sticky behaviour:** past 80 px of downward scroll the lane bar hides (translateY −100 % + opacity) and the main bar compresses to 56 px by shrinking paddings only (heights fixed per state, no height animation); upward scroll restores. The header carries `view-transition-name: brand-bar`.

### 5.2 Language + currency switcher
One chip «فا · تومان» (en: "EN · Toman", ar: «ع · تومان») in the micro-bar (or the main bar when the micro-bar is off) and in the footer utility row. Desktop: popover anchored to the chip; mobile: bottom sheet. Two groups:
- «زبان»: segmented control فارسی · English · العربية — real links to the translated counterpart (hreflang map); if no translation exists the link goes to that language's home and the item shows a small «(صفحهٔ اصلی)» hint.
- «نمایش معادل ارزی»: تومان فقط · + درهم · + دلار. Stored in a `gtm_hint` cookie (1 year, SameSite=Lax) and applied by a 3-line inline script that sets `data-hint="aed|usd|none"` on `<html>` before paint. Hints are rendered in the HTML as `<span class="price-hint" data-cur="AED">` for every currency the admin enabled and shown/hidden by CSS, so the cached page has one variant.
- Transparency line at the bottom: «نرخ‌ها: امروز ۰۹:۳۰» from the rate option's timestamp (rendered server-side; the cache is purged on rate change so it is never stale).

### 5.3 Search (page and sheet)
- Mobile: the header search icon and the app-bar «جست‌وجو» open a full-screen `<dialog>`: autofocused input (16 px font so iOS does not zoom), recent searches (localStorage, max 6, clearable), popular chips (admin list), live suggestions below. Escape/back closes; focus returns.
- Results page: the category template (§6.2) with the query in the H1 «نتایج برای «پاوربانک»» and the count; empty state shared with categories plus a «پرسش از واتساپ» button whose prefilled text includes the query.

### 5.4 Product card
Anatomy (RTL, top to bottom), all sizes in the comfortable density:
1. **Container:** surface, radius lg, 1 px clay border, no shadow at rest; `contain: layout paint`. Pointer hover: translateY(−4 px) and the `::after` shadow-2 layer fades in (200 ms). Focus-visible: lapis ring, 2 px offset. One link (the title) covers the card via `::after`; action buttons are siblings in DOM order.
2. **Plate:** `aspect-ratio: 1/1`, plate colour, radius md, 12 px inset (8 px compact); `img { object-fit: contain; width: 100%; height: 100% }` with `width`/`height` attributes; `srcset` 320/480/640 and `sizes="(min-width:1280px) 20vw, (min-width:1024px) 23vw, (min-width:768px) 31vw, 46vw"`; `loading="lazy" decoding="async"` except the first four cards in the first grid (eager, the first gets `fetchpriority="high"` on category pages). Images flagged `_gtm_packshot=1` get `mix-blend-mode: multiply` in light mode only (on #FBF8F3 the tint is imperceptible); in dark mode no blend — the plate is #E8E2D8 and a flagged image is drawn inside a nested #FBF8F3 inset with 8 px padding so the white rectangle reads as a deliberate mat. Unflagged images (dark renders, lifestyle shots) are never blended. Out of stock: static `filter: grayscale(1) opacity(.6)` on the image. Second gallery image crossfades in on pointer hover (250 ms) only when `prefers-reduced-data` is not set and the device has hover.
3. **Stickers (top corners only):** top inline-start, one sticker maximum, priority ناموجود > تخفیف (≥ 15 %) > تازه (published < 30 days) > ویژه (legacy `special`, only when the admin keeps it). Vazirmatn 700, 12 px, padding 4 px 8 px, radius xs, hard shadow `0 2px 0 ink`; rotated −6° (RTL) / +6° (LTR) on desktop; **un-rotated on compact mobile cards**. Colours: saffron «٪۲۰», plaster-on-ink «تازه», pomegranate outline «ویژه», smoke «ناموجود».
4. **Mini-seal:** 20 px two-colour seal at the plate's bottom inline-start with `title` and a visually hidden text «اصل و اورجینال با ضمانت اصالت کالا».
5. **Wishlist heart:** 36 px ghost circle at the top inline-end of the plate (always visible on touch, hover-revealed on desktop); localStorage only.
6. **Eyebrow:** brand (Latin in Vazirmatn 700 uppercase 12 px tracking .08 em; Persian brand names in Vazirmatn 700 12 px) · lane name 12 px smoke. Products without a brand (7 today) show only the lane.
7. **Title:** `_gtm_short_title` (fallback: the full title with the boilerplate «اصل و اورجینال» and «با ضمانت اصالت کالا» stripped by the core plugin, then clamped), Vazirmatn 500, 14 px mobile / 16 px desktop, line-height 1.7, 2-line clamp with a reserved 2-line height so rows align.
8. **Rating row:** only when reviews exist: saffron stars 12 px + «(۱۲)».
9. **Price block:** current price Vazirmatn 700 tabular + thin space + «تومان» 12 px/500 smoke; when the discount is ≥ 10 % the old price appears struck-through in 13 px smoke on the same line (inline-end); when ≥ 15 % the current price is pomegranate and the sticker shows. Base-currency hint `<span class="price-hint" data-cur="AED">≈ ۱۲۰ درهم</span>` 12 px smoke, hidden on cards unless the admin enables hints on cards (default off). Stock line: 6 px pistachio dot + «موجود» or smoke «ناموجود».
10. **Quick action:** a 44 px round pomegranate «افزودن به سبد» button at the plate's bottom inline-end corner on mobile (thumb corner; also keeps clear of the legacy watermark corner during the transition) and in the price row on desktop; variable products open the PDP; out of stock swaps it for a smoke bell «خبرم کن» opening a small sheet (phone or email) posting to `gtm_stock_alerts`.
- **Variants:** compact mobile (2-column, 8 px inset, 14 px title, cart button only, no hover affordances); *spread* (home bento only: 2 columns wide, plate inline-start 45 %, text inline-end with a 3-line excerpt and up to 3 spec chips); call-for-price (§5.7).
- **Skeleton:** identical boxes in plaster, opacity pulse 1→.55 at 1.2 s, `aria-busy` on the grid, replaced in place.
- **Motion:** reveal translateY 24→0 + opacity 480 ms, 50 ms stagger capped at 8; sticker slap on reveal (scale 1.3→1, stamp easing, 200 ms); add-to-cart choreography in §7.2.

### 5.5 Price display, rounding and the pricing engine
- **Data model (core plugin):** product meta `_gtm_currency` (ISO code or empty), `_gtm_base_regular`, `_gtm_base_sale` (decimal in the base currency); option `gtm_rates` = `{ "AED": {"rate": 78000, "updated": "2026-11-04 09:30"}, "USD": {...} }`; option `gtm_rounding` = `charm | nearest10k | nearest1k | none` (default `charm`); option `gtm_sale_threshold_sticker` (default 15), `gtm_sale_threshold_strike` (default 10).
- **Computation:** `toman = base × rate`, then rounding. *Charm:* round to the nearest 10,000 then subtract 1,000 (→ …9,000); for results ≥ 10,000,000 round to the nearest 100,000 then subtract 1,000 (→ …99,000). This reproduces the catalogue's existing 999,000 / 1,499,000 / 15,099,000 style. Products without a currency keep their fixed Toman prices untouched.
- **Write-through:** on any rate change or rounding change, the plugin queues an Action Scheduler job (ships inside WooCommerce) that walks products with that currency in batches of 50, sets `regular_price` / `sale_price` / `price` through the CRUD (`$product->set_regular_price()` etc. then `save()`, which updates `wc_product_meta_lookup`), deletes product transients, and after the last batch calls the cache plugin's purge hook (`gtm_purge_cache` action with adapters for the common Iranian cache plugins and WP Rocket/LiteSpeed). Sorting, the price filter, coupons, reports and JSON-LD therefore all read the same number. Totals are also recomputed at `woocommerce_before_calculate_totals` from the same function so a cart created before a rate change is honest at checkout (and a notice «قیمت یک کالا بر پایهٔ نرخ جدید به‌روز شد» is shown if it changed).
- **Formatting:** one pair of server functions `gtm_format_price($toman, $lang)` and `gtm_digits($string, $lang)` mirrored by `formatPrice()` / `toDigits()` in JS. fa → ۱٬۲۵۰٬۰۰۰ تومان; ar → ١٬٢٥٠٬٠٠٠ تومان; en → 1,250,000 Toman. The currency word follows a thin space (U+2009) in a `.unit` span (0.75 em, weight 500, smoke). Every figure is wrapped in `<bdi>`. Digit conversion never touches tokens containing Latin letters (USB-C, 65W, GKM-70, iPhone 16 Pro) and never touches phone numbers, SKUs or URLs, which also get `dir="ltr"`.
- **PDP transparency line (admin toggle, default on for PDP):** «بر پایهٔ ۱۲۰ درهم × نرخ امروز» 12 px smoke with an info button opening a popover: the rate, its timestamp, the rounding rule in one sentence, and «پرداخت همیشه به تومان انجام می‌شود». The phrase «نرخ روز» is never used in the price block itself (it invites waiting); the hint is factual, not promotional.
- **Sale state:** discount percentage computed from the final Toman pair. < 10 %: no strike, no sticker (the price is just the price). 10–14 %: struck old price only. ≥ 15 %: sticker + pomegranate price + struck old price. An admin per-product flag `_gtm_show_sale` can force either way. Optional «معتبر تا ۱۴۰۵/۰۸/۲۰» as plain text from WooCommerce's sale schedule; no countdown.

### 5.6 Stickers and badges
Shared `.sticker` component: Vazirmatn 700 12 px (14 px in the hero and the story section), padding 4 px 8 px, radius xs, hard 2 px ink shadow, rotation by `--tilt` (−6° RTL, +6° LTR, 0 on compact cards), slap animation on reveal. Variants: saffron (discount, spec stickers, «اضافه شد», rank «رتبهٔ ۱»), ink/plaster («تازه»), pomegranate outline («ویژه»), smoke («ناموجود»), lapis outline (brand chip, filter chip). Text on saffron is always ink.

### 5.7 Call-for-price mode («برگهٔ قیمت»)
- **Admin:** global toggle (off by default), per-product override (قیمت / تماس / پیروی از تنظیمات), fields: explanation line, phone, WhatsApp number, Telegram handle, working hours, primary channel (default WhatsApp).
- **Price area when on:** a plaster *price slip* with a dashed clay perforation on the top edge and a radial-gradient mask dotted bottom edge; «قیمت: تماس بگیرید» in Estedad 700 at size lg; the honest one-liner «به‌دلیل نوسان نرخ ارز، قیمت را لحظه‌ای اعلام می‌کنیم» 14 px smoke; then three equal 48 px buttons in a row (stacked under 400 px): «تماس» (tel:), «واتساپ» (wa.me prefilled with the short title + URL, icon tinted pistachio), «تلگرام» (t.me, icon tinted lapis); the primary channel is ink-filled, the others ink-outline. No pomegranate anywhere on the page in this mode.
- **PDP:** quantity stepper, variations' prices, add-to-cart, quick buy, discount sticker, old price and the base-currency hint are not rendered; the authenticity receipt stays; the mobile sticky bar becomes «تماس · واتساپ · تلگرام».
- **Cards:** price line = phone glyph + «تماس بگیرید» in smoke; the quick action becomes a WhatsApp glyph opening the prefilled chat; heart stays; stickers suppressed except «ناموجود».
- **Site-wide enforcement (server-side):** `woocommerce_is_purchasable` → false; `woocommerce_get_price_html` → the label; wc-ajax and REST add-to-cart refused; cart/checkout URLs redirect to the shop; header and app-bar cart slots hidden (the app bar's fifth slot becomes «تماس»); mini-cart disabled; price sorts and the price filter removed; the «تخفیف‌ها» home section skipped; JSON-LD omits Offer price but keeps availability; cache purged on toggle; en/ar labels "Ask for price" / «اطلب السعر».
- **Motion:** the slip tears in once per page (clip-path inset from the perforation, 320 ms), buttons stagger 60 ms; nothing continuous.

### 5.8 Buttons
Primary: pomegranate fill, white text, 48 px (52 px on the PDP CTA), radius pill, Vazirmatn 600 15 px, hover pomegranateDeep, pressed scale .98 (120 ms), loading = label swapped for the ticks spinner, success = check + «اضافه شد» sticker. Secondary: ink outline 1.5 px, ink text, same sizes. Ghost: no border, lapis text, underline grows on hover (scaleX 160 ms). Icon buttons: 44 px round, ghost or surface. Destructive: pomegranateDeep text, outline. Disabled: plaster fill, smoke text, no shadow. Dark mode: primary is night-pomegranate with night-paper text.

### 5.9 Forms
Inputs 48 px, surface fill, clay border 1 px, radius sm, label above in 14 px/500 ink, help text 13 px smoke, error 13 px pomegranateDeep with an inline icon and `aria-describedby`; focus lapis ring. Numeric fields (price filter, quantity, postcode, phone) are `type="text" inputmode="numeric"` (or `inputmode="tel"`) accepting any digit set, normalised to ASCII on input and re-rendered in locale digits on blur; phone inputs are `dir="ltr"` `text-align: start`. Selects are native `<select>` styled with a chevron that mirrors. Checkboxes 20 px with a saffron check on ink; radios as sticker chips where they are choices (sort, variations, star rating). Steppers: «− ۱ +» with 44 px targets. Every form works without JS.

### 5.10 Cart drawer, mini-cart and the cart badge
- **Badge:** the core plugin writes a `gtm_cart_count` cookie (path /, SameSite=Lax, not HttpOnly) on `woocommerce_set_cart_cookies`, `woocommerce_add_to_cart`, `woocommerce_cart_item_removed`, `woocommerce_after_cart_item_quantity_update`, `woocommerce_cart_emptied` and after checkout. The header and app-bar badges render from this cookie on load and after every cart mutation; the HTML never contains the count. The badge digit rolls (translateY) on change.
- **Add to cart:** fetch POST to `/?wc-ajax=add_to_cart` (WooCommerce's own endpoint, no jQuery needed) with `product_id` and `quantity`; the response fragments are ignored, the cookie is re-read. Variable products go to the PDP.
- **Drawer (desktop):** inline-end `<dialog>` 420 px wide, surface, sheet shadow; content fetched on open from `/wp-json/gtm/v1/cart` (server-rendered HTML fragment with fresh nonces, `Cache-Control: no-store`): line items with 64 px plate thumbs, short titles, variation text, steppers, remove, subtotal, the transparency line, «ادامهٔ خرید» (closes) and a pomegranate «تسویه حساب». Peeks for 2.5 s after a desktop add-to-cart, then closes unless hovered.
- **Mobile:** add-to-cart shows a toast (§5.11) with «مشاهده سبد»; the drawer is a full-height inline-end sheet opened from the app bar.

### 5.11 Toasts
Bottom-centre above the app bar, surface, radius md, 1 px clay, shadow-2, icon + text + optional action; slide up 16 px + opacity (320 ms), auto-dismiss after 4 s with a saffron progress line (scaleX), pausable on hover/focus, `role="status"`, max 2 stacked. Success uses the pistachio icon; errors pomegranateDeep and `role="alert"`.

### 5.12 Skeletons
Plaster blocks at the final dimensions (card: plate + 2 text bars + price bar; PDP: gallery + buy box); opacity pulse 1→.55 at 1.2 s; `aria-busy="true"` on the container; no shimmer gradients.

### 5.13 Footer
Perforated ticket rule (dashed clay) opens an ink band (dark: surface with a clay top rule); giant embossed wordmark watermark (Estedad 800, ink lightened 6 %, `aria-hidden`, clipped). Four columns (accordions on mobile): «دسته‌ها» (top 8), «خدمات مشتری» (پیگیری سفارش · ضمانت بازگشت · شیوهٔ ارسال · سوالات متداول · ضمانت اصالت کالا), «گجتیم» (درباره · تماس · همکاری با ما · راهنمای خرید), «در تماس باشیم» (phone `dir=ltr`, WhatsApp, Telegram, Instagram, address, hours). Trust row: اینماد and ساماندهی image/embed slots from theme options (official embeds only), the gateway plugin's logo, the seal beside «ضمانت اصالت کالا». Utility row: language/currency switcher, theme toggle «خودکار / روز / شب», motion toggle «کاهش حرکت», the transparency line with timestamp, «© ۱۴۰۵ گجتیم» + small "Gadgetim". `content-visibility: auto; contain-intrinsic-size: 0 720px`.

### 5.14 Breadcrumbs
12 px smoke, lapis on hover, separator chevron with `.is-directional`; «خانه › شارژر و پاوربانک › Tron X»; the last item is `aria-current="page"` and truncated at 40 characters on phones; BreadcrumbList JSON-LD emitted by the core plugin.

### 5.15 Pagination
Numbered links (`rel="prev"/"next"`, real `?page=N` URLs, Persian digits), current page as an ink sticker chip, plus «نمایش بیشتر» which fetches the next page's grid fragment, appends it, and pushes the URL; the footer always stays reachable (no infinite scroll).

---

## 6. Pages

### 6.1 Home
Core sections render always; conditional sections render only when their data condition is met (server-side check, no empty bands).

1. **«سردر» Masthead hero (core).** Desktop: 12-col, text 7 columns inline-start, collage 5 columns inline-end. Content: eyebrow «فروشگاه گجت‌های اصل» (12 px smoke, no date-line), one admin headline max 2 lines in Estedad 800 at size 3xl (default «گجت‌های خاص، اصل، با مهر اصالت»), a 1-line deck 18 px, the search slip (same combobox as the header, 52 px) directly under the deck, then two buttons: pomegranate «همهٔ محصولات», ghost «تازه‌رسیده‌ها». Collage: three packshots on plates at three depths (56/40/28 % of the column width, overlapping, clean-up required), one saffron sticker «اصل و اورجینال، با ضمانت اصالت کالا» rotated −8°. **Mobile (≤ 767 px):** the hero is ≤ 55 svh: headline at 2.5 rem/2 lines, deck, the search slip, one plate (44 vw) with the sticker, and a row of 8 category chips (scroll-snap, 40 px, plate thumbs 24 px) at the hero's bottom edge, so the first screen has search and categories. Motion: headline lines reveal upward with 60 ms stagger (640 ms); plates arrive from the inline-end with rotate −6→0, scale 1.04→1 (560 ms, 80 ms stagger); sticker slaps last; afterwards ≤ 12 px scroll parallax on the plates only inside `@supports (animation-timeline: view())` and only on ≥ 1024 px. Reduced motion: 200 ms fade. The H1 text is the LCP candidate on fa pages (Estedad 800 preloaded); the first plate image is preloaded with `fetchpriority="high"`.
2. **«راسته‌ها» → label «دسته‌بندی‌ها» (core).** Eyebrow «راسته‌های تیمچه» is the only bazaar word. Scroll-snap row of 9 tall plaster lane cards (3:4 mobile at 44 vw, 4:5 desktop; the first two featured at 1.5×) with the lane name in Vazirmatn 700 22 px, one plate, and «۱۷ کالا»; closing tile «همهٔ دسته‌ها». Lanes map to the cleaned tree: شارژر و کابل · پاوربانک · صوتی · خودرو · خانه و آشپزخانه · سلامت و زیبایی · سفر و کمپ · حافظه و فلش · هوشمند و پوشیدنی. On the «شارژر و کابل» lane card, desktop only, wattage quick-picks (۲۰ · ۲۵ · ۳۳ · ۴۵ · ۶۵ وات) as sticker chips linking to the filtered lane (requires the `pa_watt` attribute; hidden when absent). Motion: IO stagger reveal; pointer hover lifts the plate (translateY −6 px, rotate 2°). No kashida here.
3. **«تازه رسیده» Just arrived (core).** Editorial bento of the 5 newest in-stock products: one spread card (2×2) with a 3-line story from `_gtm_story` (fallback: short description, stripped of tables) + 4 standard cards; folio numbers «۰۱…۰۵» in Estedad 800 48 px in the plaster corner of each card (desktop only); «تازه» sticker; ledger link «همهٔ تازه‌ها». Motion: the spread card page-turns in (clip-path inset(0 0 0 100 %)→inset(0), 600 ms, the one large clip-path on this page), cards stagger.
4. **«پربازدیدها» (core).** Header «پربازدیدهای گجتیم» with the honesty line «بر پایهٔ بازدید»; a rail of 8 in-stock cards with saffron «رتبهٔ ۱…۸» stickers; mobile 72 vw cards. Automatically renamed «پرفروش‌ها» with the line «بر پایهٔ سفارش‌های ۳۰ روز اخیر» once ≥ 30 orders exist in the last 30 days (option `gtm_bestseller_min_orders`). Today's views skew (one product at 4,316, the rest ≤ 253) is acceptable for «پربازدیدها» because it is literally true.
5. **«مهر اصالت» Seal of authenticity (core).** Plaster band; the 120 px seal at the inline-start; four promises in four columns with 1.75 px hand-drawn icons: «اصل و اورجینال با ضمانت اصالت کالا» · «۷ روز ضمانت بازگشت» · «ارسال به سراسر ایران» · «پشتیبانی واتساپ و تلگرام» (return window and delivery text are theme options and ship as placeholders until the client confirms them — §12); an admin paragraph on how authenticity is verified; link to the guarantee page. Motion: seal stamp at 50 % visibility (scale 1.5→1, rotate −8→0, 350 ms stamp easing) with the 1 px lapis ring expanding to 1.25× and fading (500 ms); promises fade up 60 ms stagger.
6. **«تخفیف‌ها» Bazaar bargains (core, conditional on ≥ 4 qualifying products).** The 8 in-stock products with the highest discount ≥ 15 %; sticker, struck old price, strike line draws (scaleX 300 ms); eyebrow «قیمت‌ها به تومان و بر پایهٔ نرخ ثبت‌شده». Skipped in call-for-price mode.
7. **«پای صحبت» Contact strip (core).** Ink band: phone (`dir=ltr`), WhatsApp, Telegram, Instagram, hours; newsletter input (core plugin CPT `gtm_subscriber`, honeypot + time-trap, double opt-in email via wp_mail); the transparency line with the rate timestamp. No motion.

Conditional sections, inserted in this order when their condition holds:
- **«چرا این گجت؟» Story spread** (after 3): when an admin has picked a product and filled `_gtm_story_title` + three `_gtm_story_reason` + up to three spec stickers. 5/7 split, pull-quote headline with the saffron underline draw (scaleX 480 ms), numbered reasons, price block + CTA, large plate with the three spec stickers pinned around it; scroll-driven sticker drift inside `@supports`, IO stagger fallback; image stacks first on mobile.
- **«برای…» Curated edits** (after 5): when ≥ 3 edits are configured (tag/category/price query + title + plate): «برای ماشین» · «برای سفر و کمپ» · «برای میز کار» · «برای خانه و آشپزخانه» · «هدیه زیر ۲ میلیون»; overlapping editorial tiles, 7/5 then 4/8 placement, alternating plaster/ink; hover pairs (title +8 px, image −8 px).
- **«برندها» Brand wall** (after 6): static sticker wall (two wrapped rows of typographic stickers — Latin in Vazirmatn 700 uppercase on fa pages, Bricolage 700 on en — or monochrome SVG logos once the client supplies official vectors); no marquee. Shown when ≥ 6 brands have ≥ 1 product.
- **«حرف بازار» Reviews** (after the brand wall): when ≥ 6 approved reviews exist; pinned-note cards (rotate −2°/+1.5°, saffron stars, initial avatar, product thumb), aggregate «۴٫۶ از ۵ · ۱۲۸ نظر»; drop-in motion (translateY −12→0 while rotating to the tilt). Below 6 reviews: the honest numbers strip «۱۰۷ کالای اصل · ۱۳ برند · ارسال به سراسر ایران» computed from real counts.
- **«راهنمای خرید» Guides** (before the contact strip): when ≥ 3 published posts exist in the «راهنما» category; three magazine columns with reading time.

### 6.2 Shop / category / brand / search
- **Hero strip:** lane name in Estedad 800 at size 2xl (the kashida gesture lives here if it ships), count «۱۷ کالا», a 2-line editorial description written by the admin (the legacy texts — lorem ipsum and copy lifted from Digikala — must be rewritten; the full description also renders at the bottom of the page in a collapsed «بیشتر بخوانید» block for SEO), one plate of the lane's top product at the inline-end (desktop), child lanes as sticker chips in a scroll-snap row.
- **Toolbar:** desktop sticky under the header (48 px): result count, sort as a styled `<select>` chip (پربازدیدترین · جدیدترین · ارزان‌ترین · گران‌ترین · بیشترین تخفیف; «پرفروش‌ترین» appears once orders exist), «فیلترها» with an active-count badge, density toggle. Mobile: non-sticky 48 px row + the floating «فیلترها» pill.
- **Filters:** desktop 280 px sticky sidebar at the inline-start; mobile an 85 vh `<dialog>` bottom sheet with a drag handle and a pinned «نمایش ۲۳ کالا» button whose count updates from a HEAD-style fetch (`/wp-json/gtm/v1/count?…`, 300 ms debounce). Contents: «فقط موجودها» switch (default **on** when a lane has > 30 % out-of-stock products, which is the case today at 21 % overall — admin option), «فقط تخفیف‌دارها», brand checkboxes with counts, price range (dual-range slider + two numeric-text inputs, step 50,000, locale digits, ASCII in the URL), attribute facets when present. Plain GET parameters (`?brand=green-lion&min=1000000&max=5000000&instock=1&orderby=price`); JS intercepts submit, fetches the same URL with `X-Requested-With: fragment`, the core plugin returns only the toolbar + grid HTML (one PHP card template, never a JS card template), swapped inside `document.startViewTransition()` where available, `history.pushState`; without JS it is an ordinary form. Filtered combinations get `noindex,follow`; brand-only and sub-lane URLs stay indexable.
- **Grid:** uniform cards (no spreads). Out-of-stock products sort last within any sort order (secondary `menu_order` trick in the core plugin via `posts_clauses`).
- **Empty state:** inline SVG empty shelf (1.75 px ink stroke), saffron sticker «این دسته فعلاً خالی است», line «فیلترها را کم کنید یا دسته‌های دیگر را ببینید», buttons «پاک کردن فیلترها» (primary) and «پرسش از واتساپ», a «محبوب در این دسته» rail from the parent lane.
- **Brand archive:** same template with the brand sticker/logo in the hero and the seal line «همهٔ کالاهای این برند اصل و با ضمانت اصالت هستند».
- **Motion:** grid dims to opacity .4 with `aria-busy` (150 ms) on change; new cards stagger (30 ms, capped at 8); sheet slides up 320 ms; chips tick (scale 160 ms).

### 6.3 Product page
- **Structure:** breadcrumb; desktop ≥ 1024 px 12-col with 32 px gap: gallery 7 columns inline-start (the right side in Persian), buy box 5 columns sticky at `top: calc(var(--header-h) + 16px)`; tablet 6/6; mobile stacked, gallery first.
- **Gallery:** main plate 1:1 (max 640 px, platePdp in dark mode); thumbnail strip of 64 px squares below (scroll-snap, arrow keys, `aria-current`, counter «۳ / ۱۸»); on mobile a native scroll-snap carousel with dots; tap opens a `<dialog>` lightbox with pinch/double-tap zoom via transform and swipe via scroll-snap; `srcset` up to 1200 px; the first image preloaded with `fetchpriority="high"` (the LCP element) and `view-transition-name: product-image` set on the clicked card image at `pageswap` and on this plate at `pagereveal`.
- **Title area:** brand chip (lapis outline → brand archive) + lane chip; H1 = short title in Estedad 700 at size xl; the legacy `subtitle` as the deck (18 px smoke) when it is a sentence (the plugin hides subtitles shorter than 12 characters or all-caps junk like "KORANCO ORG"); the long legacy title stays in `<title>` and meta; rating summary anchored to reviews; SKU small, `dir=ltr`.
- **Buy box** (surface, radius xl, 24 px padding): price row (Vazirmatn 800 28 px + unit; struck old price + sticker per §5.5), the transparency line, stock line (pistachio dot with a 2 s opacity pulse «موجود در انبار» / smoke «ناموجود» + «خبرم کن» / «فقط ۲ عدد مانده» when stock ≤ 3 and managed), variations as sticker chips (price digit-roll on change, 240 ms, least-significant first), quantity stepper, full-width pomegranate 52 px «افزودن به سبد خرید», secondary outline «خرید سریع» (adds and goes to checkout), ghost «سفارش از واتساپ» (wa.me prefilled), then four trust micro-tiles in a 2×2 grid (seal «اصل و اورجینال با ضمانت اصالت کالا», «ارسال از تهران · ۱ تا ۳ روز کاری», «۷ روز ضمانت بازگشت», «پرداخت امن از درگاه بانکی») each opening a popover with the policy text from theme options; share row (copy link, Telegram, WhatsApp, Web Share API when available).
- **Authenticity receipt:** directly under the buy box: plaster slip, dashed clay top rule, dotted mask bottom edge, the 40 px seal, «اصل و اورجینال با ضمانت اصالت کالا» in Estedad 700, three lines (بسته‌بندی پلمب · شناسهٔ گارانتی · فاکتور رسمی) linking to the guarantee page; the seal stamps on first reveal.
- **Mobile sticky buy bar:** once the primary CTA leaves the viewport, a 64 px bar (price at the inline-start, «افزودن به سبد» at the inline-end) replaces the app bar (translateY 100 %→0, 320 ms). Call-for-price swaps it for the contact trio.
- **Below the fold:** sticky in-page chip nav («معرفی» · «مشخصات» · «نظرات» · «مرتبط»; «چرا این گجت؟» first when story meta exists), `aria-current` by IntersectionObserver. «معرفی» = the legacy HTML typeset by `.prose` (max 68 ch, 18 px/1.9, H2 in Estedad 700, tables as ledgers, images on plates), collapsed at 480 px with «بیشتر بخوان» (open by default without JS). «مشخصات» = WooCommerce attributes + the legacy «مشخصه / مقدار» tables parsed once by the plugin into meta, rendered as a two-column ledger with dotted leaders and plaster zebra rows; the legacy `extra_tab` meta as an extra accordion. «نظرات» = aggregate, histogram (bars scaleX 400 ms on reveal), pinned-note review cards, the native WooCommerce form restyled with saffron radio-star stickers (44 px targets). «از همین دسته» = 8 related in-stock products in a rail; cross-sells «با هم بخرید»; «اخیراً دیده‌اید» from localStorage via `/wp-json/gtm/v1/cards?ids=` (server-rendered fragment).
- **SEO:** Product/Offer/AggregateRating JSON-LD (`priceCurrency: "IRR"`, `price` = Toman × 10, availability, brand), BreadcrumbList, hreflang; the page says «تومان» next to every figure.

### 6.4 Cart and checkout (classic shortcode templates, restyled; the block checkout is disabled)
- **Cart page:** container 880 px; line items as ledger rows (plate thumb 80 px, short title, variation, stepper, line total, remove as an outline icon with an undo toast); coupon field collapsed behind «کد تخفیف دارید؟»; summary card sticky on desktop and a sticky bottom strip on mobile (subtotal + pomegranate «ادامه به تسویه حساب»); shipping estimate line; the transparency line; cross-sells rail «با هم بخرید» (max 4). Empty cart: the seal with a saffron «سبد خالی است» sticker, «دیدن محصولات» and «پربازدیدها» rail. No motion beyond button states and the toast.
- **Checkout:** single column (max 880 px), no sidebar on phones; order of blocks: (1) «اطلاعات تماس» — نام و نام خانوادگی, شماره موبایل (required, `inputmode="tel"`, validated as 09xxxxxxxxx after digit normalisation, used as the customer identity), ایمیل (optional; order emails go to email when given, otherwise the admin is told to contact by phone — SMS is §12); (2) «آدرس تحویل» — استان (select from the core plugin's bundled JSON of 31 provinces), شهر (dependent select, falls back to a text input without JS), آدرس (textarea), کد پستی (10 digits, `inputmode="numeric"`), «پلاک / واحد» optional; company, address 2 and country fields removed (country fixed to IR); (3) «روش ارسال» — WooCommerce shipping radios styled as sticker chips with the price; (4) «پرداخت» — the gateway plugin's radio, followed by the reassurance line «برای پرداخت به درگاه امن بانکی منتقل می‌شوید و پس از پرداخت به گجتیم برمی‌گردید» with the gateway logo and the seal; (5) «مرور سفارش» — compact ledger (thumbs, qty, totals) collapsed by default on phones behind «۳ کالا · ۵٬۴۹۸٬۰۰۰ تومان»; (6) terms checkbox (if enabled) and the 56 px pomegranate «پرداخت و ثبت سفارش». Guest checkout on; account creation optional via a checkbox «ساخت حساب برای پیگیری آسان‌تر» (password emailed/generated). The app bar is removed; the summary strip is the sticky bottom element. Errors inline per field + a summary `role="alert"` at the top; WooCommerce's notices are re-templated. jQuery and WooCommerce's checkout scripts load here (and only here and on the account/cart pages).
- **Gateway return / thank-you:** the seal stamps in (350 ms); «سفارش شما ثبت شد» + order number as a saffron sticker; status, items, address; «پیگیری سفارش» link; WhatsApp link prefilled with the order number. Failed payment: pomegranateDeep notice, «تلاش دوباره» and the contact trio.
- **Order tracking page («پیگیری سفارش»):** a core-plugin shortcode form (order number + phone) that shows a vertical timeline (ثبت شد → پرداخت شد → آمادهٔ ارسال → ارسال شد → تحویل شد) with the tracking code when set; no login required; rate-limited.

### 6.5 Account
Login/register as one page with two sticker-chip tabs; login by **phone number or email + password** (the core plugin registers users with the phone as username and maps phone → user on login); «رمز را فراموش کرده‌ام» by email. OTP-by-SMS is not in Phase 1 (§12). Dashboard: ledger-style list (سفارش‌ها, آدرس‌ها, جزئیات حساب, خروج) as a sidebar on desktop and a top chip row on mobile; orders as receipt rows with status stickers; the theme toggle and language switcher repeated. WooCommerce templates overridden in `woocommerce/myaccount/`.

### 6.6 Blog, pages, guarantee page
Posts: prose at 720 px, Estedad 700 titles, reading time, a 2 px saffron reading-progress line (scroll-driven inside `@supports`, absent otherwise), inline product cards via a `[gtm_product id]` shortcode, related guides. Pages: the same prose with an optional plaster hero. Guarantee page («ضمانت اصالت کالا»): the 120 px seal, the admin paragraph, the three verification steps as a numbered ledger, FAQ accordion (`<details>`), contact trio.

### 6.7 404
Paper page, the seal rotated −12° with a saffron «۴۰۴» sticker, «این برگه در تیمچه نیست», search slip, four lane chips, «خانه». The core plugin's 301 map from `old-urls.txt` runs first so real legacy URLs never land here.

---

## 7. Motion system

### 7.1 Principles
1. **«ورق» paper logic:** things arrive from the inline-start like a turning page and leave faster than they arrive (exit = 60 % of entry).
2. **«برچسب» weight:** stickers and the seal slap and stamp with a 5–8 % overshoot; nothing else overshoots.
3. **Motion explains hierarchy and state, never idle time.** The only continuous animations on the whole site are the 2 s in-stock pulse and the 1.2 s skeleton pulse.
4. **One hero-scale animation per viewport.**
5. **Compositor only:** transform and opacity; clip-path for at most two large reveals per page; never width/height/top/left/box-shadow/filter/backdrop-filter/background-position; `font-variation-settings` only where §7.3 allows.
6. **Every animation has a reduced-motion twin** (a fade ≤ 150 ms or nothing).
7. **Prices never move** (no counting, no roll on load; the variation digit-roll is the single exception because the shopper caused it).
8. **Nothing animates on cart, checkout and account** except toasts and button states.

### 7.2 Signature moments
- **«ورق بزن» page turn (cross-document View Transition, Chromium 126+/Safari 18.2+, none elsewhere):** `@view-transition { navigation: auto }`; `::view-transition-old(root)` slides 8 % toward the inline-end and fades (exit easing, 280 ms); `::view-transition-new(root)` slides in from the inline-start (420 ms, inOut); the clicked card image gets `view-transition-name: product-image` in the `pageswap` handler and the PDP main plate in `pagereveal`, so the plate morphs into the gallery; the header is pinned by `brand-bar`; the ticks tile pulses once as the indicator. Back navigation reverses.
- **«مهر اصالت» seal stamp:** scale 1.5→1, rotate −8→0, opacity 0→1, 350 ms stamp easing; 1 px lapis ring scales 1→1.25 and fades over 500 ms; once per element.
- **«برچسب و پرواز» sticker slap and fly-to-cart:** button label → ticks spinner (600 ms max, replaced by the response) → check + a slapped saffron «اضافه شد» sticker; simultaneously a clone of the plate image flies to the header cart along a straight transform path (520 ms, scale 1→.2, `cubic-bezier(.2,.8,.2,1)`), the badge digit rolls, and on desktop the drawer peeks; on mobile a toast appears instead. Reduced motion: check + badge + toast.
- **«شب‌بازار» night switch:** same-document View Transition; `::view-transition-new(root)` revealed by `clip-path: circle(0 at var(--x) var(--y))` → `circle(150vmax at …)` from the toggle's position, 500 ms inOut; fallback 200 ms crossfade.
- **«سردر» masthead open:** §2.10, once per session, < 700 ms, never delays LCP.
- **Grafted:** variation digit-roll (Vitrine), the «نمایش ۲۳ کالا» count that updates live in the filter sheet, the strike line that draws on sale cards.

### 7.3 The living kashida (conditional enhancement)
Ships only if the Estedad variable build in the repo carries the KSHD axis (verify with fontTools before deciding; the static 700/800 files used for text do not). If it ships: a third file `Estedad[KSHD].woff2` subset to the hero/category H1 glyphs is loaded lazily on `(hover: hover)` desktops only; the hero H1 and the category H1 get `font-variation-settings: "KSHD" 100` → `130` on hover (320 ms) with `min-inline-size` reserved at the stretched width so nothing reflows; disabled under reduced motion; nowhere else. If it does not ship, the gesture is the saffron underline that draws under the same headlines on hover (scaleX, 320 ms) — and the spec is complete without the kashida.

### 7.4 Choreography rules
Order inside a component: plate/image → headline → meta → CTA at 60 ms intervals; a section header before its grid; reveals fire once at 15 % visibility with `rootMargin: 0 0 -10% 0` and `unobserve`; items already in the viewport at load reveal immediately without stagger; stagger 50 ms capped at 8 (ninth onward together). Scroll-driven animations (`animation-timeline: view()`) only inside `@supports`, only for the hero parallax and the story stickers, only ≥ 1024 px. Sheets: translateY 100 %→0 in 320 ms inOut, out in 200 ms exit, scrim opacity 200 ms; swipe-down-to-dismiss follows the pointer with transform in rAF and closes past 80 px / fast velocity.

### 7.5 Reduced motion and data
`@media (prefers-reduced-motion: reduce)` and `html[data-motion="reduced"]` (footer toggle, localStorage): all duration tokens 1 ms, transforms removed, opacity fades ≤ 150 ms kept, no parallax, `@view-transition { navigation: none }`, the skeleton pulse becomes static, the in-stock pulse static. `prefers-reduced-data` / `Save-Data` (`html.lite` set by the inline script from `navigator.connection.saveData`): no hover second image, hero collage reduced to one plate, no View Transitions.

### 7.6 Performance budget for motion
Transform/opacity only; `will-change` applied just before an animation and removed on `finish`; at most 6 elements with `will-change` at any moment; IntersectionObserver reveals unobserve; `contain: layout paint` on cards; `content-visibility: auto` on below-fold sections (which also pauses their animations); the rule that any animation measured above 4 ms/frame of main-thread or raster time on a mid-range Android in DevTools is cut. One `motion.js` ES module exporting `reveal()`, `stamp()`, `slap()`, `flyToCart()`, `rollDigits()` built on WAAPI `element.animate()` guarded by `prefersReduced()`; CSS keyframes in `@layer motion`.

### 7.7 Forbidden
Counting numbers, animated prices on load, images moving on scroll beyond 12 px, marquees, glows, blur transitions, bouncing CTAs, autoplaying carousels, animated gradients, parallax on phones, animated box-shadow/filter/height, anything on checkout, any blocking loader.

---

## 8. RTL/LTR and trilingual rules

- **RTL-first authoring:** every template is written for Persian; CSS uses only logical properties (`margin-inline-start`, `padding-block`, `inset-inline-end`, `border-start-start-radius`, `text-align: start`, `inset-inline: 0` for fixed bars); flex/grid flow follows `dir`; `<html dir lang>` is set per page by the core plugin (fa/ar rtl, en ltr). A single custom property `--dir` (−1 rtl, 1 ltr) drives directional transforms (slide-ins, fly-to-cart, sticker tilt `--tilt: calc(-6deg * var(--dir) * -1)`), and `.is-directional { transform: scaleX(var(--dir)) }` mirrors chevrons, back/next arrows, breadcrumb separators and "send".
- **What never mirrors:** product photos and brand logos; numbers, prices, phone numbers, SKUs, model codes, URLs, emails (wrapped in `<bdi>` or `<span dir="ltr">`); the wordmark and seal geometry; non-directional icons (search, cart, check, clock, heart, phone, star); media and lightbox gestures; the reading-progress line (grows from the inline-start in both directions, which is correct).
- **What changes for English (LTR):** asymmetric grids mirror; rails start at the left; drawers come from the right edge (inline-end); sticker tilt flips sign; fonts switch to Bricolage Grotesque (display) + Instrument Sans (text), line-height 1.6, uppercase label tracking .08 em allowed; the hero is capped at 4.75 rem; the lockup is Latin-first with the small Persian wordmark above; digits 0–9 with commas and "Toman" after the number (explained once in the footer: "Prices in Iranian Toman"); Gregorian dates; the masthead copy is written, not translated, by a native writer; the suggest endpoint searches English titles; WhatsApp/Telegram prefills switch language; the card «+» stays at the plate's bottom inline-end (bottom-right in LTR) — acceptable once the photos are cleaned (§9).
- **Arabic (RTL):** same layout as Persian; `lang="ar"` switches Vazirmatn's `locl` forms (ك/ي, ٤٥٦ digit shapes); display in Vazirmatn 800 until Estedad's Arabic forms (ة ك ي ء) are verified; Arabic-Indic digits with U+066C («١٬٢٥٠٬٠٠٠ تومان», first mention «تومان إيراني»); the base-currency hint defaults **on** with AED on /ar/ because Gulf visitors cannot price a Toman; Arabic runs ~10 % longer, so buttons and chips reserve width and never truncate; line-height 1.9; no kashida gesture; «» quotation marks shared; Gregorian dates; all commerce copy (سلة التسوق، إضافة إلى السلة، الدفع، تتبّع الطلب) by a native Gulf-Arabic writer, never machine output on trust-critical lines.
- **Persian text hygiene:** a content filter in the core plugin normalises Arabic ي/ك to Persian ی/ک and inserts ZWNJ for common suffixes (می‌، ها، تر، ترین) in titles, excerpts and descriptions; Jalali dates via the plugin's converter; «٪» after the figure; «٫» for the rare decimal (ratings only).
- **Language architecture (core plugin, no Polylang/WPML):** one product post per language linked by a `gtm_translation_group` id (stock and price are NOT shared across posts — therefore en/ar product posts are *virtual mirrors*: the plugin syncs stock, price, currency and SKU from the fa post on every save and on every rate run, so there is exactly one source of truth; only text fields live on the translated post). Hidden `gtm_lang` taxonomy on products, posts and pages; term meta on lanes/brands for translated names/slugs/descriptions; rewrite rules for `/en/` and `/ar/`; every front-end query filtered by language; `switch_to_locale()` per request loading WooCommerce's ar and fa .mo files; theme strings via the normal text domain; hreflang + x-default on every page; one sitemap per language; three registered menus; a «ترجمه‌ها» metabox to create/link copies; untranslated products hidden from /en/ and /ar/ (never machine-shown); cart, checkout and emails inherit the language from the URL. Phase 1 builds this infrastructure and ships fa only.

---

## 9. Imagery guidelines for the existing packshots

- **What exists:** 409 images (3.8 per product on average, max 18), 800 × 800 WebP, white backgrounds, mostly retail boxes with the product in front; every file opened carries the old «گجتیم / GADGETIM.IR» pixel wordmark bottom-right and a translucent GT ghost in the centre. A handful are on grey gradients with real shadows.
- **Launch blocker:** no image with the old marks may ship on the new site (the client's rule, and the plate treatment makes them more visible). Resolution in order of preference: (1) the client supplies the original un-watermarked exports; (2) a one-time clean-up pass (inpainting the centre ghost and the bottom-right band, then re-whitening) reviewed image by image; (3) re-shoot the 20 hero/feature products. Interim rule for any image not yet cleaned: it is excluded from the hero collage, story spread and edits, and shown only on cards/PDP where badges stay in the top corners.
- **Processing pipeline (one-time, documented script):** square canvas 1200 × 1200, product centred with 8 % margin, background pure white #FFFFFF (the client's existing whitening workflow), export WebP q82 master → WordPress regenerates 320/480/640/800/1200 WebP (and AVIF when Imagick supports it) with `srcset`. Card thumbnails are never requested above 640 px (480 on phones).
- **Packshot flag:** on upload the core plugin samples a 4 px strip along all four edges; if ≥ 97 % of sampled pixels are ≥ #F5F5F5 the attachment gets `_gtm_packshot=1` (multiply allowed in light mode); the media library shows a toggle to override. Dark renders and lifestyle shots stay unflagged.
- **New watermark (optional):** the outline seal at 10 % opacity, 6 % of the canvas width, bottom inline-end, applied to the master only if the client wants a watermark at all (recommended: none; the seal on the card already claims authenticity).
- **Aspect and alt text:** 1 : 1 everywhere (cards, gallery, OG); alt = short title + brand (the backup's alt data is mostly empty and is filled by the plugin from the short title until edited).
- **Hero cut-outs:** 3–6 transparent-background WebPs (≤ 90 KB at 1200 px) prepared manually for the masthead collage and the story spread; everything else stays on plates.
- **Brand logos:** typographic stickers until the client supplies official monochrome SVGs; Apple/Samsung may stay text-only for trademark hygiene.

---

## 10. Accessibility (WCAG 2.2 AA)

- Contrast: every token pair listed in §3 was chosen for ≥ 4.5 : 1 text and ≥ 3 : 1 non-text in both themes; pistachio text is used only ≥ 14 px bold or as an icon next to text; saffron is never text on paper; any new tint must be re-checked.
- Focus: 2 px lapis ring with 2 px offset on every interactive element, never animated, never removed; skip link to `#main` as the first focusable element; a second skip link to the product grid on category pages.
- Structure: landmarks (header/nav/main/aside/footer), one H1 per page, logical heading order, `aria-current` on nav and pagination, `aria-expanded` on disclosures, plain anchors in menus (no ARIA menu roles).
- Dialogs: every sheet, drawer, lightbox and popover-on-mobile is a native `<dialog>` with `showModal()` (focus trap, Escape, `inert` background), labelled by its heading, returning focus to the trigger; drag-to-dismiss is additive.
- Live regions: cart changes, filter result counts and form errors announced via `aria-live="polite"`; errors `role="alert"`.
- Targets ≥ 44 × 44 px with ≥ 8 px spacing; text ≥ 12 px (stickers) and ≥ 14 px for anything tappable; inputs ≥ 16 px on phones.
- Motion: reduced-motion parity, the footer toggle, no flashing, no autoplay; the marquee is gone.
- Images: meaningful alt text; decorative SVGs `aria-hidden`; the seal has a text alternative; icon buttons have `aria-label`.
- Forms: visible labels, `autocomplete` attributes (tel, postal-code, given-name…), inline errors tied with `aria-describedby`, digit sets accepted in any script.
- Rotated stickers: text stays ≥ 12 px and un-rotated on compact cards; pinned-note reviews never exceed ±2° tilt and keep 16 px text.
- Testing: axe-core on 8 templates in CI, a keyboard walkthrough script (header → mega → sheets → PDP → checkout), NVDA + Chrome and TalkBack + Chrome Android manual passes before launch, both themes, all three languages.

---

## 11. Performance budget

Targets on a mid-range Android (Moto G-class throttling, 4G, Lighthouse CI + WebPageTest): **LCP < 2.0 s, INP < 150 ms, CLS < 0.05, TBT < 150 ms**; home ≤ 650 KB transferred on first visit, ≤ 120 KB on repeat.

| Asset | Budget (gzip/br) | Notes |
|---|---|---|
| HTML | ≤ 60 KB | server-rendered, cached by the page-cache plugin for guests |
| CSS | ≤ 45 KB | one `style.css` with `@layer reset, tokens, base, components, utilities, motion`; ≈ 10 KB critical (tokens, header, hero, card) inlined |
| JS | ≤ 35 KB total | `app.js` 8 KB (`type="module"`, modulepreload: header, sheets, cart badge, theme); `reveal.js`, `search.js`, `gallery.js`, `cart.js`, `filters.js`, `motion.js` dynamically imported on interaction or idle; no jQuery except cart/checkout/account; WooCommerce's cart-fragments, selectWoo, block styles, dashicons and emoji dequeued elsewhere |
| Fonts fa | ≤ 200 KB first visit | Vazirmatn[wght] subset ≈ 90 KB + Estedad 700 ≈ 55 KB + Estedad 800 ≈ 55 KB; immutable cache |
| Fonts en | ≤ 110 KB | Bricolage ≈ 60 KB + Instrument Sans ≈ 45 KB |
| Fonts ar | ≤ 90 KB | Vazirmatn only |
| Hero image | ≤ 90 KB | first plate preloaded, `fetchpriority="high"`; collage images 2 and 3 lazy |
| Card images | ≤ 40 KB each | ≤ 480 px on phones, ≤ 640 px desktop |
| Icons | ≤ 12 KB | one inline SVG sprite, ~30 symbols, 1.75 px stroke |

Rules: `width`/`height` + `aspect-ratio` on every image; font metric overrides on fallbacks; fixed header/app-bar heights; skeletons at final size; `content-visibility: auto` + `contain-intrinsic-size` on below-fold sections; `backdrop-filter` only on desktop pointer devices; passive listeners; rAF-throttled scroll logic; debounced, abortable search; `scheduler.yield()` in fragment swaps; the 4 ms/frame rule. Caching: full HTML for guests keyed by URL (language prefixes are distinct URLs); cart, checkout, account and any request with a non-empty cart session bypass; `/assets` with file-hash query strings and immutable headers; the core plugin purges on rate change, call-for-price toggle, product/term save and theme-option save; suggest, count and rate endpoints use transients. Measurement: a 1 KB inline web-vitals snippet posting LCP/INP/CLS to `/wp-json/gtm/v1/vitals` (stored in a plugin table, shown in wp-admin); Lighthouse CI budgets in the existing GitHub CI; sign-off only on a real mid-range Android over 4G. Hosting in Iran: no CDN assumed, HTTP/2 assumed, WebP-only tolerated, AVIF opportunistic.

---

## 12. Open questions for the client

1. **Photos:** do un-watermarked originals exist for the 409 images? If not, do you approve a paid clean-up pass (inpainting + re-whitening) with per-image review, and a re-shoot of ~20 hero products?
2. **Login by SMS code (OTP):** Iranian shoppers expect it, but it needs an SMS provider (a paid service such as Kavenegar/Ghasedak, integrated inside our core plugin — no extra plugin). Approve the service and its cost, or launch with phone + password?
3. **Trust claims:** confirm the exact return window («۷ روز»?), delivery promise («ارسال از تهران، ۱ تا ۳ روز کاری»?), the three authenticity verification steps, and the guarantee page text. Nothing goes live as a placeholder.
4. **Rounding rule:** charm rounding (…۹٬۰۰۰ / …۹۹٬۰۰۰) is proposed as default; confirm, and confirm the 15 % sticker / 10 % strike thresholds.
5. **Price reconciliation:** several legacy rows do not equal base × 78,000 (e.g. 30 AED stored as 1,125,000 Toman) and some bases are fractional (14.16666 AED). Should the migration trust the AED bases (and recompute Toman) or the Toman figures (and back-compute bases)?
6. **Rate update process:** who updates rates, how often, and should a change be scheduled (e.g. 09:30 daily) rather than instant?
7. **Base-currency hint:** show it on the PDP by default in Persian (proposed on, factual wording) or keep it admin-only?
8. **Short titles and stories:** will you write `_gtm_short_title` for 107 products (we provide the auto-stripped fallback) and stories for the «چرا این گجت؟» feature?
9. **Category copy:** the old descriptions (lorem ipsum, Digikala text) must be rewritten; who writes them, and do you approve the 9-lane cleaned tree (merging the duplicate «هندزفری» terms and fixing the menu items that point at the wrong terms)?
10. **Brand logos:** can you obtain official vector logos from the distributors? Until then brands are typographic stickers.
11. **Phase 2 languages:** English and Arabic need human translation of 107 products, 9 lanes, guides and UI copy (Gulf Arabic). Confirm the budget and whether en ships before ar.
12. **Dark mode default:** proposed default is light with an auto/day/night toggle. Agree?
13. **eNamad / ساماندهی / gateway:** provide the official embed codes and the gateway plugin name so the trust row and checkout reassurance use real assets.
14. **Newsletter:** keep the core-plugin newsletter (double opt-in by email) or defer it?
15. **Sign-off path:** approve a static style tile + clickable home and PDP prototype (real fonts, palette, three cleaned products, the motion) before WordPress templates are built.

---

### Appendix A — Core plugin surface (so engineers can size it)
`gadgetim-core` (prefix `gtm_`): currencies and rates (options UI, Action Scheduler write-through, rounding, transparency timestamp); product meta (`_gtm_currency`, `_gtm_base_regular`, `_gtm_base_sale`, `_gtm_short_title`, `_gtm_story_*`, `_gtm_show_sale`, `_gtm_packshot` on attachments, parsed spec table); call-for-price (options + enforcement hooks); formatting (`gtm_format_price`, `gtm_digits`, Persian normaliser, Jalali); REST (`suggest`, `count`, `cart`, `cards`, `vitals`, `stock-alert`, `subscribe`); cart cookie; filters/fragment responses; out-of-stock-last ordering; JSON-LD; hreflang/sitemaps/rewrites/translation groups/locale switching; province–city data; order-tracking shortcode; cache-purge adapters; 301 map; packshot edge sampling on upload. Phase 1 excludes the en/ar admin UI beyond the metabox and excludes OTP.

### Appendix B — Theme file layout
`timcheh/` classic theme: `style.css` (layers), `assets/fonts/*`, `assets/js/{app,reveal,search,gallery,cart,filters,motion}.js`, `assets/icons/sprite.svg`, `template-parts/{header,footer,card,sticker,seal,price,receipt,…}.php`, `woocommerce/{archive-product,single-product,cart,checkout,myaccount}/…` overrides, `inc/{setup,enqueue,templates,walker-mega}.php`, `page-templates/{guarantee,tracking}.php`. No build step; the only offline step is `pyftsubset` for fonts (command in `README`).
