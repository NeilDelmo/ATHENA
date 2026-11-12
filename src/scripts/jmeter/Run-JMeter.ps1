param(
    [Parameter(Mandatory = $true)][string]$RunDirectory,
    [Parameter(Mandatory = $true)][string]$JMeterPath
)
$ErrorActionPreference = 'Stop'
$runFolder = (Resolve-Path -LiteralPath $RunDirectory).Path
$jmeterExecutable = (Resolve-Path -LiteralPath $JMeterPath).Path
$plan = Join-Path $runFolder 'athena.jmx'
$properties = Join-Path $runFolder 'run.properties'
$results = Join-Path $runFolder 'results.jtl'
$report = Join-Path $runFolder 'html-report'
if ((Test-Path -LiteralPath $results) -or (Test-Path -LiteralPath $report)) {
    throw 'This run already has results. Prepare a new run to preserve the earlier evidence.'
}
if (-not (Test-Path -LiteralPath $plan) -or -not (Test-Path -LiteralPath $properties)) {
    throw 'Prepare this run with athena.mjs first.'
}
& $jmeterExecutable '-n' '-t' $plan '-q' $properties '-l' $results '-j' (Join-Path $runFolder 'jmeter.log') '-e' '-o' $report
if ($LASTEXITCODE -ne 0) { throw "JMeter exited with code $LASTEXITCODE. Inspect the log and keep the failed run." }
Write-Output "Results: $results"
Write-Output "HTML report: $report"
Write-Output "Summarize from src: node scripts/jmeter/summarize.mjs `"$results`""
