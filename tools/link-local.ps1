<#
.SYNOPSIS
    Links the repository packages into the Local site with directory junctions (architecture 2.4, decision D6).

.DESCRIPTION
    Creates or refreshes
        <Site>\themes\gadgetim        ->  <Repo>\themes\gadgetim
        <Site>\plugins\gadgetim-core  ->  <Repo>\plugins\gadgetim-core
    An existing link is removed with [System.IO.Directory]::Delete after a reparse-point check and never
    with Remove-Item: in Windows PowerShell 5.1 Remove-Item on a junction either fails ("directory is not
    empty") or, with -Recurse, follows the reparse point and deletes the REPOSITORY's files. A real
    directory at the target is never touched; the script refuses and asks you to move it away by hand.
    No admin rights needed. Windows PowerShell 5.1 compatible.

.PARAMETER Site
    wp-content directory of the Local site. Default: $env:GADGETIM_WP_PATH\wp-content, else the gadgetim site.

.PARAMETER Repo
    wp-content directory of this repository. Default: the repository that contains this script.

.PARAMETER Remove
    Only remove the junctions (safely); do not recreate them.

.PARAMETER Status
    Only report what sits at each target.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tools\link-local.ps1

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tools\link-local.ps1 -Remove
#>
[CmdletBinding()]
param(
    [string]$Site = '',
    [string]$Repo = '',
    [switch]$Remove,
    [switch]$Status
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

function Get-GtmLinkTarget {
    param([string]$Path)
    $item = Get-Item -LiteralPath $Path -Force
    $linkTarget = $null
    try { $linkTarget = $item.Target } catch { }
    if ($null -eq $linkTarget) { return '' }
    return [string](@($linkTarget)[0])
}

function Remove-GtmLink {
    # Removes ONLY a junction/symlink directory entry; refuses real directories.
    param([string]$Path)
    if (-not (Test-GtmReparsePoint $Path)) {
        throw "$Path وجود دارد و یک دایرکتوری واقعی است، نه پیوند. خودت آن را کنار بگذار؛ این اسکریپت چیزی را حذف نمی‌کند."
    }
    [System.IO.Directory]::Delete($Path)   # removes the link entry, never the target's contents
}

$prevEncoding = [Console]::OutputEncoding
try { [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false) } catch { }

$exitCode = 0
try {
    if (-not (Test-Path -LiteralPath $Site -PathType Container)) {
        throw "پوشهٴ wp-content سایت پیدا نشد: $Site"
    }
    Write-Host "سایت: $Site"
    Write-Host "ریپو: $Repo"
    Write-Host ''

    foreach ($p in $packages) {
        $target = Join-Path $Site $p
        $source = Join-Path $Repo $p

        $exists = Test-Path -LiteralPath $target
        $isLink = $false
        if ($exists) { $isLink = Test-GtmReparsePoint $target }

        if ($Status) {
            if (-not $exists) {
                Write-Host "[-] $p : در سایت وجود ندارد"
            } elseif ($isLink) {
                Write-Host "[=] $p : پیوند -> $(Get-GtmLinkTarget $target)"
            } else {
                Write-Host "[!] $p : دایرکتوری واقعی است، نه پیوند"
            }
            continue
        }

        if ($exists) {
            Remove-GtmLink $target
            Write-Host "[x] پیوند قبلی حذف شد: $target"
        }
        if ($Remove) { continue }

        if (-not (Test-Path -LiteralPath $source -PathType Container)) {
            Write-Host "[ ] $p : در ریپو هنوز وجود ندارد، رد شد ($source)"
            continue
        }
        $parent = Split-Path -Parent $target
        if (-not (Test-Path -LiteralPath $parent -PathType Container)) {
            New-Item -ItemType Directory -Path $parent -Force | Out-Null
        }
        New-Item -ItemType Junction -Path $target -Target $source | Out-Null
        Write-Host "[+] $p : $target -> $(Get-GtmLinkTarget $target)"
    }

    if (-not $Status -and -not $Remove) {
        Write-Host ''
        Write-Host 'گام بعد: در wp-admin پوستهٴ «Gadgetim — Timcheh» و افزونهٴ gadgetim-core را فعال کن. داخل Local Sites چیزی را ویرایش نکن.'
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
