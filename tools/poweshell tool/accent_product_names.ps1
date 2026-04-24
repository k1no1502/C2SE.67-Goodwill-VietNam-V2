$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$mysqlExe = 'c:\xampp\mysql\bin\mysql.exe'
$dbName = 'goodwill_vietnam'

function Convert-ProductName([string]$name) {
    $n = $name

    # Leading word accents
    if ($n -match '^Ao ') { $n = $n -replace '^Ao ', 'Áo ' }
    if ($n -match '^Am ') { $n = $n -replace '^Am ', 'Ấm ' }
    if ($n -match '^Ban ') { $n = $n -replace '^Ban ', 'Bàn ' }
    if ($n -match '^Bep ') { $n = $n -replace '^Bep ', 'Bếp ' }
    if ($n -match '^Bo ') { $n = $n -replace '^Bo ', 'Bộ ' }
    if ($n -match '^Chan ') { $n = $n -replace '^Chan ', 'Chân ' }
    if ($n -match '^Chuot ') { $n = $n -replace '^Chuot ', 'Chuột ' }
    if ($n -match '^Dam ') { $n = $n -replace '^Dam ', 'Đầm ' }
    if ($n -match '^Dong ') { $n = $n -replace '^Dong ', 'Đồng ' }
    if ($n -match '^Lo ') { $n = $n -replace '^Lo ', 'Lò ' }
    if ($n -match '^May ') { $n = $n -replace '^May ', 'Máy ' }
    if ($n -match '^Noi ') { $n = $n -replace '^Noi ', 'Nồi ' }
    if ($n -match '^O ') { $n = $n -replace '^O ', 'Ổ ' }
    if ($n -match '^Quan ') { $n = $n -replace '^Quan ', 'Quần ' }
    if ($n -match '^Quat ') { $n = $n -replace '^Quat ', 'Quạt ' }
    if ($n -match '^Vay ') { $n = $n -replace '^Vay ', 'Váy ' }

    # Phrase accents
    $pairs = @(
        @('so mi','sơ mi'),
        @('tay dai','tay dài'),
        @('mau den','màu đen'),
        @('mau trang','màu trắng'),
        @('mau xanh navy','màu xanh navy'),
        @('khoac','khoác'),
        @('len mong','len mỏng'),
        @('the thao','thể thao'),
        @('xep ly','xếp ly'),
        @('phim co','phím cơ'),
        @('khong day','không dây'),
        @('phien ban','phiên bản'),
        @('gia dinh','gia đình'),
        @('co ban','cơ bản'),
        @('nang cao','nâng cao'),
        @('pin lon','pin lớn'),
        @('cong so','công sở'),
        @('khong dau','không dầu'),
        @('hoi nuoc','hơi nước'),
        @('tiet kiem dien','tiết kiệm điện'),
        @('hut bui','hút bụi'),
        @('vi song','vi sóng'),
        @('sinh to','sinh tố'),
        @('loc khong khi','lọc không khí'),
        @('da nang','đa năng'),
        @('tinh bang','tính bảng'),
        @('doc sach','đọc sách'),
        @('thong minh','thông minh'),
        @('sieu toc','siêu tốc')
    )

    foreach ($p in $pairs) {
        $n = [regex]::Replace($n, [regex]::Escape($p[0]), $p[1], [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    }

    # Common phrase normalization
    $n = $n -replace 'Dry fit', 'Dry Fit'
    $n = $n -replace 'Wifi', 'WiFi'

    return $n
}

if (!(Test-Path $sqlPath)) {
    throw "Missing file: $sqlPath"
}

$text = Get-Content -Raw -Path $sqlPath
$matches = [regex]::Matches($text, "INSERT INTO donations .*VALUES \(@user_id, '([^']+)'")

$oldNames = New-Object 'System.Collections.Generic.HashSet[string]'
foreach ($m in $matches) {
    [void]$oldNames.Add($m.Groups[1].Value)
}

$map = @{}
foreach ($old in ($oldNames | Sort-Object)) {
    $new = Convert-ProductName $old
    if ($old -ne $new) {
        $map[$old] = $new
    }
}

if ($map.Count -eq 0) {
    Write-Output 'updated_names=0'
    exit 0
}

foreach ($old in $map.Keys) {
    $new = $map[$old]
    $text = $text.Replace("'$old'", "'$new'")
}

Set-Content -Path $sqlPath -Value $text -Encoding UTF8

if (Test-Path $mysqlExe) {
    foreach ($old in $map.Keys) {
        $new = $map[$old]
        $oldSql = $old.Replace("'", "''")
        $newSql = $new.Replace("'", "''")
        $q1 = "UPDATE donations SET item_name = '$newSql' WHERE item_name = '$oldSql';"
        $q2 = "UPDATE inventory SET name = '$newSql' WHERE name = '$oldSql';"
        & $mysqlExe -u root --default-character-set=utf8mb4 -D $dbName -e $q1 | Out-Null
        & $mysqlExe -u root --default-character-set=utf8mb4 -D $dbName -e $q2 | Out-Null
    }
}

Write-Output ("updated_names=" + $map.Count)