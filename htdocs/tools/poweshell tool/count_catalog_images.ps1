$root = 'c:\xampp\htdocs'
$jpgRoot = (Get-ChildItem (Join-Path $root 'picture_Database') -Filter '*-???.jpg' -File | Measure-Object).Count
$jpgUploads = (Get-ChildItem (Join-Path $root 'uploads\donations\picture_Database') -Filter '*-???.jpg' -File | Measure-Object).Count
$badRefs = (Select-String -Path (Join-Path $root 'database\Database.sql') -Pattern 'picture_Database/\.jpg' -AllMatches | Measure-Object).Count

Write-Output "jpg_root=$jpgRoot"
Write-Output "jpg_uploads=$jpgUploads"
Write-Output "bad_refs=$badRefs"