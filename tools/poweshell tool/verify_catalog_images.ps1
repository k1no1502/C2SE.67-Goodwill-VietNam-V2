$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'

$text = Get-Content -Raw -Path $sqlPath
$matches = [regex]::Matches($text, "picture_Database/([a-z0-9-]+-\d{3}\.jpg)")
$set = New-Object 'System.Collections.Generic.HashSet[string]'
foreach ($m in $matches) {
    [void]$set.Add($m.Groups[1].Value)
}

$missingA = 0
$missingB = 0
foreach ($f in $set) {
    if (!(Test-Path (Join-Path $dirA $f))) { $missingA++ }
    if (!(Test-Path (Join-Path $dirB $f))) { $missingB++ }
}

Write-Output ("sql_unique_files=" + $set.Count)
Write-Output ("missing_in_root=" + $missingA)
Write-Output ("missing_in_uploads=" + $missingB)