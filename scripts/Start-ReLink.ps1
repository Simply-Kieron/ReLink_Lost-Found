param([switch]$NoBrowser, [switch]$Stop)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$runtimeRoot = Join-Path $projectRoot '.relink-runtime'
$configPath = Join-Path $runtimeRoot 'apache.conf'
$pidPath = Join-Path $runtimeRoot 'httpd.pid'
$apacheCandidates = @('C:\wamp\bin\apache', 'C:\wamp64\bin\apache')
$apache = $apacheCandidates | Where-Object { Test-Path -LiteralPath $_ } | ForEach-Object { Get-ChildItem -LiteralPath $_ -Directory | ForEach-Object { Join-Path $_.FullName 'bin\httpd.exe' } } | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -Last 1
if (!$apache) { throw 'Apache was not found in C:\wamp or C:\wamp64.' }
if ($Stop) {
    if (!(Test-Path -LiteralPath $pidPath)) { Write-Output 'ReLink is already stopped.'; exit }
    & $apache -k shutdown -f $configPath
    if ($LASTEXITCODE -ne 0) { throw 'Unable to stop ReLink Apache.' }
    exit
}
$existingPid = $null
if (Test-Path -LiteralPath $pidPath) { $existingPid = Get-Process -Id ([int](Get-Content -LiteralPath $pidPath -Raw).Trim()) -ErrorAction SilentlyContinue }
if (!$existingPid) {
    $portCheck = New-Object Net.Sockets.TcpClient
    try { $portCheck.Connect('127.0.0.1', 8085); throw 'Port 8085 is already in use. Stop the other server first.' } catch [Net.Sockets.SocketException] { } finally { $portCheck.Dispose() }
    New-Item -ItemType Directory -Path (Join-Path $runtimeRoot 'sessions') -Force | Out-Null
    Set-Content -LiteralPath (Join-Path $runtimeRoot '.htaccess') -Value 'Require all denied' -Encoding ASCII
    $template = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'apache-local.conf') -Raw
    $template = $template.Replace('@PROJECT_ROOT@', $projectRoot.Replace('\','/'))
    [IO.File]::WriteAllText($configPath, $template, [Text.UTF8Encoding]::new($false))
    & $apache -t -f $configPath
    if ($LASTEXITCODE -ne 0) { throw 'Apache configuration validation failed.' }
    Start-Process -FilePath $apache -ArgumentList ('-f "' + $configPath + '"') -WindowStyle Hidden | Out-Null
}
$ready = $false
for ($attempt = 0; $attempt -lt 25; $attempt++) {
    try { $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8085/pages/login.php' -UseBasicParsing -TimeoutSec 2; if ($response.StatusCode -eq 200) { $ready = $true; break } } catch { Start-Sleep -Milliseconds 200 }
}
if (!$ready) { throw 'ReLink did not start. Check .relink-runtime/apache-error.log.' }
Write-Output 'ReLink main system: http://localhost:8085/pages/login.php'
if (!$NoBrowser) { Start-Process -FilePath 'http://localhost:8085/pages/login.php' -WindowStyle Hidden }
