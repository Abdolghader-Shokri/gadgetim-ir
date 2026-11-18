#!/usr/bin/env bash
# Sets the release number of both packages (owner, 2026-11-18: "number every version from now on"):
#   tools/bump-version.sh X.Y.Z
#
# One number for the plugin and the theme, because tools/build-release.sh builds a release from the
# tag vX.Y.Z and refuses headers that do not match it. Written:
#   wp-content/plugins/gadgetim-core/gadgetim-core.php   "Version:" header and GADGETIM_CORE_VERSION
#   wp-content/themes/gadgetim/style.css                 "Version:" header
#
# Numbering: every delivery to the live site is a release. Z for fixes only, Y for new features or
# visible changes (Z back to 0), X for a breaking change. Then: add the release to CHANGELOG.md,
# commit, merge, tag the merge commit on main with vX.Y.Z, push the tag and build from it
# (tools/build-release.sh vX.Y.Z). An untagged build is a test build, never a delivery.
#
# Changing the plugin version runs Plugin::maybe_upgrade() once on the next admin page load; its hooks
# only add what is missing (settings, languages, currencies, the daily price check).
# Output for the user is Persian; technical tokens stay Latin.

set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$repo_root"

die() {
  printf '✘ %s\n' "$1" >&2
  exit 1
}

if [ "$#" -ne 1 ] || [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
  sed -n '2,17p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
  [ "$#" -eq 1 ] && exit 0
  exit 1
fi

version="$1"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "شمارهٔ نسخه باید به شکل X.Y.Z باشد، مثل 1.0.1: $version"

plugin=wp-content/plugins/gadgetim-core/gadgetim-core.php
theme=wp-content/themes/gadgetim/style.css
[ -f "$plugin" ] || die "پیدا نشد: $plugin"
[ -f "$theme" ] || die "پیدا نشد: $theme"

current=$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$plugin" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')

# Only the header lines and the constant change; the files keep their LF endings.
sed -i -E "0,/^[[:space:]]*\*[[:space:]]*Version:/s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${version}/" "$plugin"
sed -i -E "s/^(define\( 'GADGETIM_CORE_VERSION', ')[^']*(' \);)/\1${version}\2/" "$plugin"
sed -i -E "0,/^Version:/s/^(Version:[[:space:]]*).*/\1${version}/" "$theme"

grep -q "^ \* Version:[[:space:]]*${version}$" "$plugin" || die "سرآیند افزونه به‌روز نشد."
grep -q "define( 'GADGETIM_CORE_VERSION', '${version}' );" "$plugin" || die "ثابت GADGETIM_CORE_VERSION به‌روز نشد."
grep -q "^Version: ${version}$" "$theme" || die "سرآیند قالب به‌روز نشد."

printf '✔ نسخهٔ افزونه و قالب: %s ← %s\n' "${current:-?}" "$version"
printf '  بعد: CHANGELOG.md، کامیت، ادغام، تگ v%s روی main و tools/build-release.sh v%s\n' "$version" "$version"
