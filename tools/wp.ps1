<#
.SYNOPSIS
    WP-CLI for the Local site (architecture 2.6): Local's php.exe + the site's rendered php.ini + wp-cli.phar.

.DESCRIPTION
    tools\wp.ps1 <wp-cli arguments>      e.g.  tools\wp.ps1 option get siteurl
    tools\wp.ps1                         no arguments: show the resolved paths, download nothing
    tools\wp.ps1 cli update              update the phar in place (WP-CLI verifies its own download)

    wp-cli.phar is downloaded into tools\wp-cli\ (gitignored) ONLY when this script runs and the file
    is missing. The download is a pinned WP-CLI release, checked against the SHA-512 pinned in this
    script (not one fetched from the same server) before it is kept and executed.
    --path=<Local site> is added unless you pass your own --path. The rendered php.ini matters: it
    carries Local's MySQL port. Windows PowerShell 5.1 compatible; no param() block so every argument
    reaches WP-CLI untouched.

    Environment overrides: GADGETIM_PHP (php.exe), GADGETIM_PHP_INI (php.ini), GADGETIM_WP_PATH (site root),
    GADGETIM_WPCLI_URL + GADGETIM_WPCLI_SHA512 (another phar and its expected SHA-512; the URL is refused
    without the hash).

    To move to a newer WP-CLI: take the hash from the release's .sha512 asset on
    https://github.com/wp-cli/wp-cli/releases, cross-check it, and update $pinnedVersion / $pinnedSha512.
#>

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$wpArgs = @($args)

$pharDir     = Join-Path $PSScriptRoot 'wp-cli'
$phar        = Join-Path $pharDir 'wp-cli.phar'
$defaultPhp  = 'C:\Users\GHADER\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe'
$defaultIni  = 'C:\Users\GHADER\AppData\Roaming\Local\run\sqs5AIpIj\conf\php\php.ini'
$defaultSite = 'C:\Users\GHADER\Local Sites\gadgetim\app\public'
# Pinned release. SHA-512 cross-checked on 2026-11-04 against the release's .sha512 asset and the
# copy already verified in tools\wp-cli\ (both be928f6b…fe8832).
$pinnedVersion = '2.12.0'
$pinnedSha512  = 'be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832'
$defaultUrl    = "https://github.com/wp-cli/wp-cli/releases/download/v$pinnedVersion/wp-cli-$pinnedVersion.phar"

function Resolve-GtmPhp {
    if ($env:GADGETIM_PHP) {
        if (Test-Path -LiteralPath $env:GADGETIM_PHP -PathType Leaf) { return $env:GADGETIM_PHP }
        throw "GADGETIM_PHP به فایلی اشاره می‌کند که نیست: $env:GADGETIM_PHP"
    }
    if (Test-Path -LiteralPath $defaultPhp -PathType Leaf) { return $defaultPhp }

    # Any Local PHP 8.x build on this machine, newest first.
    $servicesRoot = Join-Path $env:APPDATA 'Local\lightning-services'
    if (Test-Path -LiteralPath $servicesRoot -PathType Container) {
        $candidates = Get-ChildItem -LiteralPath $servicesRoot -Directory -Filter 'php-8*' | Sort-Object Name -Descending
        foreach ($dir in $candidates) {
            $exe = Join-Path $dir.FullName 'bin\win64\php.exe'
            if (Test-Path -LiteralPath $exe -PathType Leaf) { return $exe }
        }
    }
    throw 'php.exe سرویس Local پیدا نشد. Local نصب است؟ (یا GADGETIM_PHP را تنظیم کن)'
}

function Resolve-GtmPhpIni {
    if ($env:GADGETIM_PHP_INI) {
        if (Test-Path -LiteralPath $env:GADGETIM_PHP_INI -PathType Leaf) { return $env:GADGETIM_PHP_INI }
        throw "GADGETIM_PHP_INI به فایلی اشاره می‌کند که نیست: $env:GADGETIM_PHP_INI"
    }
    if (Test-Path -LiteralPath $defaultIni -PathType Leaf) { return $defaultIni }

    # The run folder is named after the site id; sites.json maps names to ids.
    $sitesJson = Join-Path $env:APPDATA 'Local\sites.json'
    if (Test-Path -LiteralPath $sitesJson -PathType Leaf) {
        $sites = Get-Content -LiteralPath $sitesJson -Raw -Encoding UTF8 | ConvertFrom-Json
        foreach ($prop in $sites.PSObject.Properties) {
            $site = $prop.Value
            $name = ''
            $path = ''
            try { $name = [string]$site.name } catch { }
            try { $path = [string]$site.path } catch { }
            if ($name -eq 'gadgetim' -or $path -match '[\\/]gadgetim$') {
                $candidate = Join-Path $env:APPDATA ('Local\run\' + $prop.Name + '\conf\php\php.ini')
                if (Test-Path -LiteralPath $candidate -PathType Leaf) { return $candidate }
            }
        }
    }
    throw 'php.ini رندرشدهٴ سایت پیدا نشد. سایت gadgetim را یک بار در Local روشن کن (یا GADGETIM_PHP_INI را تنظیم کن).'
}

function Get-GtmWpCli {
    if (Test-Path -LiteralPath $phar -PathType Leaf) { return $phar }

    $url      = $defaultUrl
    $expected = $pinnedSha512
    if ($env:GADGETIM_WPCLI_URL) {
        # A hash fetched from the same (possibly redirected) server proves nothing: an override
        # URL must come with its own expected SHA-512.
        $override = [regex]::Match([string]$env:GADGETIM_WPCLI_SHA512, '^[0-9a-fA-F]{128}$')
        if (-not $override.Success) {
            throw 'GADGETIM_WPCLI_URL بدون GADGETIM_WPCLI_SHA512 (هش ۱۲۸ کاراکتری SHA-512) پذیرفته نمی‌شود.'
        }
        $url      = $env:GADGETIM_WPCLI_URL
        $expected = $override.Value
    }

    New-Item -ItemType Directory -Path $pharDir -Force | Out-Null
    $download = "$phar.download"

    Write-Host "wp-cli.phar موجود نیست؛ دانلود از $url ..."
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
    $ProgressPreference = 'SilentlyContinue'
    try {
        Invoke-WebRequest -Uri $url -OutFile $download -UseBasicParsing
        $actual = (Get-FileHash -LiteralPath $download -Algorithm SHA512).Hash
        if ($actual -ne $expected.ToUpperInvariant()) { throw 'SHA-512 فایل دانلودشده با هش ثبت‌شده در اسکریپت نمی‌خواند؛ فایل دور انداخته شد.' }
        Move-Item -LiteralPath $download -Destination $phar -Force
    }
    catch {
        if (Test-Path -LiteralPath $download) { Remove-Item -LiteralPath $download -Force }
        throw "دانلود wp-cli.phar ناموفق بود: $($_.Exception.Message) — می‌توانی فایل را دستی در $phar بگذاری."
    }
    $size = [math]::Round((Get-Item -LiteralPath $phar).Length / 1MB, 1)
    Write-Host "wp-cli.phar ذخیره شد ($size MB، SHA-512 تأیید شد): $phar"
    return $phar
}

$prevOutputEncoding = [Console]::OutputEncoding
$prevPipeEncoding   = $OutputEncoding
try {
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [Console]::OutputEncoding = $utf8
    $OutputEncoding = $utf8
} catch { }

$exitCode = 0
try {
    $site = $defaultSite
    if ($env:GADGETIM_WP_PATH) { $site = $env:GADGETIM_WP_PATH }
    # No trailing backslash: before a closing quote it would escape the quote on php's command line.
    $site = $site.TrimEnd('\')

    if ($wpArgs.Count -eq 0) {
        Write-Host 'tools\wp.ps1 — WP-CLI روی سایت Local'
        Write-Host 'کاربرد: tools\wp.ps1 <دستور wp>     مثال: tools\wp.ps1 option get siteurl'
        Write-Host ''
        foreach ($pair in @(@('php.exe', { Resolve-GtmPhp }), @('php.ini', { Resolve-GtmPhpIni }))) {
            try { Write-Host ("{0,-8}: {1}" -f $pair[0], (& $pair[1])) }
            catch { Write-Host ("{0,-8}: ✘ {1}" -f $pair[0], $_.Exception.Message) -ForegroundColor Yellow }
        }
        Write-Host ("{0,-8}: {1}" -f 'site', $site)
        if (Test-Path -LiteralPath $phar -PathType Leaf) {
            Write-Host ("{0,-8}: {1} (موجود)" -f 'wp-cli', $phar)
        } else {
            Write-Host ("{0,-8}: {1} (موجود نیست؛ در اولین اجرا دانلود می‌شود)" -f 'wp-cli', $phar)
        }
        exit 0
    }

    $php = Resolve-GtmPhp
    $ini = Resolve-GtmPhpIni
    $wp  = Get-GtmWpCli

    $hasPath = $false
    foreach ($a in $wpArgs) {
        if (($a -like '--path=*') -or ($a -eq '--path')) { $hasPath = $true }
    }

    # -d display_startup_errors=0 hides the harmless php_imagick.dll warning of Local's ini.
    $invoke = @('-c', $ini, '-d', 'display_startup_errors=0', '-d', 'memory_limit=512M', $wp)
    if (-not $hasPath) { $invoke += "--path=$site" }
    $invoke += $wpArgs

    & $php @invoke
    $exitCode = $LASTEXITCODE
}
catch {
    Write-Host "✘ $($_.Exception.Message)" -ForegroundColor Red
    $exitCode = 1
}
finally {
    try {
        [Console]::OutputEncoding = $prevOutputEncoding
        $OutputEncoding = $prevPipeEncoding
    } catch { }
}
exit $exitCode
