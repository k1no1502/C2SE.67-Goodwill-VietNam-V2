$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$outSqlPath = Join-Path $root 'tools\sync_catalog_images.sql'
$mysqlExe = 'c:\xampp\mysql\bin\mysql.exe'
$dbName = 'goodwill_vietnam'

$lines = Get-Content -Path $sqlPath
$updates = New-Object System.Collections.Generic.List[string]

foreach ($line in $lines) {
    if ($line -match "^INSERT INTO donations .*VALUES \(@user_id, '([^']+)', '.*?', \d+, \d+, '[^']+', '[^']+', (\d+), JSON_ARRAY\('([^']+)'\), 'approved'\);$") {
        $name = $Matches[1].Replace("'", "''")
        $estimated = $Matches[2]
        $img = $Matches[3].Replace("'", "''")
        [void]$updates.Add("UPDATE donations SET images = JSON_ARRAY('$img') WHERE item_name = '$name' AND estimated_value = $estimated AND status = 'approved';")
    }
    elseif ($line -match "^INSERT INTO inventory .*VALUES \(@donation_id, '([^']+)', '.*?', \d+, \d+, '[^']+', '[^']+', '[^']+', \d+, (\d+), JSON_ARRAY\('([^']+)'\), 'available'\);$") {
        $name = $Matches[1].Replace("'", "''")
        $estimated = $Matches[2]
        $img = $Matches[3].Replace("'", "''")
        [void]$updates.Add("UPDATE inventory SET images = JSON_ARRAY('$img') WHERE name = '$name' AND estimated_value = $estimated AND status IN ('available','reserved','sold');")
    }
}

if ($updates.Count -lt 200) {
    throw "Expected at least 200 updates, got $($updates.Count)"
}

Set-Content -Path $outSqlPath -Value ($updates -join "`r`n") -Encoding UTF8

if (!(Test-Path $mysqlExe)) {
    throw "mysql.exe not found at $mysqlExe"
}

Get-Content -Path $outSqlPath | & $mysqlExe -u root $dbName --default-character-set=utf8mb4

Write-Output ("updates_written=" + $updates.Count)
Write-Output "db_sync_done=1"