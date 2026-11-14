#!/usr/bin/env bash
# Gadgetim repository lint (architecture 2.6). Run from Git Bash or CI:
#   tools/lint.sh [--no-phpcs] [--no-shellcheck]
#
#   1. php -l on every PHP file under wp-content/, tools/ and tests/
#      (php from PATH, otherwise Local's php.exe; override with GADGETIM_PHP)
#   1b. PHP 8.1 floor: php -l with a PHP 8.1 binary (GADGETIM_PHP81, php8.1 on PATH, or docker
#      php:8.1-cli), plus a syntax scan for PHP 8.2+ constructs that always runs
#   2. shellcheck on every *.sh in the repository, when shellcheck is installed
#   3. phpcs with phpcs.xml.dist, when phpcs is installed (vendor/bin or PATH)
#   4. hygiene: LF-only text files, no UTF-8 BOM in PHP files, executable bit on *.sh in the index
#   5. the theme's .min.css copies match their sources (tools/minify-css.php --check)
#
# Exit code: 0 when every check that ran passed, 1 otherwise. Checks that could not run say so.
# Output for the user is Persian; technical tokens stay Latin.

set -u

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$repo_root" || exit 1

run_phpcs=1
run_shellcheck=1
for arg in "$@"; do
  case "$arg" in
    --no-phpcs) run_phpcs=0 ;;
    --no-shellcheck) run_shellcheck=0 ;;
    -h|--help)
      sed -n '2,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "گزینهٴ ناشناخته: $arg" >&2
      exit 1
      ;;
  esac
done

failures=0
fail() {
  failures=$((failures + 1))
  printf '  ✘ %s\n' "$1"
}
head1() { printf '\n== %s\n' "$1"; }

# Byte-level scan: prints "cr" when the file contains a CR byte and "nul" when it contains a NUL
# (binary). grep is deliberately not used: the MSYS build rewrites CR/LF in "text" files first.
scan_bytes() {
  local hex out=""
  hex=$(LC_ALL=C tr -cd '\r\000' <"$1" | head -c 64 | od -An -tx1 | tr -d ' \n' || true)
  case "$hex" in *0d*) out="cr" ;; esac
  case "$hex" in *00*) out="$out nul" ;; esac
  printf '%s' "$out"
}
has_bom() { [ "$(head -c 3 "$1" | od -An -tx1 | tr -d ' \n')" = "efbbbf" ]; }

# ---------------------------------------------------------------------------
# PHP binary: PATH first, then Local's php.exe (architecture 0: "no PHP on PATH").
# ---------------------------------------------------------------------------
php_bin="${GADGETIM_PHP:-}"
if [ -z "$php_bin" ]; then
  if command -v php >/dev/null 2>&1; then
    php_bin="php"
  else
    php_bin="C:/Users/GHADER/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
    if [ ! -f "$php_bin" ]; then
      # Any Local PHP 8.x build on this machine.
      appdata="${APPDATA:-}"
      if [ -n "$appdata" ] && command -v cygpath >/dev/null 2>&1; then
        appdata=$(cygpath -u "$appdata")
      fi
      for candidate in "$appdata"/Local/lightning-services/php-8*/bin/win64/php.exe; do
        [ -f "$candidate" ] && php_bin="$candidate"
      done
    fi
  fi
fi

# ---------------------------------------------------------------------------
# 1. php -l
# ---------------------------------------------------------------------------
head1 "php -l"
php_dirs=()
for d in wp-content tools tests; do
  [ -d "$d" ] && php_dirs+=("$d")
done

php_files=()
if [ ${#php_dirs[@]} -gt 0 ]; then
  while IFS= read -r -d '' f; do
    php_files+=("$f")
  done < <(find "${php_dirs[@]}" -type f -name '*.php' \
    -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path 'tools/wp-cli/*' -print0 | sort -z)
fi

if [ ${#php_files[@]} -eq 0 ]; then
  echo "  فایل PHP پیدا نشد (wp-content/، tools/، tests/)."
elif ! { [ -x "$php_bin" ] || command -v "$php_bin" >/dev/null 2>&1; }; then
  fail "مفسر PHP پیدا نشد ($php_bin). php را روی PATH بگذار یا GADGETIM_PHP را تنظیم کن."
else
  jobs=$(nproc 2>/dev/null || echo 4)
  lint_log=$(mktemp)
  # -n: no php.ini (a syntax check needs no extensions, and it skips Local's imagick startup warning).
  printf '%s\0' "${php_files[@]}" \
    | xargs -0 -P "$jobs" -n 1 "$php_bin" -n -d display_errors=1 -d error_reporting=-1 -l >"$lint_log" 2>&1
  lint_rc=${PIPESTATUS[1]}
  if [ "$lint_rc" -ne 0 ]; then
    fail "خطای نحوی در PHP:"
    grep -v '^No syntax errors detected' "$lint_log" | grep -v '^$' | sed 's/^/      /'
  else
    echo "  ✔ ${#php_files[@]} فایل PHP بدون خطای نحوی ($php_bin)"
  fi
  rm -f "$lint_log"

  # PHP 8.1 floor: the plugin declares 8.1 and production PHP is unknown, while Local runs 8.2.
  php81_bin="${GADGETIM_PHP81:-}"
  if [ -z "$php81_bin" ] && command -v php8.1 >/dev/null 2>&1; then
    php81_bin="php8.1"
  fi
  if [ -n "$php81_bin" ]; then
    lint81_log=$(mktemp)
    printf '%s\0' "${php_files[@]}" \
      | xargs -0 -P "$jobs" -n 1 "$php81_bin" -n -d display_errors=1 -l >"$lint81_log" 2>&1
    if [ "${PIPESTATUS[1]}" -ne 0 ]; then
      fail "خطای نحوی در PHP 8.1:"
      grep -v '^No syntax errors detected' "$lint81_log" | grep -v '^$' | sed 's/^/      /'
    else
      echo "  ✔ php -l با PHP 8.1 ($php81_bin)"
    fi
    rm -f "$lint81_log"
  elif command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    if docker run --rm -v "$repo_root:/app" -w /app php:8.1-cli sh -c \
      'find wp-content tools tests -name "*.php" -not -path "*/vendor/*" -not -path "tools/wp-cli/*" -print0 | xargs -0 -n 1 php -l | grep -v "^No syntax errors"; exit 0' \
      | grep -q .; then
      fail "خطای نحوی در PHP 8.1 (docker php:8.1-cli)."
    else
      echo "  ✔ php -l با PHP 8.1 (docker php:8.1-cli)"
    fi
  else
    echo "  php -l با PHP 8.1 اجرا نشد: GADGETIM_PHP81، php8.1 یا docker در دسترس نیست. فقط اسکن نحوی اجرا شد."
  fi

  # Syntax scan for PHP 8.2+ constructs (runs everywhere): standalone true/false/null types,
  # readonly classes, DNF types, typed class constants (8.3) and #[\Override] (8.3).
  compat_hits=$(grep -nE \
    -e 'function\b.*\)\s*:\s*\??(true|false|null)\s*(\{|;|$)' \
    -e 'function\b.*\)\s*:\s*([A-Za-z_\\?]+\|)*true(\|[A-Za-z_\\]+)*\s*(\{|;|$)' \
    -e '^\s*(final\s+|abstract\s+)*readonly\s+(final\s+|abstract\s+)*class\b' \
    -e '\(\s*[A-Za-z_\\]+\s*&\s*[A-Za-z_\\]+\s*\)\s*\|' \
    -e '^\s*(public|protected|private|final)?\s*const\s+(int|string|float|bool|array|self|static)\s+[A-Z_]' \
    -e '#\[\\?Override\]' \
    "${php_files[@]}" 2>/dev/null || true)
  if [ -n "$compat_hits" ]; then
    fail "ساختار PHP 8.2 یا جدیدتر (کف پروژه PHP 8.1 است):"
    printf '%s\n' "$compat_hits" | sed 's/^/      /'
  else
    echo "  ✔ ساختار نحوی جدیدتر از PHP 8.1 پیدا نشد"
  fi

  # BOM and CRLF inside PHP files break headers and the Linux server respectively.
  for f in "${php_files[@]}"; do
    if has_bom "$f"; then
      fail "UTF-8 BOM در ابتدای فایل: $f"
    fi
    case "$(scan_bytes "$f")" in
      *cr*) fail "پایان خط CRLF (باید LF باشد): $f" ;;
    esac
  done
fi

# ---------------------------------------------------------------------------
# 2. shellcheck
# ---------------------------------------------------------------------------
head1 "shellcheck"
sh_files=()
while IFS= read -r -d '' f; do
  sh_files+=("$f")
done < <(git ls-files -z -c -o --exclude-standard -- '*.sh' ':!first-backup' ':!release' ':!node_modules')

if [ "$run_shellcheck" -eq 0 ]; then
  echo "  رد شد (--no-shellcheck)."
elif [ ${#sh_files[@]} -eq 0 ]; then
  echo "  فایل .sh پیدا نشد."
elif ! command -v shellcheck >/dev/null 2>&1; then
  echo "  اجرا نشد: shellcheck نصب نیست (winget install koalaman.shellcheck)."
else
  if shellcheck "${sh_files[@]}"; then
    echo "  ✔ ${#sh_files[@]} اسکریپت shell بدون هشدار"
  else
    fail "shellcheck هشدار داد (بالا)."
  fi
fi

# ---------------------------------------------------------------------------
# 3. phpcs (optional)
# ---------------------------------------------------------------------------
head1 "phpcs"
phpcs_bin=""
if [ -x vendor/bin/phpcs ]; then
  phpcs_bin="vendor/bin/phpcs"
elif command -v phpcs >/dev/null 2>&1; then
  phpcs_bin="$(command -v phpcs)"
fi

if [ "$run_phpcs" -eq 0 ]; then
  echo "  رد شد (--no-phpcs)."
elif [ ! -d wp-content ]; then
  echo "  رد شد: wp-content/ هنوز وجود ندارد."
elif [ -z "$phpcs_bin" ]; then
  echo "  اجرا نشد: phpcs نصب نیست (composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer)."
else
  # Warnings are printed but only errors fail the run.
  if "$phpcs_bin" -q --runtime-set ignore_warnings_on_exit 1; then
    echo "  ✔ phpcs بدون خطا"
  else
    fail "phpcs خطا داد (بالا)."
  fi
fi

# ---------------------------------------------------------------------------
# 4. hygiene: LF, executable bits
# ---------------------------------------------------------------------------
head1 "پایان خط و بیت اجرا"
crlf_tracked=$(git ls-files --eol 2>/dev/null | grep -E '^i/[a-z-]+ +w/(crlf|mixed)' | sed 's/.*\t//')
if [ -n "$crlf_tracked" ]; then
  while IFS= read -r f; do
    fail "فایل ردیابی‌شده با CRLF در درخت کاری: $f"
  done <<<"$crlf_tracked"
else
  echo "  ✔ همهٴ فایل‌های ردیابی‌شده LF هستند"
fi

exec_missing=0
while IFS= read -r line; do
  [ -z "$line" ] && continue
  mode=${line%% *}
  path=${line#*$'\t'}
  if [ "$mode" != "100755" ]; then
    exec_missing=1
    fail "بیت اجرا ثبت نشده: git update-index --chmod=+x $path"
  fi
done < <(git ls-files -s -- '*.sh' ':!first-backup' ':!release' 2>/dev/null)
[ "$exec_missing" -eq 0 ] && echo "  ✔ بیت اجرای اسکریپت‌های .sh در ایندکس ثبت است (یا اسکریپتی ردیابی نشده)"

# ---------------------------------------------------------------------------
# 5. minified theme stylesheets match their sources (tools/minify-css.php)
# ---------------------------------------------------------------------------
head1 "CSS فشرده قالب"
if ! { [ -x "$php_bin" ] || command -v "$php_bin" >/dev/null 2>&1; }; then
  echo "  اجرا نشد: مفسر PHP پیدا نشد."
elif css_out=$("$php_bin" -n tools/minify-css.php --check 2>&1); then
  echo "  ✔ فایل‌های .min.css با منبعشان یکی‌اند"
else
  fail "فایل .min.css کهنه یا گم است؛ اجرا کنید: php tools/minify-css.php"
  printf '    %s\n' "$css_out"
fi

# ---------------------------------------------------------------------------
echo
if [ "$failures" -eq 0 ]; then
  echo "✔ همه‌چیز سالم است."
  exit 0
fi
echo "✘ $failures مشکل پیدا شد."
exit 1
