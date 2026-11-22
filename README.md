# gadgetim-ir

فروشگاه جدید گجتیم (gadgetim.ir): وردپرس + ووکامرس در دو بسته — افزونهٴ `gadgetim-core` (داده و رفتار: سه‌زبانه، چندارزی، تنظیمات، واردکننده) و پوستهٴ `gadgetim` (فقط نمایش). مشخصات کامل در `docs/` است؛ این فایل فقط راه‌اندازی و ابزارها را می‌گوید.

## Layout

| Path | What |
|---|---|
| `wp-content/plugins/gadgetim-core/` | core plugin: data, behaviour, admin |
| `wp-content/themes/gadgetim/` | theme "Gadgetim — Aurora" (prototype in `docs/prototypes/aurora/`): presentation only |
| `docs/` | `architecture.md` (spec), `decisions.md` (wins where the documents differ), `design-spec.md` |
| `tools/` | developer scripts (below); `tools/wp-cli/` is a gitignored download cache |
| `release/` | zips from `tools/build-release.sh` (gitignored) |

The packages sit at their real WordPress paths, so junctions, `git archive` and zips need no path rewriting.

## Local development (Windows, Local)

Site: `C:\Users\GHADER\Local Sites\gadgetim\app\public` (http://gadgetim.local). Never edit inside `Local Sites`; the site sees the repository through links.

| Script | Purpose |
|---|---|
| `tools\link-local.ps1` | creates/refreshes directory junctions site -> repo (`-Status`, `-Remove`). Links are removed with `[System.IO.Directory]::Delete` after a reparse-point check, never with `Remove-Item`; a real directory at the target is refused. |
| `tools\sync-local.ps1` | fallback: one-way `robocopy /MIR` repo -> site (`-DryRun`). Refuses a junction destination. |
| `tools\wp.ps1 <args>` | WP-CLI with Local's `php.exe` and the site's rendered `php.ini`; downloads `wp-cli.phar` into `tools\wp-cli\` on first use (SHA-512 verified). Without arguments it prints the resolved paths. |
| `tools/wp-bootstrap.php` | plain-PHP alternative to WP-CLI: loads WordPress from the Local site, then runs a file, `-r '<code>'` or STDIN (`--help`). |
| `tools/lint.sh` | `php -l` on every PHP file, shellcheck, phpcs when installed, LF/BOM/exec-bit checks |
| `tools/build-release.sh [tag]` | `git archive` per package -> `release/*.zip` + `.sha256`, LF verified |
| `tools/bump-version.sh X.Y.Z` | sets the release number in the plugin header, `GADGETIM_CORE_VERSION` and the theme `style.css` |

PowerShell scripts: the default policy blocks `.ps1` files, so either allow them once with `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` and call `tools\wp.ps1 ...` directly, or run `powershell -NoProfile -ExecutionPolicy Bypass -File tools\link-local.ps1` (fine for link/sync; through `-File`, WP-CLI arguments that contain spaces lose their quotes). Shell scripts run from Git Bash.

PHP from Git Bash (PHP is not on PATH; the rendered ini carries Local's MySQL port, `-d display_startup_errors=0` hides the harmless imagick warning):

```
PHP="C:/Users/GHADER/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
INI="C:/Users/GHADER/AppData/Roaming/Local/run/sqs5AIpIj/conf/php/php.ini"
"$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php -r 'echo get_option("siteurl");'
```

Environment overrides for all tools: `GADGETIM_PHP`, `GADGETIM_PHP_INI`, `GADGETIM_WP_PATH`.

## Conventions

- LF everywhere (`.gitattributes`: `* text=auto eol=lf`), tabs in PHP/JS/CSS (`.editorconfig`), WordPress Coding Standards (`phpcs.xml.dist`, WPCS 3.x).
- Windows does not record the executable bit. After adding or changing a `.sh` file:

  ```
  git update-index --chmod=+x tools/lint.sh tools/build-release.sh
  ```

  For a file not yet in the index use `git add --chmod=+x <file>`. `tools/lint.sh` fails while the bit is missing.
- PHP 8.1+, WordPress 6.7+, WooCommerce 9.6+. No build step, no Composer at runtime.

## Release

Every delivery of the plugin and the theme to the live site is a numbered release (owner, 2026-11-18). Both packages carry the same number:
- Z for fixes only;
- Y for new features or visible changes, with Z back to 0;
- X for a breaking change.

An untagged build (`<header version>-<sha>`) is a test build, never a delivery.

1. `tools/bump-version.sh X.Y.Z` and a new section in `CHANGELOG.md`.
2. Commit and merge.
3. Tag the merge commit on main `vX.Y.Z`, push the tag, then `tools/build-release.sh vX.Y.Z` (it refuses headers that differ from the tag).
4. Pack the delivery into one file (owner, 2026-11-22: one zip on the host, one visit to the dashboard):
   `"$PHP" -c "$INI" tools/build-update-bundle.php X.Y.Z [content-….zip products-….zip …]`
   writes `release/deploy/gadgetim-update-X.Y.Z-<date>.zip` with both release zips and the day's drop packages. Every delivery carries the plugin and the theme, also a content-only one: the site skips a version it has and catches up on one it missed.
5. The owner puts that one file into `public_html/wp-content/gadgetim-drop/` and opens the dashboard. `Import\Bundle` checks every SHA-256 and version, installs a newer plugin and theme the way "Replace current with uploaded" does, loads the site from the server and puts the old version back on a fatal error, then the content is imported and the dashboard shows what happened.
   - A live site before 1.4.0 cannot read the package: upload `gadgetim-core-X.Y.Z.zip` once from Plugins -> Add -> Upload ("Replace current with uploaded") first. A package that the old version moved into `failed/` is taken back by itself.
6. Check the number on the live site: Plugins shows it for Gadgetim Core, and Appearance -> Themes -> Theme Details shows it for the theme.

Nothing is ever fetched from a remote update channel: code reaches the site only in a package the owner uploads by hand (architecture D7).
