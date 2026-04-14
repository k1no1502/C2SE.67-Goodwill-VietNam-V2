$ErrorActionPreference = 'Stop'

$root = 'c:\xampp\htdocs'
$sqlPath = Join-Path $root 'database\Database.sql'
$dirA = Join-Path $root 'picture_Database'
$dirB = Join-Path $root 'uploads\donations\picture_Database'

if (!(Test-Path $sqlPath)) {
    throw "SQL file not found: $sqlPath"
}

New-Item -ItemType Directory -Force -Path $dirA | Out-Null
New-Item -ItemType Directory -Force -Path $dirB | Out-Null

$text = Get-Content -Raw -Path $sqlPath
$regex = "JSON_ARRAY\('picture_Database/([^']+)-(\d{3})\.svg'\)"
$matches = [regex]::Matches($text, $regex)

$keys = New-Object 'System.Collections.Generic.HashSet[string]'
foreach ($m in $matches) {
    [void]$keys.Add($m.Groups[1].Value + '-' + $m.Groups[2].Value)
}

$all = $keys | Sort-Object
$ok = 0
$fail = 0

foreach ($k in $all) {
    $jpg = "$k.jpg"
    $parts = $k -split '-'
    if ($parts.Count -gt 1) {
        $parts = $parts[0..($parts.Count - 2)]
    }

    $query = ($parts -join ' ')
    $query = $query -replace '\b(mau|phien|ban|gia|dinh|nho|vua|lon|co|pin|nang|cao)\b', ''
    $query = ($query -replace '\s+', ' ').Trim()
    if ([string]::IsNullOrWhiteSpace($query)) {
        $query = 'product'
    }

    $url1 = 'https://source.unsplash.com/1200x675/?' + [uri]::EscapeDataString($query + ' product')
    $url2 = 'https://loremflickr.com/1200/675/' + [uri]::EscapeDataString($query)

    $targetA = Join-Path $dirA $jpg
    $targetB = Join-Path $dirB $jpg

    $done = $false
    foreach ($u in @($url1, $url2)) {
        try {
            Invoke-WebRequest -Uri $u -OutFile $targetA -Headers @{ 'User-Agent' = 'Mozilla/5.0' } -MaximumRedirection 5 -TimeoutSec 45
            if ((Test-Path $targetA) -and ((Get-Item $targetA).Length -gt 1024)) {
                Copy-Item $targetA $targetB -Force
                $done = $true
                break
            }
        }
        catch {
            # Try next provider.
        }
    }

    if ($done) {
        $ok++
    }
    else {
        $fail++
    }

    Start-Sleep -Milliseconds 80
}

# Rewrite SQL paths to JPG after download attempt.
$text = [regex]::Replace($text, 'picture_Database/([a-z0-9-]+-\d{3})\.svg', 'picture_Database/$1.jpg')
Set-Content -Path $sqlPath -Value $text -Encoding UTF8

Write-Output ("downloaded_ok=$ok")
Write-Output ("downloaded_fail=$fail")$ErrorActionPreference='Stop'
$root='c:\xampp\htdocs'
$sqlPath=Join-Path $root 'database\Database.sql'
$dirA=Join-Path $root 'picture_Database'
$dirB=Join-Path $root 'uploads\donations\picture_Database'
$text=Get-Content -Raw $sqlPath
$regex="JSON_ARRAY\('picture_Database/([^']+)-(\d{3})\.svg'\)"
$ms=[regex]::Matches($text,$regex)
$keys=@{}
foreach($m in $ms){ $keys[$m.Groups[1].Value+'-'+$m.Groups[2].Value]=1 }
$all=$keys.Keys | Sort-Object
$ok=0; $fail=0
foreach($k in $all){
  $jpg="$k.jpg"
  $parts=$k -split '-'
  if($parts.Count -gt 1){ $parts=$parts[0..($parts.Count-2)] }
  $q=($parts -join ' ')
  $q=$q -replace '\b(mau|phien|ban|gia|dinh|nho|vua|lon|co|pin|nang|cao)\b',''
  $q=($q -replace '\s+',' ').Trim()
  if([string]::IsNullOrWhiteSpace($q)){ $q='product' }
  $u1='https://source.unsplash.com/1200x675/?'+[uri]::EscapeDataString($q+' product')
  $u2='https://loremflickr.com/1200/675/'+[uri]::EscapeDataString($q)
  $targetA=Join-Path $dirA $jpg
  $targetB=Join-Path $dirB $jpg
  $done=$false
  foreach($u in @($u1,$u2)){
    try {
      Invoke-WebRequest -Uri $u -OutFile $targetA -Headers @{"User-Agent"="Mozilla/5.0"} -MaximumRedirection 5 -TimeoutSec 45
      if((Test-Path $targetA) -and ((Get-Item $targetA).Length -gt 1024)){
        Copy-Item $targetA $targetB -Force
        $done=$true
        break
      }
    } catch {}
  }
  if($done){ $ok++ } else { $fail++ }
  Start-Sleep -Milliseconds 100
}
$text = [regex]::Replace($text, "picture_Database/([a-z0-9-]+-\d{3})\.svg", "picture_Database/$1.jpg")
Set-Content -Path $sqlPath -Value $text -Encoding UTF8
Write-Output ("downloaded_ok=" + $ok)
Write-Output ("downloaded_fail=" + $fail)
