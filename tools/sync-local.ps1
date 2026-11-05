<#
.SYNOPSIS
    One-way mirror of the repository packages into the Local site with robocopy (fallback for link-local.ps1, architecture 2.4).

.DESCRIPTION
    robocopy /MIR  <Repo>\<package>  ->  <Site>\<package>
    The site copy becomes identical to the repository: extra files at the destination are DELETED.
    Nothing is ever copied back. A destination that is a junction is refused, because then the site is
    already linked to the repository (use link-local.ps1, or link-local.ps1 -Remove first).
    Windows PowerShell 5.1 compatible.

.PARAMETER Site
    wp-content directory of the Local site. Default: $env:GADGETIM_WP_PATH\wp-content, else the gadgetim site.

.PARAMETER Repo
    wp-content directory of this repository. Default: the repository that contains this script.

.PARAMETER DryRun
    List what would change (robocopy /L) without copying or deleting anything.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tools\sync-local.ps1 -DryRun
#>
[CmdletBinding()]
param(
    [string]$Site = '',
    [string]$Repo = '',
    [switch]$DryRun
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$packages = @('themes\gadgetim', 'plugins\gadgetim-core')

if ($Site -eq '') {
    if ($env:GADGETIM_WP_PATH) {
        $Site = Join-Path $env:GADGETIM_WP_PATH 'wp-content'
    } else {
        $Site = 'C:\Users\GHADER\Local Sites\gadgetim\app\public\wp-content'
    }
}
if ($Repo -eq '') {
    $Repo = Join-Path (Split-Path -Parent $PSScriptRoot) 'wp-content'
}

function Test-GtmReparsePoint {
    param([string]$Path)
    $item = Get-Item -LiteralPath $Path -Force
    return (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0)
}

$prevEncoding = [Console]::OutputEncoding
try { [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false) } catch { }

$exitCode = 0
try {
    if (-not (Test-Path -LiteralPath $Site -PathType Container)) {
        throw "پوشهٴ wp-content سایت پیدا نشد: $Site"
    }
    if ($null -eq (Get-Command robocopy.exe -ErrorAction SilentlyContinue)) {
        throw 'robocopy.exe پیدا نشد.'
    }
    Write-Host "ریپو -> سایت (یک‌طرفه، /MIR)"
    Write-Host "سایت: $Site"
    Write-Host "ریپو: $Repo"
    if ($DryRun) { Write-Host 'حالت آزمایشی: فقط فهرست تغییرات، بدون کپی یا حذف.' }
    Write-Host ''

    foreach ($p in $packages) {
        # No trailing backslash: a backslash before the closing quote would escape it on the command line.
        $source = (Join-Path $Repo $p).TrimEnd('\')
        $target = (Join-Path $Site $p).TrimEnd('\')

        if (-not (Test-Path -LiteralPath $source -PathType Container)) {
            Write-Host "[ ] $p : در ریپو هنوز وجود ندارد، رد شد ($source)"
            continue
        }
        if ((Test-Path -LiteralPath $target) -and (Test-GtmReparsePoint $target)) {
            Write-Host "[!] $p : مقصد یک پیوند (junction) است؛ سایت از قبل به ریپو وصل است. چیزی کپی نشد." -ForegroundColor Yellow
            continue
        }

        $rcArgs = @($source, $target, '/MIR', '/NJH', '/NJS', '/NDL', '/NP', '/R:2', '/W:1', '/XD', '.git', 'node_modules', '/XF', '*.log')
        if ($DryRun) { $rcArgs += '/L' }

        Write-Host "[>] $p"
        & robocopy.exe @rcArgs
        $code = $LASTEXITCODE

        # robocopy exit bits: 1 copied, 2 extras removed, 4 mismatches, 8 copy failures, 16 fatal error.
        if ($code -ge 8) {
            Write-Host "    ✘ robocopy با کد $code شکست خورد." -ForegroundColor Red
            $exitCode = 1
        } elseif ($code -eq 0) {
            Write-Host '    ✔ بدون تغییر'
        } else {
            $what = @()
            if ($code -band 1) { $what += 'فایل‌هایی کپی شد' }
            if ($code -band 2) { $what += 'فایل‌های اضافی مقصد حذف شد' }
            if ($code -band 4) { $what += 'ناهمخوانی نوع فایل/پوشه' }
            Write-Host ("    ✔ " + ($what -join '، '))
        }
    }
}
catch {
    Write-Host "✘ $($_.Exception.Message)" -ForegroundColor Red
    $exitCode = 1
}
finally {
    try { [Console]::OutputEncoding = $prevEncoding } catch { }
}
exit $exitCode
