param(
    [string]$XamppPath = 'C:\xampp',
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [string]$Database = 'team01',
    [string]$User = 'team01',
    [string]$OutputPath = (Join-Path $PSScriptRoot '..\sql\dbdump.sql')
)

$dumpExecutable = Join-Path $XamppPath 'mysql\bin\mysqldump.exe'
if (-not (Test-Path -LiteralPath $dumpExecutable)) {
    throw "mysqldump.exe를 찾을 수 없습니다: $dumpExecutable"
}

$resolvedOutput = [System.IO.Path]::GetFullPath($OutputPath)
$outputDirectory = Split-Path -Parent $resolvedOutput
if (-not (Test-Path -LiteralPath $outputDirectory)) {
    throw "출력 폴더를 찾을 수 없습니다: $outputDirectory"
}

Write-Host 'DB 비밀번호를 입력하면 구조·데이터·뷰를 포함한 덤프를 생성합니다.'
& $dumpExecutable `
    --no-defaults `
    --protocol=TCP `
    --host=$HostName `
    --port=$Port `
    --user=$User `
    --password `
    --default-character-set=utf8mb4 `
    --single-transaction `
    --skip-lock-tables `
    --triggers `
    --result-file=$resolvedOutput `
    $Database

if ($LASTEXITCODE -ne 0) {
    throw "mysqldump 실행 실패: 종료 코드 $LASTEXITCODE"
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
$dumpText = [System.IO.File]::ReadAllText($resolvedOutput, $utf8WithoutBom)
$dumpText = [regex]::Replace(
    $dumpText,
    '/\*!50013 DEFINER=`[^`]+`@`[^`]+` SQL SECURITY DEFINER \*/',
    '/*!50013 SQL SECURITY INVOKER */'
)
$dumpText = [regex]::Replace($dumpText, '[ \t]+(?=\r?$)', '', 'Multiline')
[System.IO.File]::WriteAllText($resolvedOutput, $dumpText, $utf8WithoutBom)

Write-Host "덤프 생성 완료: $resolvedOutput"
