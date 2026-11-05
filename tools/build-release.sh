#!/usr/bin/env bash
# Gadgetim release builder (architecture 2.5, decision D7). Run from Git Bash or CI:
#   tools/build-release.sh [<ref>]        default ref: HEAD; for a release pass the tag, e.g. v1.0.0
#
# For each package a zip is made with `git archive` straight from the git object store, so the
# working tree never leaks into it and line endings are the committed (LF) ones:
#   release/gadgetim-core-<version>.zip   -> folder gadgetim-core/   (upload first)
#   release/gadgetim-theme-<version>.zip  -> folder gadgetim/        (upload second)
# plus a <zip>.sha256 next to each. <version> is the tag without "v" (the package headers must
# agree with it) or, for an untagged ref, "<header version>-<short sha>".
#
# Dev files are left out in two ways: a built-in list of top-level names (tests, phpcs.xml.dist,
# .gitattributes, ...) and `export-ignore` attributes in a .gitattributes INSIDE the package
# (git archive reads attributes from the archived tree, not from the repository root).
# Every zip is unpacked once more and refused when any text file carries CRLF or a PHP file a BOM.

set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$repo_root"

die() {
  printf '✘ %s\n' "$1" >&2
  exit 1
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
  sed -n '2,16p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
  exit 0
fi

# name | subtree in the repository | folder name inside the zip | file carrying the "Version:" header
packages=(
  "gadgetim-core|wp-content/plugins/gadgetim-core|gadgetim-core|gadgetim-core.php"
  "gadgetim-theme|wp-content/themes/gadgetim|gadgetim|style.css"
)

# Top-level entries of a package that never ship.
dev_entries=(
  .gitattributes .gitignore .editorconfig .phpcs.xml.dist phpcs.xml.dist phpcs.xml
  phpunit.xml.dist phpunit.xml composer.json composer.lock package.json package-lock.json
  node_modules vendor tests test .github .vscode .idea
)

# ---------------------------------------------------------------------------
# Resolve the ref and a release tag.
# ---------------------------------------------------------------------------
ref="${1:-HEAD}"
commit=$(git rev-parse --verify --quiet "${ref}^{commit}") || die "مرجع گیت پیدا نشد: $ref"
short=$(git rev-parse --short "$commit")

tag=""
if git rev-parse --verify --quiet "refs/tags/$ref" >/dev/null 2>&1; then
  tag="$ref"
else
  tag=$(git describe --tags --exact-match "$commit" 2>/dev/null || true)
fi
if [ -n "$tag" ] && [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.-]+)?$ ]]; then
  tag="" # a tag, but not a release tag (vX.Y.Z)
fi

if [ -n "$tag" ]; then
  echo "ساخت نسخهٴ انتشار از تگ $tag (کامیت $short)"
else
  echo "ساخت بستهٴ آزمایشی از $ref (کامیت $short، بدون تگ انتشار)"
fi

if ! git diff --quiet HEAD -- wp-content 2>/dev/null \
  || [ -n "$(git ls-files --others --exclude-standard -- wp-content 2>/dev/null)" ]; then
  echo "هشدار: در درخت کاری تغییرات کامیت‌نشده هست؛ در زیپ فقط محتوای کامیت $short می‌رود."
fi

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
header_version() { # <commit> <path>  -> value of the "Version:" header (first 60 lines)
  git show "$1:$2" 2>/dev/null | head -n 60 \
    | grep -m1 -iE '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*[^[:space:]]' \
    | sed -E 's/^[^:]*:[[:space:]]*//; s/[[:space:]]*(\*\/)?[[:space:]]*$//' || true
}

sha256_of() { # <file> -> hex digest
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1" | cut -d' ' -f1
  elif command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$1" | cut -d' ' -f1
  elif command -v openssl >/dev/null 2>&1; then
    openssl dgst -sha256 "$1" | sed 's/^.*= //'
  elif command -v certutil >/dev/null 2>&1; then
    certutil -hashfile "$1" SHA256 | sed -n 2p | tr -d ' \r'
  else
    die "ابزاری برای SHA-256 پیدا نشد (sha256sum/shasum/openssl/certutil)."
  fi
}

human_size() { # <bytes>
  awk -v b="$1" 'BEGIN { if (b >= 1048576) printf "%.1f MB", b / 1048576; else printf "%.0f KB", b / 1024 }'
}

# Byte-level scan: prints "cr" when the file contains a CR byte and "nul" when it contains a NUL
# (binary). grep is deliberately not used: the MSYS build rewrites CR/LF in "text" files first.
scan_bytes() {
  local hex out=""
  # "|| true": head closes the pipe early on big binaries and pipefail would otherwise abort the build.
  hex=$(LC_ALL=C tr -cd '\r\000' <"$1" | head -c 64 | od -An -tx1 | tr -d ' \n' || true)
  case "$hex" in *0d*) out="cr" ;; esac
  case "$hex" in *00*) out="$out nul" ;; esac
  printf '%s' "$out"
}
has_bom() { [ "$(head -c 3 "$1" | od -An -tx1 | tr -d ' \n')" = "efbbbf" ]; }

# A subtree archive reads attributes from the archived tree only, never from the repository root,
# so without this pin a Windows checkout with core.autocrlf=true would get CRLF written into the zip.
# Blobs are stored LF (root .gitattributes: text=auto eol=lf); emit them byte for byte.
git_archive() {
  git -c core.autocrlf=false -c core.eol=lf archive "$@"
}

mtime_opt=()
archive_help=$(git archive -h 2>&1 || true) # exits 129 by design; pipefail must not see it
case "$archive_help" in
  *--mtime*)
    # Same commit -> same bytes -> same SHA-256 (reproducible zips; git >= 2.41).
    mtime_opt=(--mtime="$(git show -s --format=%ci "$commit")")
    ;;
esac

mkdir -p release
tmp_root=$(mktemp -d)
trap 'rm -rf "$tmp_root"' EXIT

built=()

# ---------------------------------------------------------------------------
# Build each package
# ---------------------------------------------------------------------------
for spec in "${packages[@]}"; do
  IFS='|' read -r name subtree folder header_file <<<"$spec"

  if ! git rev-parse --verify --quiet "$commit:$subtree" >/dev/null 2>&1; then
    die "بستهٴ $name در کامیت $short وجود ندارد ($subtree)."
  fi

  hv=$(header_version "$commit" "$subtree/$header_file")
  [ -n "$hv" ] || die "سرآیند Version: در $subtree/$header_file پیدا نشد."

  if [ -n "$tag" ]; then
    version="${tag#v}"
    if [ "$hv" != "$version" ]; then
      die "نسخهٴ تگ ($version) با سرآیند $name ($hv) نمی‌خواند؛ سرآیند را به‌روز کن یا دوباره تگ بزن."
    fi
  else
    version="${hv}-${short}"
  fi

  # Top-level entries minus the dev list; export-ignore inside the package still applies on top.
  keep=()
  while IFS= read -r entry; do
    [ -z "$entry" ] && continue
    skip=0
    for d in "${dev_entries[@]}"; do
      if [ "$entry" = "$d" ]; then
        skip=1
        break
      fi
    done
    [ "$skip" -eq 0 ] && keep+=("$entry")
  done < <(git ls-tree --name-only "$commit:$subtree")
  [ ${#keep[@]} -gt 0 ] || die "بستهٴ $name خالی است."

  zip="release/${name}-${version}.zip"
  rm -f "$zip" "$zip.sha256"
  git_archive --format=zip "${mtime_opt[@]}" --prefix="$folder/" -o "$zip" "$commit:$subtree" "${keep[@]}"

  # Verify the exact same tree: LF only, no BOM in PHP files.
  check_dir="$tmp_root/$name"
  mkdir -p "$check_dir"
  git_archive --format=tar "${mtime_opt[@]}" "$commit:$subtree" "${keep[@]}" | tar -x -C "$check_dir"

  crlf=""
  bom=""
  while IFS= read -r -d '' f; do
    rel=${f#"$check_dir"/}
    case "$(scan_bytes "$f")" in
      *nul*) ;; # binary file (image, font): line endings do not apply
      *cr*) crlf+="      $rel"$'\n' ;;
    esac
    case "$rel" in
      *.php) if has_bom "$f"; then bom+="      $rel"$'\n'; fi ;;
    esac
  done < <(find "$check_dir" -type f -print0)
  if [ -n "$crlf" ]; then
    rm -f "$zip"
    printf '%s' "$crlf" >&2
    die "فایل‌های بالا CRLF دارند؛ زیپ $name ساخته نشد. (git add --renormalize . و کامیت دوباره)"
  fi
  if [ -n "$bom" ]; then
    rm -f "$zip"
    printf '%s' "$bom" >&2
    die "فایل‌های PHP بالا BOM دارند؛ زیپ $name ساخته نشد."
  fi

  file_count=$(find "$check_dir" -type f | wc -l | tr -d ' ')
  size=$(wc -c <"$zip" | tr -d ' ')
  digest=$(sha256_of "$zip")
  printf '%s  %s\n' "$digest" "$(basename "$zip")" >"$zip.sha256"

  built+=("$zip")
  echo
  echo "بستهٴ $name ($version): $zip — $file_count فایل، $(human_size "$size")"
  echo "SHA-256: $digest"
done

echo
echo "ترتیب آپلود روی سرور: اول gadgetim-core، بعد پوسته (معماری 2.5). فایل .sha256 کنار هر زیپ است:"
for z in "${built[@]}"; do
  echo "  $z"
done
