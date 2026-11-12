param(
    [Parameter(Mandatory = $true)][string]$JMeterPath,
    [ValidateSet('Validate', 'Quick', 'Measure', 'Workflow')][string]$Mode = 'Validate',
    [string]$OutputDirectory = 'test-results/jmeter-mixed/local'
)

$ErrorActionPreference = 'Stop'
$src = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../..')).Path
$jmeter = (Resolve-Path -LiteralPath $JMeterPath).Path
$serverRoot = Join-Path $src 'test-results/jmeter-local'
New-Item -ItemType Directory -Path $serverRoot -Force | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$stdout = Join-Path $serverRoot "launcher-$stamp.out.log"
$stderr = Join-Path $serverRoot "launcher-$stamp.err.log"
$server = $null
$client = $null

Push-Location -LiteralPath $src
try {
    $server = Start-Process -FilePath 'node' -ArgumentList @('scripts/jmeter/serve-local.mjs') `
        -WorkingDirectory $src -PassThru -WindowStyle Hidden `
        -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    $deadline = [DateTime]::UtcNow.AddMinutes(5)
    $poolFile = $null
    $pool = $null
    $validated = $false
    $handler = [System.Net.Http.HttpClientHandler]::new()
    $handler.UseProxy = $false
    $client = [System.Net.Http.HttpClient]::new($handler)

    while ([DateTime]::UtcNow -lt $deadline -and -not $validated) {
        if ($server.HasExited) { throw "Local ATHENA setup exited. See $stderr." }
        if (Test-Path -LiteralPath $stdout) {
            $poolLine = Get-Content -LiteralPath $stdout | Where-Object { $_ -match '^Local pool: ' } | Select-Object -Last 1
            if ($poolLine -match '^Local pool: (.+)$') {
                $poolFile = (Resolve-Path -LiteralPath $Matches[1].Trim()).Path
                $pool = Get-Content -Raw -LiteralPath $poolFile | ConvertFrom-Json
            }
        }
        if ($poolFile) {
            try {
                $probeUrl = "$($pool.baseUrl)/__jmeter/session"
                $response = $client.GetAsync($probeUrl).GetAwaiter().GetResult()
                if ($response.StatusCode -eq [System.Net.HttpStatusCode]::OK) {
                    $response.Dispose()
                    foreach ($user in ($pool.users | Group-Object workspace | ForEach-Object { $_.Group[0] })) {
                        $sessionPath = Join-Path (Split-Path -Parent $poolFile) $user.sessionFile
                        $cookie = (Get-Content -Raw -LiteralPath $sessionPath | ConvertFrom-Json).cookies |
                            Where-Object { $_.name -match 'session' } | Select-Object -First 1
                        if (!$cookie) { throw "Local $($user.workspace) session file has no ATHENA cookie." }
                        $request = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::Get, $probeUrl)
                        $request.Headers.TryAddWithoutValidation('Cookie', "$($cookie.name)=$($cookie.value)") | Out-Null
                        $check = $client.SendAsync($request).GetAwaiter().GetResult()
                        $diagnostic = $check.Content.ReadAsStringAsync().GetAwaiter().GetResult() | ConvertFrom-Json
                        $httpOk = $check.StatusCode -eq [System.Net.HttpStatusCode]::OK
                        $request.Dispose()
                        $check.Dispose()
                        if (!$httpOk -or !$diagnostic.cookieParsed -or
                            !$diagnostic.keyMatchesCookie -or !$diagnostic.sessionFileFound -or !$diagnostic.storedAuthenticationFound -or
                            !$diagnostic.testingEnvironment) {
                            throw "Local $($user.workspace) session validation failed (http=$httpOk, header=$($diagnostic.cookieHeaderPresent), parsed=$($diagnostic.cookieParsed), key=$($diagnostic.keyMatchesCookie), file=$($diagnostic.sessionFileFound), auth=$($diagnostic.storedAuthenticationFound), testing=$($diagnostic.testingEnvironment))."
                        }
                        Write-Output "Verified local $($user.workspace) session."
                    }
                    $validated = $true
                    break
                }
                $response.Dispose()
            } catch [System.Net.Http.HttpRequestException] {
                Start-Sleep -Seconds 1
            } catch [System.Net.Sockets.SocketException] {
                Start-Sleep -Seconds 1
            }
        }
        Start-Sleep -Seconds 1
    }
    if (-not $validated) { throw "Local ATHENA did not become ready. See $stdout and $stderr." }

    $runMixed = Join-Path $PSScriptRoot 'Run-Mixed.ps1'
    if ($Mode -eq 'Quick') {
        & $runMixed -Pool $poolFile -JMeterPath $jmeter -OutputDirectory $OutputDirectory -Mode Quick
    } else {
        & $runMixed -Pool $poolFile -JMeterPath $jmeter -OutputDirectory $OutputDirectory -Mode Validate
    }
    if ($Mode -eq 'Measure') {
        Write-Output 'Six-role validation passed; starting the 20, 50, and 100 user measurements.'
        & $runMixed -Pool $poolFile -JMeterPath $jmeter -OutputDirectory $OutputDirectory -Mode Measure
    } elseif ($Mode -eq 'Workflow') {
        & $runMixed -Pool $poolFile -JMeterPath $jmeter -OutputDirectory $OutputDirectory -Mode Workflow
        $runtime = Get-Content -Raw -LiteralPath (Join-Path (Split-Path -Parent $poolFile) 'runtime-env.local.json') | ConvertFrom-Json
        foreach ($entry in $runtime.PSObject.Properties) { Set-Item -Path "Env:$($entry.Name)" -Value ([string]$entry.Value) }
        & php scripts/jmeter/seed-local.php verify
        if ($LASTEXITCODE -ne 0) { throw 'The local proposal or monitoring handoff did not persist as expected.' }
    }
} finally {
    if ($client) { $client.Dispose() }
    if ($server -and -not $server.HasExited) {
        & taskkill.exe /PID $server.Id /T /F 2>$null | Out-Null
    }
    if ($pool) {
        $appPort = [uri]::new([string]$pool.baseUrl).Port
        $owners = Get-NetTCPConnection -LocalPort $appPort -State Listen -ErrorAction SilentlyContinue |
            Select-Object -ExpandProperty OwningProcess -Unique
        foreach ($owner in $owners) {
            $listener = Get-Process -Id $owner -ErrorAction SilentlyContinue
            if ($listener.ProcessName -eq 'nginx' -and $listener.StartTime -ge $server.StartTime) {
                & taskkill.exe /PID $owner /T /F 2>$null | Out-Null
            }
        }
    }
    Pop-Location
    Write-Output "Local server logs: $stdout and $stderr"
}
