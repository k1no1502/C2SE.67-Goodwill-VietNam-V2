$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'
$tmpDir = Join-Path $root 'tools\_tmp_unique_fetch'

New-Item -ItemType Directory -Force -Path $tmpDir | Out-Null

function Get-ProductQuery([string]$name) {
    $n = $name.ToLowerInvariant()

    if ($n -match 'ao\s+so\s+mi') { return 'white long sleeve dress shirt clothing' }
    if ($n -match 'ao\s+thun') { return 'cotton tshirt clothing' }
    if ($n -match 'quan\s+jean') { return 'blue jeans pants clothing' }
    if ($n -match 'vay\s+midi') { return 'midi dress women clothing' }
    if ($n -match 'dam\s+cong\s+so') { return 'office dress women clothing' }
    if ($n -match 'ao\s+khoac\s+hoodie') { return 'hoodie jacket clothing' }
    if ($n -match 'ao\s+len') { return 'knit sweater clothing' }
    if ($n -match 'quan\s+short\s+kaki') { return 'khaki shorts clothing' }
    if ($n -match 'ao\s+the\s+thao') { return 'sports shirt dry fit clothing' }
    if ($n -match 'quan\s+tay') { return 'formal trousers pants clothing' }
    if ($n -match 'chan\s+vay') { return 'pleated skirt clothing' }
    if ($n -match 'ao\s+khoac\s+jean') { return 'denim jacket clothing' }

    if ($n -match 'tai\s+nghe') { return 'wireless bluetooth headphones device' }
    if ($n -match 'loa\s+mini') { return 'portable bluetooth speaker device' }
    if ($n -match 'ban\s+phim\s+co') { return 'mechanical keyboard device' }
    if ($n -match 'chuot\s+khong\s+day') { return 'wireless computer mouse device' }
    if ($n -match 'webcam') { return 'hd webcam device' }
    if ($n -match 'may\s+tinh\s+bang') { return 'android tablet device' }
    if ($n -match 'dong\s+ho\s+thong\s+minh') { return 'smartwatch device' }
    if ($n -match 'camera\s+an\s+ninh') { return 'wifi security camera device' }
    if ($n -match 'may\s+in\s+mini') { return 'portable mini printer device' }
    if ($n -match 'o\s+cung\s+ssd') { return 'ssd solid state drive device' }
    if ($n -match 'may\s+doc\s+sach') { return 'e ink ebook reader device' }

    if ($n -match 'noi\s+com\s+dien') { return 'electric rice cooker appliance' }
    if ($n -match 'am\s+sieu\s+toc') { return 'stainless electric kettle appliance' }
    if ($n -match 'noi\s+chien\s+khong\s+dau') { return 'air fryer appliance' }
    if ($n -match 'ban\s+ui\s+hoi\s+nuoc') { return 'steam iron appliance' }
    if ($n -match 'quat\s+dung') { return 'standing fan appliance' }
    if ($n -match 'may\s+hut\s+bui\s+cam\s+tay') { return 'handheld vacuum cleaner appliance' }
    if ($n -match 'lo\s+vi\s+song') { return 'microwave oven appliance' }
    if ($n -match 'may\s+xay\s+sinh\s+to') { return 'kitchen blender appliance' }
    if ($n -match 'bo\s+noi\s+inox') { return 'stainless cookware set appliance' }
    if ($n -match 'bep\s+tu\s+don') { return 'induction cooktop appliance' }
    if ($n -match 'may\s+loc\s+khong\s+khi') { return 'air purifier appliance' }

    return 'consumer product'
}

function Try-Download([string]$url, [string]$dest) {
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

$seenFiles = New-Object 'System.Collections.Generic.HashSet[string]'
$items = @()
foreach ($m in $matches) {
    $name = $m.Groups[1].Value
    $file = $m.Groups[2].Value
    if ($seenFiles.Add($file)) {
        $items += [pscustomobject]@{ Name = $name; File = $file }
    }
}

$usedHashes = New-Object 'System.Collections.Generic.HashSet[string]'
$ok = 0
$fail = 0
$uniqueOk = 0

for ($i = 0; $i -lt $items.Count; $i++) {
    $item = $items[$i]
    $query = Get-ProductQuery $item.Name
    $q = [uri]::EscapeDataString($query)

    $targetA = Join-Path $dirA $item.File
    $targetB = Join-Path $dirB $item.File
    $tmp = Join-Path $tmpDir ("tmp_" + $item.File)

    $saved = $false

    for ($attempt = 0; $attempt -lt 8 -and -not $saved; $attempt++) {
        $sig = $i + 1 + ($attempt * 157)
        $lock = 20260327 + $sig
        $urls = @(
            "https://source.unsplash.com/1200x675/?$q&sig=$sig",
            "https://loremflickr.com/1200/675/$q?lock=$lock"
        )

        foreach ($u in $urls) {
            if (-not (Try-Download -url $u -dest $tmp)) {
                continue
            }

            $hash = (Get-FileHash -Path $tmp -Algorithm SHA256).Hash
            if ($usedHashes.Contains($hash)) {
                continue
            }

            Copy-Item -Path $tmp -Destination $targetA -Force
            Copy-Item -Path $tmp -Destination $targetB -Force
            [void]$usedHashes.Add($hash)
            $saved = $true
            break
        }
    }

    if ($saved) {
        $ok++
        $uniqueOk++
    }
    else {
        $fail++
    }

    Start-Sleep -Milliseconds 80
}

Write-Output ("catalog_items=" + $items.Count)
Write-Output ("updated_ok=" + $ok)
Write-Output ("updated_fail=" + $fail)
Write-Output ("unique_hash_count=" + $usedHashes.Count)