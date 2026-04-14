$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'

function Get-Slug([string]$text) {
    $s = $text.ToLowerInvariant()
    $s = [regex]::Replace($s, '[^a-z0-9]+', '-')
    $s = $s.Trim('-')
    if ([string]::IsNullOrWhiteSpace($s)) {
        return 'product'
    }
    return $s
}

if (!(Test-Path $sqlPath)) {
    throw "Missing SQL file: $sqlPath"
}

New-Item -ItemType Directory -Force -Path $dirA | Out-Null
New-Item -ItemType Directory -Force -Path $dirB | Out-Null

$text = Get-Content -Raw -Path $sqlPath
$startMarker = '-- IMPORT PRODUCT CATALOG DATA (100 items)'
$endMarker = '-- VIEWS'

$start = $text.IndexOf($startMarker)
$end = $text.IndexOf($endMarker)
if ($start -lt 0 -or $end -lt 0 -or $end -le $start) {
    throw 'Could not locate catalog block markers.'
}

$prefix = $text.Substring(0, $start)
$block = $text.Substring($start, $end - $start)
$suffix = $text.Substring($end)

$lines = $block -split "`r?`n"
$index = 0
$currentPath = ''
$catalog = @()
$fixedLines = New-Object System.Collections.Generic.List[string]

foreach ($line in $lines) {
    $newLine = $line
    if ($line -match "^INSERT INTO donations .*VALUES \(@user_id, '([^']+)'.*JSON_ARRAY\('picture_Database/[^']*'\), 'approved'\);") {
        $index++
        $name = $Matches[1]
        $slug = Get-Slug $name
        $currentPath = "picture_Database/$slug-{0}.jpg" -f $index.ToString('000')
        $catalog += [pscustomobject]@{ Name = $name; Path = $currentPath }
        $newLine = [regex]::Replace($line, "JSON_ARRAY\('picture_Database/[^']*'\)", "JSON_ARRAY('$currentPath')")
    }
    elseif ($line -match "^INSERT INTO inventory .*JSON_ARRAY\('picture_Database/[^']*'\), 'available'\);") {
        if (![string]::IsNullOrWhiteSpace($currentPath)) {
            $newLine = [regex]::Replace($line, "JSON_ARRAY\('picture_Database/[^']*'\)", "JSON_ARRAY('$currentPath')")
        }
    }
    [void]$fixedLines.Add($newLine)
}

if ($index -ne 100) {
    throw "Expected 100 donation rows in catalog block, found $index"
}

$newBlock = ($fixedLines -join "`r`n")
$newText = $prefix + $newBlock + $suffix
Set-Content -Path $sqlPath -Value $newText -Encoding UTF8

$ok = 0
$fail = 0
foreach ($item in $catalog) {
    $fileName = Split-Path $item.Path -Leaf
    $targetA = Join-Path $dirA $fileName
    $targetB = Join-Path $dirB $fileName

    $q = [uri]::EscapeDataString($item.Name + ' product')
    $urlPrimary = "https://loremflickr.com/1200/675/$q"
    $urlFallback = "https://source.unsplash.com/1200x675/?$q"

    $done = $false
    foreach ($u in @($urlPrimary, $urlFallback)) {
        try {
            Invoke-WebRequest -Uri $u -OutFile $targetA -Headers @{ 'User-Agent' = 'Mozilla/5.0' } -MaximumRedirection 5 -TimeoutSec 45
            if ((Test-Path $targetA) -and ((Get-Item $targetA).Length -gt 1000)) {
                Copy-Item -Path $targetA -Destination $targetB -Force
                $done = $true
                break
            }
        }
        catch {
            # Try next source.
        }
    }

    if ($done) { $ok++ } else { $fail++ }
    Start-Sleep -Milliseconds 80
}

Write-Output ("catalog_rows=$index")
Write-Output ("downloaded_ok=$ok")
Write-Output ("downloaded_fail=$fail")