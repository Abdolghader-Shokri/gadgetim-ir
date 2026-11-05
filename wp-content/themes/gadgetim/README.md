# Gadgetim — Aurora «شفق» theme

Classic WordPress theme for gadgetim.ir, rebuilt on the Aurora prototype the client chose
(`docs/prototypes/aurora/`). Light only, colourful, animated, RTL-first (fa, ar) with an LTR `/en/`.
Everything renders on the server; JavaScript is an enhancement (vanilla ES modules, no jQuery, no CDN).
Data, pricing, languages and settings come from the **gadgetim-core** plugin (`Requires Plugins`);
`inc/compat.php` keeps the storefront alive without it.

Binding specs: `docs/decisions.md`, `docs/architecture.md` (§2.3, §3.10, §4.5, §5), functional parts of
`docs/design-spec.md` (§5.5 price/sale, §5.7 call-for-price, §5.10 cart badge, §8 RTL, §10 a11y, §11 perf).

---

## 1. Files and ownership

| Path | What | Owner |
|---|---|---|
| `style.css` | theme header only | foundation |
| `functions.php` | constants, version, requires `inc/*.php`, then page layers when present | foundation |
| `inc/compat.php` | fallbacks for every plugin helper (`gadgetim_price()`, `gadgetim_lang()`, …) | foundation |
| `inc/setup.php` | supports, image sizes, menus fallback, `gadgetim_page_kind()`, body classes, head clean-up, favicons | foundation |
| `inc/settings.php` | Storefront settings tab (`theme.*`), `gadgetim_list_setting()`, `gadgetim_campaign_end()` | foundation |
| `inc/assets.php` | CSS layers, script modules, fonts, `#gadgetim-config`, sprite, WC/block dequeue | foundation |
| `inc/images.php` | `sizes` contexts, LQIP, eager budget, LCP preload, watermark mask helper | foundation |
| `inc/template-tags.php` | all UI helpers and the product card renderer | foundation |
| `inc/menus.php` | menu locations, category groups (mega/drawer/search), stories, brands, footer links, languages | foundation |
| `inc/woocommerce.php` | core WC wiring: wrapper, breadcrumbs, loop card, grid `<ul>`, sorting | foundation |
| `inc/newsletter.php` | footer newsletter admin-post handler | foundation |
| `header.php`, `footer.php`, `templates/header/*`, `templates/footer/*` | site chrome | foundation |
| `index.php`, `page.php`, `single.php`, `archive.php`, `search.php`, `404.php`, `templates/loop-post.php` | minimal on-brand fallbacks | **pages** builder may polish |
| `inc/home.php`, `front-page.php`, `templates/home/*`, `assets/css/home.css` | home | **home** builder |
| `inc/archive.php`, `woocommerce/archive-product.php`, `woocommerce/content-product.php`, `woocommerce/loop/*`, `assets/css/archive.css`, `assets/js/modules/filters.js` | shop / category / brand / product search | **archive** builder |
| `inc/product.php`, `woocommerce/single-product*`, `templates/product/*`, `assets/css/product.css`, `assets/js/modules/gallery.js` | product page | **product** builder |
| `inc/commerce.php`, `woocommerce/cart/*`, `woocommerce/checkout/*`, `woocommerce/myaccount/*`, `woocommerce/notices/*`, `assets/css/commerce.css` | cart, checkout, account | **commerce** builder |
| `inc/pages.php`, `templates/pages/*`, `assets/css/pages.css` | pages, posts, search, 404 | **pages** builder |
| `inc/fx.php`, `assets/css/fx.css`, `assets/js/modules/fx.js` | the delight layer (intro choreography, hero FX, scroll choreography, tilt, magnets, confetti, ambient sky, progress, view transitions) — see §2 "FX layer" | **fx** |

`functions.php` requires `inc/home.php`, `inc/archive.php`, `inc/product.php`, `inc/commerce.php`,
`inc/pages.php` **after** the foundation files, each only when readable — so a page layer can
`remove_action()` / `remove_filter()` anything the foundation wired (e.g. `gadgetim_wc_loop_card`).
WooCommerce template overrides go in `woocommerce/` copied from the installed WC 11.2.1 with the
`@version` header kept (none exist yet; the loop card is wired through hooks).

Conventions: PHP 8.1 `declare( strict_types=1 )`, text domain `gadgetim`, English source strings,
escape late (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize early, prefix `gadgetim_`,
tabs, LF. Persian digits everywhere in UI numbers: always print numbers through `gadgetim_number()`,
`gadgetim_digits()`, `gadgetim_percent()`, dates through `gadgetim_date()` (Jalali in fa).

---

## 2. CSS

### Layers (declared once, first thing in the inlined `tokens.css`)

```
@layer reset, tokens, base, layout, components, pages, fx, utilities;
```

| File | Layer(s) | Loaded |
|---|---|---|
| `assets/css/tokens.css` | `tokens` | inlined in `<head>` with the `@font-face` rules |
| `assets/css/base.css` | `reset`, `base` | always |
| `assets/css/components.css` | `components`, `utilities` | always |
| `assets/css/layout.css` | `layout` (container, grids, page shell, aurora) + **site chrome in `components`** | always, after components.css |
| `assets/css/{home,archive,product,commerce,pages}.css` | `pages` | only on their page kind (and only once they contain rules) |
| `assets/css/fx.css` | `fx` + reveal variants in `utilities` | always, last |

Page layers: wrap every rule in `@layer pages { … }`. They win over components without `!important`.
The site chrome (header, mega, footer, bottom nav) sits in the components layer after `components.css`,
so contextual rules (`.sugg__price .price__now`) refine generic components at equal or higher specificity.

Rules: **logical properties only** (`inset-inline-start`, `margin-inline`, `padding-block`,
`border-start-end-radius`, `text-align: start`). Directional transforms use `--dir` (`-1` RTL, `1` LTR):
`translate: calc(4px * var(--dir)) 0` moves "forward". Arrows/chevrons get the class `ic--dir` (mirrored in
LTR). Light only — no dark tokens, no `prefers-color-scheme`. Motion: transform/opacity; every
animation is neutralised under `prefers-reduced-motion` (utilities layer); `html.lite` (Save-Data)
stops the aurora drift. Real `backdrop-filter` only ≥ 768 px with a fine pointer; phones get pre-tinted
solid glass. Breakpoints: base phone, `768px` tablet, `1000px` laptop (full header + mega), `1100px` desktop.

### Tokens (`tokens.css`)

Colours `--ink #17133b`, `--ink-2`, `--muted`, `--muted-2`, `--line`, `--line-2`, `--bg #f6f6fc`, `--card`,
`--soft`, `--soft-2`, `--soft-3`, `--sk`; aurora `--indigo #4f46e5`, `--violet #7c3aed`, `--purple`,
`--fuchsia #c026d3`, `--pink #ec4899`, `--rose #e11d48`, `--amber #f59e0b`, `--gold`, `--sky`, `--teal`,
`--green`, `--green-2`, `--warn`.
Gradients `--grad` (main), `--grad-warm`, `--grad-cool`, `--grad-gold`, `--grad-green`, `--grad-pink`,
`--grad-text`, `--grad-band` (amazing-offers band), `--grad-hot` (discount), `--grad-sticker`, `--grad-ring`
(story ring), `--grad-border` (card hover border), `--grad-soft`.
Glass `--glass`, `--glass-strong`, `--glass-line`. Radii `--r-xs 10` `--r-sm 14` `--r-md 18` `--r-lg 24`
`--r-xl 32` `--r-pill`. Shadows `--sh-1`, `--sh-2`, `--sh-3`, `--sh-pop`, `--sh-glow`.
Motion `--ease`, `--ease-io`, `--spring`, `--dur-1/2/3`. Type `--font` (Vazirmatn VF), `--display`
(Estedad VF), `--fs-xs … --fs-4xl`. Spacing `--s-1 (4) … --s-9 (56)`. Layout `--gutter` (16/24/32),
`--container 1360px`, `--hdr-h` (sticky header height for scroll padding), `--bnav-h`. Z scale
`--z-hdr 60`, `--z-promo 61`, `--z-mega 58`, `--z-to-top 65`, `--z-bnav 70`, `--z-sugg 80`, `--z-toast 85`,
`--z-drawer 95`, `--z-fly 200`, `--z-skip 300`.

### Component catalogue (class names)

| Component | Markup / classes | Helper |
|---|---|---|
| Container, grids | `.container`, `.page-shell`, `.pgrid` (2/3/5 cols; `--cols`), `.grid-2` | — |
| Section head | `.sect` (section spacing) › `.sect__head[--center]` › `.sect__ic[--warm\|--cool\|--gold\|--pink\|--green]`, `.sect__titles` › `.sect__title`, `.sect__sub`; `.sect__more`; `.rail-nav` | `gadgetim_section_head()` |
| Buttons | `.btn` + `--white`, `--soft`, `--outline`, `--ghost`, `--success`, `--dark`, `--sm`, `--lg`, `--block`, `--glow` (looping sweep); `.is-loading`; hover lift + sweep, press scale, ripple | `gadgetim_button()` |
| Icon buttons | `.icon-btn` (44 px), `.glass-btn` (round glass; `.ic-pause`/`.ic-play` swap with `aria-pressed`) | — |
| Icons | `<svg class="ic"><use href="#i-name"/></svg>`; `.ic--dir` mirrors in LTR | `gadgetim_icon()` |
| Counter badge | `.badge` (`.is-empty`, `.bump`), `.badge--cart` (amber) | — |
| Tag | `.tag` + `--new`, `--hot`, `--success`, `--warn`, `--dark`, `--glass` | `gadgetim_badge()` |
| Discount pill | `.off` (+ `--sm`, `--lg`), shown at ≥ sticker threshold (15 %) | `gadgetim_discount_pill()` |
| Sticker | `.sticker` (round amber, tilted, wobble; `--lg`, `--static`), `.deal` (tilted yellow note) | `gadgetim_sticker()` |
| Chips | `.chip-q` (`.is-active`, `[aria-pressed=true]`) | — |
| Price | plugin markup `.price` › `.price__now` (`bdi` + `.price__unit`), `del.price__was`, `.price__off`, `.price__base`; `.price--lg` shows `.price__off`; cards hide the base hint | `gadgetim_price_html()`, `gadgetim_the_price()` |
| Rating | `.rating` › `.stars` (`--rating` 0–5) › `.stars__track` + `.stars__fill`, `.rating__n` | `gadgetim_rating_stars()` |
| Product card | `.pc` › `.pc__in` › `.pc__media` (`.pc__img`, `.pc__img--has2`, `.pc__img2` crossfade), `.pc__flags`, `.pc__wish`, `.pc__body` › `.pc__brand`, `.pc__title`, `.pc__meta` (`.pc__low`, `.pc__rate`), `.pc__foot`; `.pc--rail`, `.is-out`, `.pc--sk` (skeleton) | `gadgetim_product_card()` |
| Add button | `.add` (plus → check `.is-done`, `.is-busy`), `--wide`, `--sm`, `--contact` (WhatsApp), `--options` | `gadgetim_add_button()` |
| Compact card | `.pcx` › `.pcx__img`, `.pcx__body`, `.pcx__t`, `.pcx__foot` | variant `compact` |
| Ranked row | `.rk[--top]` › `.rk__img`, `.rk__n` (outlined number; gradient for top 3), `.rk__body`, `.rk__t`, `.rk__p` | variant `rank` |
| Mini row | `.pmini` › img, `.pmini__t`, `.pmini__p` | variant `mini` |
| Rail | `ul.rail[id]` (`--col`, `--gap`, `--fade`), `.prail` (product rail presets); `.is-scrollable/.at-start/.at-end` edge fade; buttons `.rail-btn` (light) / `.rail-arrow` (on gradients) | `gadgetim_rail_nav()` |
| Story circle | `a.story[--hot]` › `.story__ring` › `.story__img`; `.story__name`; `.is-seen` | data: `gadgetim_story_categories()` |
| Carousel | `.carousel.carousel--fade[data-carousel]` › `.carousel__track` › `[data-slide].is-active`; `.dots[data-carousel-dots]` › `.dot` › `.dot__bar` (progress) | carousel.js |
| Countdown | `.cd.cd--flip` (flip cards) / `.cd.cd--chip` (dark chips) › `.cd__u` › `.cd__d[data-u]`, `.cd__l`; `.cd__sep`; `.is-ended` | `gadgetim_countdown()` |
| Drawer | `dialog.drawer[data-drawer]` (start side) / `.drawer--end`; `.drawer__head`, `.drawer__body`, `.drawer__foot`, `.drawer__links`, `.drawer__wa`, `.drawer__count` | drawer.js |
| Modal / sheet | `dialog.modal[data-drawer]` (`.modal--sheet` slides up on phones) | drawer.js |
| Accordion | `ul.acc[data-accordion]` (`="multi"` allows several) › `.acc__btn[aria-expanded][aria-controls]` (`.acc__ic`, `.acc__label`, `.acc__chev`) + `.acc__panel` (inert when closed) › `div` › `.acc__grid` or `.acc__text` | app.js |
| Tabs | `[role=tablist].tabs` › `button.tab[role=tab][aria-selected][aria-controls]`; panels toggle `hidden` (`data-tabs="shared"` keeps one shared panel) | app.js |
| Tooltip | `[data-tip="text"]` on a focusable element | CSS |
| Toast | `#toasts` › `.toast[--success\|--error\|--plain]` | toast.js |
| Cart / wish line | `.citem`, `.citem__t`, `.citem__row`, `.citem__price`, `.qty`, `.cart-total`, `.cart-save`, `.cart-empty` | cart.js |
| Skeleton | `.sk` (shimmer), `.sk-line`, `.pc--sk` | `gadgetim_card_skeleton()` |
| Breadcrumbs | `nav.crumbs` › `ol.crumbs__list` › `.crumbs__item` › `.crumbs__link` / `.crumbs__current`, `.crumbs__sep` | `gadgetim_breadcrumbs()`, `gadgetim_render_crumbs()` |
| Pagination | `nav.pager` › `.pager__list` › `.pager__num[.is-current]`, `.pager__arrow`, `.pager__gap` | `gadgetim_pagination()` |
| Empty state | `.empty` › `.empty__ic` / `.empty__art`, `.empty__t`, `.empty__p`, `.empty__actions` | `gadgetim_empty_state()` |
| Panel | `.panel`, `.panel--glass` | — |
| Notices | WooCommerce `.woocommerce-message/-info/-error` restyled | — |
| Contact slip | plugin `.gtm-contact[--compact\|--full]` restyled | `gadgetim_price_html()` in contact mode |
| Watermark mask | `.wm`, `.wm--fade` (+ SVG filter `#wm-lift` in the sprite) | `gadgetim_decor_image()` |
| Prose | `.prose` (editor content) | — |
| Utilities | `[data-reveal]` (+ `style="--i:n"`), `.u-center`, `.u-ltr`, `.u-muted`, `.u-grad-text`, `.u-mt`, `.u-hide-phone`, `.u-only-phone`, `.sr-only`, `.nw` | motion.js |

### FX layer (`fx.css` + `fx.js` + `inc/fx.php`)

The client asked for much more, much more visible motion. Everything here is decoration on top of the
components above: transforms/opacity first (the individual `translate`/`scale`/`rotate` properties, so page
transforms keep working), filter/clip-path only for short moments, never a live blur on large areas on phones.

- **Intro** — `html.fx-intro` (pre-paint script: first page of a browsing session or a reload; fx.js drops it
  at 2.2 s): promo drops, logo spins in + word sweep + glint, search/actions/nav cascade, header line draws,
  bottom nav springs up. Home on every load (and on every slide change, timed by `--fx-t0` / `.fx-moved`):
  panel settles, headline words (`gadgetim_fx_words()` → `.fxw`, `--w`) and the gradient line go
  blur→sharp **without ever starting from opacity 0** (LCP), packshots spring in, stage glow pulses, rings
  scale in, story rings draw (`.story__draw`, stroke-dashoffset) before the turning conic takes over. < 900 ms.
- **Hero** — `.slide__aura` (turning conic ring), `.slider__fx` (light orbs `i` + rising sparkles `b`),
  pointer light `.slider::after`, packshot 3D tilt from carousel.js `--px/--py` (perspective on `.hp`, so the
  multiply blend survives), sway on touch, circular clip-path wipe from the packshot (`--wx/--wy`), zoom-blur,
  CTA halo + recurring glint inside `.gt`.
- **Scroll** — `[data-reveal]` variants (in `utilities`: heads slide + blur, cards flip up, ranked rows slide,
  tiles rotate, banners zoom, stats pop; spring settle), `[data-fx]` reveals for blocks without data-reveal
  (fx.js; hidden only once `html.fx-ready` is set), `.sect__tt` gradient underline draws, `.sect__ic` pops,
  `[data-count-to]` count-ups (offers facts too), parallax through `animation-timeline: view()/scroll()` inside
  `@supports` (fx.js drives it elsewhere), desktop floating shapes beside four home sections.
- **Cards / buttons** — `.fx-tilt` (`--rx/--ry`, specular `--sx/--sy` on `.pc__media::after`), turning
  gradient border (`@property --fx-a`), image shine `.pc__media::before`, pill pop/wobble, add-button
  squash + halo + drawn check, confetti `.fx-burst`, cart ring `.fx-got`, `+1` `.fx-plus`; magnets (Web
  Animations on `translate`), ripples `.fx-ripw`, shimmer on primary CTAs on screen (`.fx-vis`).
- **Ambient** — `.fx-sky` (fixed, three pre-blurred blobs; `body` is transparent so it and `.aurora` show),
  `.fx-progress` (top bar), `.to-top__ring`; offers band glow/flame (`.offers__flame`)/ribbon sweep/gift
  shine (`.gift__shine`)/3D flip digits; banner rays + floating badges + pointer light; mega menu, drawer and
  bottom-nav (`.fx-morph`, `.fx-tap`) choreography; footer flowing top border and social jelly; PDP gallery,
  buy box and celebration (`.fx-yay`); archive hero and first rows; tinted skeletons; cross-document view
  transitions (opt-in printed by `inc/fx.php`, not on cart/checkout, skipped across a language switch).
- **Discipline** — loops pause off-screen (`.fx-off`), in hidden tabs (`html.fx-hidden`) and behind overlays;
  fewer loops on phones; `html.lite` and reduced motion get a calm static version (fx.js does nothing then);
  one-shot entrances are keyed on `:not(.fx-shot)` (the pre-paint script marks every element whose `fx-*`
  animation ended), so a breakpoint change never replays them; intros use `animation-fill-mode: backwards`.

Site chrome (layout.css): `.promo` (`.promo__ticker[data-ticker]`, `.promo__links`), `.hdr` (`.is-scrolled`,
`.is-tucked`), `.hdr__bar`, `.logo` (`.logo__mark`, `.logo__word`, `.logo__latin`, `.logo--ftr`), `.search`
(`.search__cat`, `.search__go`), `.sugg` (`.sugg__h`, `.sugg__opt`, `.sugg__chips`, `.sugg__foot`,
`.sugg__all`, `.sugg__none`), `.act` (`--account`, `--wish`, `--cart`), `.hdr__nav`, `.mega-btn`,
`.nav-links` (`.nav-hot`), `.nav-extra`, `.mega` (`.mega__panel`, `.mega__groups`, `.mega__g`, `.mega__pane`,
`.mega__links`, `.mega__link`, `.mega__brands`, `.mega__mini`, `.mega__feat`), `.aurora` (`--short`),
`.ftr` (`.news`, `.ftr__grid`, `.contact`, `.ftr__col`, `.trust`, `.social`, `.langs`, `.ftr__bottom`),
`.bnav` (`.bnav__ind`, `.bnav__i`, `.bnav__icw`), `.to-top`, `.page-head`, `.post-list`, `.post-card`.

---

## 3. PHP helpers (template tags)

All return escaped HTML strings unless named `gadgetim_the_*` (echo).

**Core / text**
- `gadgetim_part( 'header/bar', $args )` — `get_template_part( 'templates/…' )`.
- `gadgetim_attr( [ 'class' => 'x', 'hidden' => true ] )` — attribute string, URLs escaped.
- `gadgetim_icon( 'arrow', 'ic--dir', $attrs )`, `gadgetim_the_icon()` — sprite icons (list in `assets/icons/sprite.svg`: search, user, heart, bag, menu, close, chev-l, chev-r, chev-d, arrow, up, grid, home, flame, bolt, shield, truck, return, chat, lock, phone, mail, plus, minus, check, trash, star, sparkle, gift, trend, crown, clock, headphones, mobile, watch, usb, car, cup, drop, tent, plug, speaker, gauge, pause, play, insta, telegram, video, percent, pin, box, tag, layers, search-x, globe, info, alert, filter, sort, share, bell, eye, logout, orders).
- `gadgetim_bidi( $text )` — escapes and isolates Latin runs in `<bdi>` (model names in Persian titles).
- `gadgetim_fx_words( $html, $start = 0 )` (`inc/fx.php`) — escaped inline HTML → `.sr-only` copy + an
  aria-hidden copy split into `.fxw` word spans (`--w`) for word-by-word reveals; only plain spaces split.
- `gadgetim_percent( 15 )` → «۱۵٪»; `gadgetim_ascii_digits()`; `gadgetim_kses_inline()`.

**Product data**
- `gadgetim_product( $id|$post|$product|null )`, `gadgetim_short_title( $p )` (ProductExtras::short_title),
  `gadgetim_product_brand( $p )` (Catalog\Brands::primary), `gadgetim_product_lane( $p )`,
  `gadgetim_product_discount( $p )`, `gadgetim_product_saving( $p )`, `gadgetim_sale_thresholds()`
  (`strike` 10, `sticker` 15 from Pricing\Display), `gadgetim_sale_end( $p )` (real sale end or 0),
  `gadgetim_is_new( $p )` (30 days, filter `gadgetim_new_days`), `gadgetim_low_stock( $p )` (1–3 or 0),
  `gadgetim_contact_mode( $p = null )` (ContactMode::applies / enabled).
- `gadgetim_price_html( $p, $opts )` — plugin price partial (no base hint, `<div>`), or the
  ContactMode slip (`'slip' => 'compact'|'full'`). Options pass through to `gadgetim_price()`
  (`tag`, `class`, `base_hint`, `context`). `gadgetim_the_price()` echoes.
- `gadgetim_discount_pill( $p|int, 'off--sm' )`, `gadgetim_rating_stars( $p, [ 'link' => true ] )`.

**Card renderer** — `gadgetim_product_card( $product, $variant = 'default', $args = [] )`,
`gadgetim_the_product_card()`. Variants `default | rail | compact | rank | mini`. Args: `index`
(stagger and rank number), `reveal` (default true for default/rail/rank — set false in the first
viewport), `eager` (eager image), `sizes` (context), `class`, `tag` (`li` default, `div`, `article`),
`new` (force the "new" tag), `wish` (false hides the heart), `rank` (rank number). The card is a
single link (title `::after` covers it); the heart and add button sit above it.

**UI**
- `gadgetim_button( $label, [ 'href', 'variant', 'size', 'icon', 'icon_start', 'glow', 'block', 'type', 'class', 'attrs' ] )`.
- `gadgetim_add_button( $p, [ 'wide' => true ] )`, `gadgetim_wish_button( $p )`.
- `gadgetim_badge( $text, $variant, [ 'icon' ] )`, `gadgetim_sticker( '۵۷٪', 'تا', 'تخفیف' )`.
- `gadgetim_section_head( [ 'title', 'sub', 'id', 'icon', 'tone', 'more_url', 'more_label', 'rail', 'tag', 'center', 'reveal', 'after' ] )` — «مشاهده همه» by default.
- `gadgetim_rail_nav( $rail_id, 'light'|'glass' )`.
- `gadgetim_breadcrumbs()` (WC trail on shop pages), `gadgetim_render_crumbs( [ [ label, url ], … ] )`.
- `gadgetim_pagination( [ 'total', 'current', 'query', 'base' ] )` (locale digits, rel prev/next).
- `gadgetim_empty_state( [ 'icon', 'art', 'title', 'text', 'actions' => [ button args + 'label' ], 'tag' ] )`.
- `gadgetim_countdown( $timestamp, [ 'style' => 'flip'|'chip', 'label', 'ended', 'class' ] )` — **real
  deadlines only**: returns '' for 0/past. Sources: `gadgetim_sale_end( $p )`, `gadgetim_campaign_end()`.
- `gadgetim_card_skeleton()`.
- `gadgetim_logo( [ 'uid', 'class' ] )`, `gadgetim_monogram( $uid )`, `gadgetim_brand_name()`.

**Contact** — `gadgetim_contact( 'phone' )`, `gadgetim_tel_href()`, `gadgetim_phone_display()` (`<bdi dir=ltr>`),
`gadgetim_whatsapp_url( $text )`, `gadgetim_telegram_url()`, `gadgetim_instagram_url()`, `gadgetim_guarantee_url()`.

**Images** (`inc/images.php`)
- `gadgetim_product_image( $product|$attachment_id, 'gtm-card', [ 'class', 'alt', 'sizes' => 'card', 'loading', 'fetchpriority', 'lqip', 'attrs' ] )` — srcset/sizes, width/height, LQIP background.
- `gadgetim_image_sizes_attr( $context )`: `card`, `rail`, `hero`, `banner`, `tile`, `pdp`, `story`, `rank`, `thumb`, `mini`, `micro`.
- `gadgetim_set_eager_budget( $n, $high )` before an above-the-fold row: the next `$n` images load eagerly, the first with `fetchpriority=high`.
- `gadgetim_product_image_id( $p, $index )` (0 main, 1 first gallery image…).
- `gadgetim_decor_image( $p|$id, $size, [ 'index', 'fade', … ] )` — **use for every decorative large
  image (hero, banners, tiles, stories, mega art)**: watermark mask `.wm` (+ `.wm--fade` for products
  standing on the bottom edge). Product cards and the gallery stay unmasked.
- `gadgetim_term_image( $term )`. LCP: filter `gadgetim_lcp_image` → `[ attachment_id, size, sizes context ]` (default: product main image) is preloaded in `<head>`.
- Sizes: `gtm-thumb` 160² crop, `gtm-card` 400 soft, `gtm-hero` 800 soft; `medium_large`, `1536`, `2048` disabled; no big-image scaling.

**Navigation data** (`inc/menus.php`, cached per language in transients, version bumped on product/category/settings saves)
- `gadgetim_category_groups()` — the 8 curated groups (definition by slug in `gadgetim_category_groups_def()`, filter of the same name; falls back to the top categories). Each: `id, name, slug, url, icon, count, links[] (id, name, url, count, img), feature, minis[], brands[] (name, url, count)`.
- `gadgetim_story_categories( $limit )` — `id, name, url, count, img` (term thumbnail, else the most popular in-stock product's first gallery image). Filter `gadgetim_story_slugs`.
- `gadgetim_category_image_id( $term )`, `gadgetim_category_icon( $term )`, `gadgetim_category_product_ids( $term_ids, $limit, $in_stock )`, `gadgetim_lane_count( $term )`, `gadgetim_brands( $limit )`.
- `gadgetim_nav_links()` (primary menu or shop links), `gadgetim_footer_service_links()`, `gadgetim_footer_about_links()`, `gadgetim_language_links()` (I18n\Hreflang::switcher), `gadgetim_menu_location( 'primary' )`, `gadgetim_menu_items()`, `gadgetim_page_url( [ slugs ] )`, `gadgetim_shop_url()`.

**Other** — `gadgetim_page_kind()` (`home, product, archive, cart, checkout, account, page, post, blog, search, 404`; filter `gadgetim_page_kind`), `gadgetim_list_setting( 'trust.badges' )`, `gadgetim_campaign_end()`, `gadgetim_count_label( $n )`, `gadgetim_has_bestsellers()`, `gadgetim_config()` (filter `gadgetim_config`), `gadgetim_js_locale()`, `gadgetim_price_unit()`.

### Storefront settings (Gadgetim → Settings → Storefront)

`theme.promo_messages` (i18n list; fallback `trust.badges`), `theme.search_popular` (i18n list),
`theme.campaign_end` (`2026-11-30 23:59`, site time), `theme.newsletter` (toggle),
`theme.enamad_url` / `theme.enamad_img`, `theme.samandehi_url` / `theme.samandehi_img`.
Contact data: `contact.phone`, `contact.whatsapp`, `contact.telegram`, `contact.instagram`,
`contact.email`, `contact.address`, `contact.hours` (plugin Brand tab).

---

## 4. JavaScript

`assets/js/app.js` is the only enqueued module. Other modules are registered as dynamic
dependencies (import map `@gadgetim/<name>`) only when their file exists, and `app.js` imports them on
demand. Each module exports `init( ctx )` (called once by the loader) plus its API. `ctx` = `{ cfg, t,
$, $$, esc, fmt, digits, reduced, mq, load, emit, on, cookie, store, badges, bnav, toast }`, also on
`window.gadgetim` for debugging. Config: `<script type="application/json" id="gadgetim-config">`
(`lang, dir, locale, digits, page, home, rest, restNonce, store, wcAjax, cart, checkout, shop, account,
search, contactMode, cartCookie, wishKey, currency{unit,code,thousands,decimal,percent}, thresholds, strings`).

| Module | Loaded | Data attributes / API |
|---|---|---|
| `app.js` | always | header tuck/shadow (`#hdr`, `--row1`), `[data-ticker]`, `#to-top`, badges `[data-cart-count]` (cookie `gtm_cart_count`), `[data-wish-count]`, `[data-cart-label]`/`[data-wish-label]` (aria-label), hearts `[data-wish="id"]` (localStorage `gtm_wish`), mega `[data-mega]`/`[data-mega-btn]`/`#mega`, accordions, tabs, `[data-act="search"]`, `/` shortcut, newsletter `[data-newsletter]` |
| `motion.js` | idle, every page | `[data-reveal]` + `--i`, `[data-count-to="107"]`, ripple on `.btn/.add/.search__go/.tab/[data-ripple]`, card glow `.pc__in`, `[data-parallax="0.15"]`; API `observe(scope)`, `countUp(el)`, `parallax(el, f)` |
| `fx.js` | idle, every page | pausing (`.fx-off`, `html.fx-hidden`), `[data-fx]` reveals + `html.fx-ready`, hero `.fx-moved`, tilt/specular (`.fx-tilt`), banner light, magnets, extra ripples + tap pulses, cart celebration (`gtm:cart-added` / `gtm:cart`), bottom-nav `.fx-morph`, CTA `.fx-vis`, scroll-timeline fallbacks, view-transition skip across languages; API `burst(x, y, n, power)`, `pulse(el, cls, ms)`. Does nothing under reduced motion / Save-Data |
| `carousel.js` | `[data-carousel]`, `.rail[id]`, `[data-rail]` present | carousels: `[data-carousel][data-autoplay=ms][data-pointer-parallax]`, `[data-slide]`, `[data-carousel-dots]`, `[data-carousel-prev/next/pause]`, event `carousel:change`, `el.carousel.go(n)`; rails: `.rail[id]` + `[data-rail=id][data-dir=prev\|next]`, `[data-rail-autoplay=ms]`; API `mount(scope)`, `railState(rail)` |
| `countdown.js` | `[data-countdown]` present | `[data-countdown=ISO]`, `[data-countdown-label]`, `[data-countdown-ended]`, `[data-countdown-wrap]` (hidden at the end); API `mount(scope)`; event `gtm:countdown-ended` |
| `drawer.js` | first `[data-open]` | `dialog[data-drawer]`, triggers `[data-open=id][aria-controls=id]`, `[data-close]`, `[data-close-link]`; API `open(id, trigger)`, `close()`, `toggle()`, `current()`; event `gtm:drawer {id, open}` |
| `toast.js` | first toast | `ctx.toast({ title, text, img, icon, type, action: { label, href \| open }, duration })` |
| `cart.js` | add-to-cart click, cart/wishlist drawer open | `[data-add-to-cart=id][data-quantity]` (fly-to-cart, bump, wiggle, toast); `#cart-drawer` (Store API, cookie-gated), `#wish-drawer`; API `add(btn)`, `refresh()`, `renderWish()`; event `gtm:cart {count}` |
| `search.js` | first focus/pointer/input on `#q` | `form[data-search]`, `#q[role=combobox]`, `#sugg[role=listbox]`, `<template id="sugg-idle">` parts `[data-part=popular\|cats\|deals]`, `[data-q]` chips; API `attach()`, `norm(s)` |
| `filters.js` | `data-page="archive"` | archive builder |
| `gallery.js` | `data-page="product"` | product builder |

Events on `document`: `gtm:drawer`, `gtm:cart`, `gtm:cart-added` (`{ btn, id, qty }`, the moment the
server confirms an add), `gtm:wish`, `gtm:mega`, `gtm:search-open`, `gtm:tab`, `gtm:countdown-ended`.
Listen with `ctx.on( name, fn )`.

Motion rules: never `data-reveal` in the first viewport; pause carousels on hover/focus/off-screen/hidden
tab (built in); reduced motion → no autoplay, no flips, no flight, instant reveals.

---

## 5. Header, footer and drawers (what exists)

- Promo strip: rotating messages (settings → trust badges → built-in copy), help links, phone (`dir=ltr`).
- Header bar: menu button (< 1000 px), Aurora logo (monogram SVG + gradient «گجتیم» + GADGETIM; an
  uploaded logo replaces it), search with category selector (≥ 1000 px) and live suggestions, account,
  wishlist (drawer), cart (drawer; plain link on cart/checkout). Phones: the top row tucks on scroll, the search stays.
- Desktop nav (≥ 1000 px): «دسته‌بندی کالاها» mega menu (groups with icons, sub-categories with images,
  brands, best deals, featured product), links (primary menu or offers/newest/best sellers/all products), authenticity + WhatsApp.
- Footer: newsletter (admin-post `gadgetim_newsletter`, nonce + honeypot + rate limit, option
  `gadgetim_newsletter`), about + contact cards, quick links, popular categories, customer service (footer
  menu or the imported pages), trust seals (eNamad/Samandehi from settings, dashed placeholders until set),
  social, language links, «© ۱۴۰۵ گجتیم» (Jalali year).
- Bottom nav (< 768 px): home, categories (menu drawer), search, cart (drawer, badge), account; sliding indicator.
- Drawers: `#menu-drawer` (account, category accordion, links, WhatsApp, languages), `#cart-drawer`, `#wish-drawer`.
- `#toasts`, `#to-top` (≥ 768 px).

Favicons: `assets/img/favicon.svg`, `favicon-32.png`, `favicon-16.png`, `apple-touch-icon.png` (180),
`icon-192.png`, `icon-512.png`, `site.webmanifest` (printed unless a Customizer Site Icon is set).
