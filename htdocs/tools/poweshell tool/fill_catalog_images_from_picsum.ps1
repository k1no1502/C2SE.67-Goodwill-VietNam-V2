$ErrorActionPreference = 'Stop'

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

$files = $set | Sort-Object
$ok = 0
$fail = 0

foreach ($f in $files) {
    $targetA = Join-Path $dirA $f
    $targetB = Join-Path $dirB $f
    $seed = [uri]::EscapeDataString([System.IO.Path]::GetFileNameWithoutExtension($f))
    $url = "https://picsum.photos/seed/$seed/1200/675"

    try {
        Invoke-WebRequest -Uri $url -OutFile $targetA -Headers @{ 'User-Agent' = 'Mozilla/5.0' } -MaximumRedirection 5 -TimeoutSec 45
        if ((Get-Item $targetA).Length -gt 1000) {
            Copy-Item -Path $targetA -Destination $targetB -Force
            $ok++
        }
        else {
            $fail++
        }
    }
    catch {
        $fail++
    }

    Start-Sleep -Milliseconds 60
}

Write-Output ("total_files=" + $files.Count)
Write-Output ("downloaded_ok=" + $ok)
Write-Output ("downloaded_fail=" + $fail)