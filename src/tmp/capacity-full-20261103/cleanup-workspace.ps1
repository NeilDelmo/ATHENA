$ErrorActionPreference = 'Stop'
$capacityRoot = (Resolve-Path -LiteralPath 'C:\laragon\www\athena-app\src\tmp\capacity-full-20261103').ProviderPath
if ($capacityRoot -cne 'C:\laragon\www\athena-app\src\tmp\capacity-full-20261103') { throw 'Unexpected benchmark directory.' }
$capacityReportPath = Join-Path $capacityRoot 'capacity-report.json'
$capacityReport = Get-Content -LiteralPath $capacityReportPath -Raw | ConvertFrom-Json
if (-not ($capacityReport.runs | Where-Object name -eq '4g-soak-20m').finished_at) { throw 'Sustained test has not completed.' }
if (-not (Test-Path -LiteralPath (Join-Path $capacityRoot 'measurement-evidence.zip'))) { throw 'Evidence bundle is missing.' }
foreach ($capacityName in @('artifacts', 'results', '__pycache__')) {
    $capacityTarget = Join-Path $capacityRoot $capacityName
    if (Test-Path -LiteralPath $capacityTarget) {
        $capacityResolved = (Resolve-Path -LiteralPath $capacityTarget).ProviderPath
        if ($capacityResolved -cne $capacityTarget -or -not $capacityResolved.StartsWith($capacityRoot + '\', [StringComparison]::Ordinal)) { throw 'Unsafe cleanup target.' }
        Remove-Item -LiteralPath $capacityResolved -Recurse -Force
    }
}
foreach ($capacityName in @('source.tar', 'vendor.tar', 'snapshot-ready')) {
    $capacityTarget = Join-Path $capacityRoot $capacityName
    if (Test-Path -LiteralPath $capacityTarget) { Remove-Item -LiteralPath $capacityTarget -Force }
}
Write-Output 'Temporary snapshots and large generated files removed; compact results retained.'
