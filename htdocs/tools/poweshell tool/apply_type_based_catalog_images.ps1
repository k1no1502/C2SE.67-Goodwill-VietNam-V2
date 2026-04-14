$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'
$tmpDir = Join-Path $root 'tools\_img_cache'

New-Item -ItemType Directory -Force -Path $tmpDir | Out-Null

function Get-ImageType([string]$name) {
    $n = $name.ToLowerInvariant()
    if ($n -match 'ao\s+so\s+mi') { return 'dress-shirt' }
    if ($n -match 'ao\s+thun') { return 'tshirt' }
    if ($n -match 'quan\s+jean') { return 'jeans' }
    if ($n -match 'vay\s+midi') { return 'midi-dress' }
    if ($n -match 'dam\s+cong\s+so') { return 'office-dress' }
    if ($n -match 'ao\s+khoac\s+hoodie') { return 'hoodie' }
    if ($n -match 'ao\s+len') { return 'sweater' }
    if ($n -match 'quan\s+short\s+kaki') { return 'khaki-shorts' }
    if ($n -match 'ao\s+the\s+thao') { return 'sports-shirt' }
    if ($n -match 'quan\s+tay') { return 'trousers' }
    if ($n -match 'chan\s+vay') { return 'skirt' }
    if ($n -match 'ao\s+khoac\s+jean') { return 'denim-jacket' }

    if ($n -match 'tai\s+nghe') { return 'headphones' }
    if ($n -match 'loa\s+mini') { return 'speaker' }
    if ($n -match 'ban\s+phim\s+co') { return 'keyboard' }
    if ($n -match 'chuot\s+khong\s+day') { return 'mouse' }
    if ($n -match 'webcam') { return 'webcam' }
    if ($n -match 'may\s+tinh\s+bang') { return 'tablet' }
    if ($n -match 'dong\s+ho\s+thong\s+minh') { return 'smartwatch' }
    if ($n -match 'camera\s+an\s+ninh') { return 'security-camera' }
    if ($n -match 'may\s+in\s+mini') { return 'printer' }
    if ($n -match 'o\s+cung\s+ssd') { return 'ssd' }
    if ($n -match 'may\s+doc\s+sach') { return 'ereader' }

    if ($n -match 'noi\s+com\s+dien') { return 'rice-cooker' }
    if ($n -match 'am\s+sieu\s+toc') { return 'electric-kettle' }
    if ($n -match 'noi\s+chien\s+khong\s+dau') { return 'air-fryer' }
    if ($n -match 'ban\s+ui\s+hoi\s+nuoc') { return 'steam-iron' }
    if ($n -match 'quat\s+dung') { return 'standing-fan' }
    if ($n -match 'may\s+hut\s+bui\s+cam\s+tay') { return 'handheld-vacuum' }
    if ($n -match 'lo\s+vi\s+song') { return 'microwave' }
    if ($n -match 'may\s+xay\s+sinh\s+to') { return 'blender' }
    if ($n -match 'bo\s+noi\s+inox') { return 'cookware-set' }
    if ($n -match 'bep\s+tu\s+don') { return 'induction-cooktop' }
    if ($n -match 'may\s+loc\s+khong\s+khi') { return 'air-purifier' }

    return 'generic-product'
}

$typeToQuery = @{
    'dress-shirt' = 'white dress shirt product';
    'tshirt' = 'cotton t shirt product';
    'jeans' = 'blue jeans pants product';
    'midi-dress' = 'midi dress product';
    'office-dress' = 'office dress product';
    'hoodie' = 'hoodie sweatshirt product';
    'sweater' = 'knit sweater product';
    'khaki-shorts' = 'khaki shorts product';
    'sports-shirt' = 'sports shirt dry fit product';
    'trousers' = 'formal trousers product';
    'skirt' = 'pleated skirt product';
    'denim-jacket' = 'denim jacket product';
    'headphones' = 'wireless headphones product';
    'speaker' = 'portable bluetooth speaker product';
    'keyboard' = 'mechanical keyboard product';
    'mouse' = 'wireless mouse product';
    'webcam' = 'hd webcam product';
    'tablet' = 'android tablet product';
    'smartwatch' = 'smartwatch product';
    'security-camera' = 'wifi security camera product';
    'printer' = 'mini printer product';
    'ssd' = 'ssd drive product';
    'ereader' = 'ebook reader device product';
    'rice-cooker' = 'electric rice cooker product';
    'electric-kettle' = 'stainless electric kettle product';
    'air-fryer' = 'air fryer product';
    'steam-iron' = 'steam iron product';
    'standing-fan' = 'standing fan product';
    'handheld-vacuum' = 'handheld vacuum cleaner product';
    'microwave' = 'microwave oven product';
    'blender' = 'kitchen blender product';
    'cookware-set' = 'stainless cookware set product';
    'induction-cooktop' = 'induction cooktop product';
    'air-purifier' = 'air purifier product';
    'generic-product' = 'consumer product photo'
}

$text = Get-Content -Raw -Path $sqlPath
$pattern = "INSERT INTO donations .*VALUES \(@user_id, '([^']+)', '.*?', \d+, \d+, '[^']+', '[^']+', \d+, JSON_ARRAY\('picture_Database/([a-z0-9-]+-\d{3}\.jpg)'\), 'approved'\);"
$matches = [regex]::Matches($text, $pattern)

$items = @()
$seen = New-Object 'System.Collections.Generic.HashSet[string]'
foreach ($m in $matches) {
    $name = $m.Groups[1].Value
    $file = $m.Groups[2].Value
    if ($seen.Add($file)) {
        $type = Get-ImageType $name
        $items += [pscustomobject]@{ Name = $name; File = $file; Type = $type }
    }
}

$typeCache = @{}
$typeOk = 0
$typeFail = 0

foreach ($type in ($items.Type | Sort-Object -Unique)) {
    $query = $typeToQuery[$type]
    if (-not $query) { $query = $typeToQuery['generic-product'] }
    $q = [uri]::EscapeDataString($query)
    $cacheFile = Join-Path $tmpDir ($type + '.jpg')

    $ok = $false
    foreach ($url in @("https://loremflickr.com/1200/675/$q?lock=20260327", "https://source.unsplash.com/1200x675/?$q")) {
        try {
            Invoke-WebRequest -Uri $url -OutFile $cacheFile -Headers @{ 'User-Agent' = 'Mozilla/5.0' } -MaximumRedirection 5 -TimeoutSec 45
            if ((Get-Item $cacheFile).Length -gt 1200) {
                $ok = $true
                break
            }
        }
        catch {
            # try fallback source
        }
    }

    if ($ok) {
        $typeCache[$type] = $cacheFile
        $typeOk++
    }
    else {
        $typeFail++
    }
}

$fileOk = 0
$fileFail = 0
foreach ($it in $items) {
    if ($typeCache.ContainsKey($it.Type)) {
        $src = $typeCache[$it.Type]
        Copy-Item -Path $src -Destination (Join-Path $dirA $it.File) -Force
        Copy-Item -Path $src -Destination (Join-Path $dirB $it.File) -Force
        $fileOk++
    }
    else {
        $fileFail++
    }
}

Write-Output ("catalog_items=" + $items.Count)
Write-Output ("types_ok=" + $typeOk)
Write-Output ("types_fail=" + $typeFail)
Write-Output ("files_updated=" + $fileOk)
Write-Output ("files_failed=" + $fileFail)