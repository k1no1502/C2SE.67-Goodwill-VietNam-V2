$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'

function Get-ProductQuery([string]$name) {
    $n = $name.ToLowerInvariant()

    # Clothing
    if ($n -match 'ao\s+so\s+mi') { return 'white long sleeve dress shirt clothing product' }
    if ($n -match 'ao\s+thun') { return 'cotton t shirt clothing product' }
    if ($n -match 'quan\s+jean') { return 'blue denim jeans pants clothing product' }
    if ($n -match 'vay\s+midi') { return 'midi dress women clothing product' }
    if ($n -match 'dam\s+cong\s+so') { return 'office dress women clothing product' }
    if ($n -match 'ao\s+khoac\s+hoodie') { return 'hoodie jacket clothing product' }
    if ($n -match 'ao\s+len') { return 'knit sweater clothing product' }
    if ($n -match 'quan\s+short\s+kaki') { return 'khaki shorts clothing product' }
    if ($n -match 'ao\s+the\s+thao') { return 'sports t shirt dry fit clothing product' }
    if ($n -match 'quan\s+tay') { return 'formal trousers pants clothing product' }
    if ($n -match 'chan\s+vay') { return 'pleated skirt women clothing product' }
    if ($n -match 'ao\s+khoac\s+jean') { return 'denim jacket clothing product' }

    # Electronics
    if ($n -match 'tai\s+nghe') { return 'wireless bluetooth headphones product' }
    if ($n -match 'loa\s+mini') { return 'portable bluetooth speaker product' }
    if ($n -match 'ban\s+phim\s+co') { return 'mechanical keyboard product' }
    if ($n -match 'chuot\s+khong\s+day') { return 'wireless mouse product' }
    if ($n -match 'webcam') { return 'hd webcam product' }
    if ($n -match 'may\s+tinh\s+bang') { return 'android tablet product' }
    if ($n -match 'dong\s+ho\s+thong\s+minh') { return 'smartwatch product' }
    if ($n -match 'camera\s+an\s+ninh') { return 'wifi security camera product' }
    if ($n -match 'may\s+in\s+mini') { return 'portable mini printer product' }
    if ($n -match 'o\s+cung\s+ssd') { return 'ssd drive product' }
    if ($n -match 'may\s+doc\s+sach') { return 'e ink ebook reader product' }

    # Home appliances
    if ($n -match 'noi\s+com\s+dien') { return 'electric rice cooker product' }
    if ($n -match 'am\s+sieu\s+toc') { return 'electric kettle stainless steel product' }
    if ($n -match 'noi\s+chien\s+khong\s+dau') { return 'air fryer product' }
    if ($n -match 'ban\s+ui\s+hoi\s+nuoc') { return 'steam iron product' }
    if ($n -match 'quat\s+dung') { return 'standing fan product' }
    if ($n -match 'may\s+hut\s+bui\s+cam\s+tay') { return 'handheld vacuum cleaner product' }
    if ($n -match 'lo\s+vi\s+song') { return 'microwave oven product' }
    if ($n -match 'may\s+xay\s+sinh\s+to') { return 'blender appliance product' }
    if ($n -match 'bo\s+noi\s+inox') { return 'stainless steel cookware set product' }
    if ($n -match 'bep\s+tu\s+don') { return 'induction cooktop stove product' }
    if ($n -match 'may\s+loc\s+khong\s+khi') { return 'air purifier product' }

    return 'consumer product photo'
}

function Try-Download([string]$url, [string]$dest) {
    try {
        Invoke-WebRequest -Uri $url -OutFile $dest -Headers @{ 'User-Agent' = 'Mozilla/5.0' } -MaximumRedirection 5 -TimeoutSec 45
        if ((Test-Path $dest) -and ((Get-Item $dest).Length -gt 1200)) {
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
        $items += [pscustomobject]@{
            Name = $name
            File = $file
        }
    }
}

$ok = 0
$fail = 0
foreach ($it in $items) {
    $query = Get-ProductQuery $it.Name
    $q = [uri]::EscapeDataString($query)

    $url1 = "https://source.unsplash.com/1200x675/?$q"
    $url2 = "https://loremflickr.com/1200/675/$q"

    $targetA = Join-Path $dirA $it.File
    $targetB = Join-Path $dirB $it.File

    $done = $false
    foreach ($u in @($url1, $url2)) {
        if (Try-Download -url $u -dest $targetA) {
            Copy-Item -Path $targetA -Destination $targetB -Force
            $done = $true
            break
        }
    }

    if ($done) { $ok++ } else { $fail++ }
    Start-Sleep -Milliseconds 100
}

Write-Output ("catalog_items=" + $items.Count)
Write-Output ("downloaded_ok=" + $ok)
Write-Output ("downloaded_fail=" + $fail)