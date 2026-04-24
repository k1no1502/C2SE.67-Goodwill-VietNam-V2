$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'

function Get-ProductQuery([string]$name) {
    $n = $name.ToLowerInvariant()

    # Clothing
    if ($n -match 'ao\s+so\s+mi') { return 'white long sleeve dress shirt' }
    if ($n -match 'ao\s+thun') { return 'black cotton t shirt' }
    if ($n -match 'quan\s+jean') { return 'blue slim fit jeans' }
    if ($n -match 'vay\s+midi') { return 'black midi dress' }
    if ($n -match 'dam\s+cong\s+so') { return 'white office dress' }
    if ($n -match 'ao\s+khoac\s+hoodie') { return 'white hoodie jacket' }
    if ($n -match 'ao\s+len') { return 'navy knit sweater' }
    if ($n -match 'quan\s+short\s+kaki') { return 'khaki shorts' }
    if ($n -match 'ao\s+the\s+thao') { return 'navy sports shirt' }
    if ($n -match 'quan\s+tay') { return 'black formal trousers' }
    if ($n -match 'chan\s+vay') { return 'white pleated skirt' }
    if ($n -match 'ao\s+khoac\s+jean') { return 'denim jacket' }

    # Electronics
    if ($n -match 'tai\s+nghe') { return 'wireless bluetooth headphones' }
    if ($n -match 'loa\s+mini') { return 'portable bluetooth speaker' }
    if ($n -match 'ban\s+phim\s+co') { return 'mechanical keyboard' }
    if ($n -match 'chuot\s+khong\s+day') { return 'wireless computer mouse' }
    if ($n -match 'webcam') { return 'usb hd webcam' }
    if ($n -match 'may\s+tinh\s+bang') { return 'android tablet device' }
    if ($n -match 'dong\s+ho\s+thong\s+minh') { return 'smartwatch' }
    if ($n -match 'camera\s+an\s+ninh') { return 'wifi security camera' }
    if ($n -match 'may\s+in\s+mini') { return 'portable mini printer' }
    if ($n -match 'o\s+cung\s+ssd') { return 'solid state drive ssd' }
    if ($n -match 'may\s+doc\s+sach') { return 'e ink ebook reader' }

    # Home appliances
    if ($n -match 'noi\s+com\s+dien') { return 'electric rice cooker' }
    if ($n -match 'am\s+sieu\s+toc') { return 'stainless electric kettle' }
    if ($n -match 'noi\s+chien\s+khong\s+dau') { return 'air fryer' }
    if ($n -match 'ban\s+ui\s+hoi\s+nuoc') { return 'steam iron' }
    if ($n -match 'quat\s+dung') { return 'standing electric fan' }
    if ($n -match 'may\s+hut\s+bui\s+cam\s+tay') { return 'handheld vacuum cleaner' }
    if ($n -match 'lo\s+vi\s+song') { return 'microwave oven' }
    if ($n -match 'may\s+xay\s+sinh\s+to') { return 'kitchen blender' }
    if ($n -match 'bo\s+noi\s+inox') { return 'stainless steel cookware set' }
    if ($n -match 'bep\s+tu\s+don') { return 'induction cooktop' }
    if ($n -match 'may\s+loc\s+khong\s+khi') { return 'air purifier' }

    return 'consumer product'
}

function Get-WikimediaImageUrl([string]$query) {
    $encoded = [uri]::EscapeDataString($query)
    $api = "https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrnamespace=6&gsrlimit=25&gsrsearch=$encoded&prop=imageinfo&iiprop=url|mime&format=json"

    try {
        $resp = Invoke-RestMethod -Uri $api -TimeoutSec 45
        if (-not $resp.query.pages) {
            return $null
        }

        $pages = $resp.query.pages.PSObject.Properties.Value
        foreach ($p in $pages) {
            if (-not $p.imageinfo -or $p.imageinfo.Count -eq 0) { continue }
            $info = $p.imageinfo[0]
            if (-not $info.url) { continue }

            $title = ($p.title | ForEach-Object { $_.ToString().ToLowerInvariant() })
            $mime = ($info.mime | ForEach-Object { $_.ToString().ToLowerInvariant() })
            $url = $info.url.ToString()

            if ($mime -notmatch 'image/(jpeg|jpg|png)') { continue }
            if ($title -match 'logo|icon|symbol|map|diagram|flag|coat of arms') { continue }
            if ($url -match '\.svg($|\?)') { continue }

            return $url
        }
    }
    catch {
        return $null
    }

    return $null
}

function Download-Image([string]$url, [string]$dest) {
    try {
        Invoke-WebRequest -Uri $url -OutFile $dest -Headers @{ 'User-Agent' = 'Mozilla/5.0' } -MaximumRedirection 5 -TimeoutSec 60
        if ((Test-Path $dest) -and ((Get-Item $dest).Length -gt 1500)) {
            return $true
        }
    }
    catch {
        return $false
    }
    return $false
}

$text = Get-Content -Raw -Path $sqlPath
$pattern = "INSERT INTO donations .*VALUES \(@user_id, '([^']+)', '.*?', \d+, \d+, '[^']+', '[^']+', \d+, JSON_ARRAY\('picture_Database/([a-z0-9-]+-\d{3}\.jpg)'\), 'approved'\);"
$matches = [regex]::Matches($text, $pattern)

$seen = New-Object 'System.Collections.Generic.HashSet[string]'
$items = @()
foreach ($m in $matches) {
    $name = $m.Groups[1].Value
    $file = $m.Groups[2].Value
    if ($seen.Add($file)) {
        $items += [pscustomobject]@{ Name = $name; File = $file }
    }
}

$ok = 0
$fail = 0
$fallback = 0

foreach ($it in $items) {
    $query = Get-ProductQuery $it.Name
    $wmUrl = Get-WikimediaImageUrl $query

    $targetA = Join-Path $dirA $it.File
    $targetB = Join-Path $dirB $it.File

    $done = $false
    if ($wmUrl) {
        $done = Download-Image -url $wmUrl -dest $targetA
    }

    if (-not $done) {
        $q = [uri]::EscapeDataString($query + ' product')
        $fallbackUrl = "https://source.unsplash.com/1200x675/?$q"
        $done = Download-Image -url $fallbackUrl -dest $targetA
        if ($done) { $fallback++ }
    }

    if ($done) {
        Copy-Item -Path $targetA -Destination $targetB -Force
        $ok++
    }
    else {
        $fail++
    }

    Start-Sleep -Milliseconds 60
}

Write-Output ("catalog_items=" + $items.Count)
Write-Output ("downloaded_ok=" + $ok)
Write-Output ("downloaded_fail=" + $fail)
Write-Output ("used_fallback_unsplash=" + $fallback)