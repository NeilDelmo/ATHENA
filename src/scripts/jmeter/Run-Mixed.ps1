param(
    [Parameter(Mandatory = $true)][string]$Pool,
    [Parameter(Mandatory = $true)][string]$JMeterPath,
    [Parameter(Mandatory = $true)][string]$OutputDirectory,
    [ValidateSet('Validate', 'Quick', 'ReducedQuick', 'SharedQuick', 'Measure', 'Workflow')][string]$Mode = 'Validate'
)

$ErrorActionPreference = 'Stop'
$prepare = if ($Mode -eq 'Workflow') { 'write-journey.mjs' } else { 'mixed.mjs' }
if ($Mode -eq 'SharedQuick') { $prepare = 'shared.mjs' }
$cases = switch ($Mode) {
    'Validate' { @(6) }
    'Quick' { @(6, 20, 50, 100) }
    'ReducedQuick' { @(4, 20, 50, 100) }
    'SharedQuick' { @(3, 20, 50, 100) }
    'Measure' { @(20, 20, 20, 50, 50, 50, 100, 100, 100) }
    'Workflow' { @('create', 'submit', 'forward', 'accept') }
}

foreach ($case in $cases) {
    $arguments = @((Join-Path $PSScriptRoot $prepare), $Pool, $case, $OutputDirectory)
    if ($Mode -eq 'Quick') { $arguments += 'major' }
    if ($Mode -eq 'ReducedQuick') { $arguments += 'reduced' }
    $run = & node @arguments
    if ($LASTEXITCODE -ne 0) { throw "Could not prepare $case." }
    Write-Output "Starting $case at $run"
    & (Join-Path $PSScriptRoot 'Run-JMeter.ps1') -RunDirectory $run -JMeterPath $JMeterPath
    & node (Join-Path $PSScriptRoot 'summarize.mjs') (Join-Path $run 'results.jtl')
    if ($LASTEXITCODE -ne 0) {
        if ($Mode -ne 'Measure') { throw "Validation failed at $run." }
        $summary = Get-Content -Raw -LiteralPath (Join-Path $run 'summary.json') | ConvertFrom-Json
        if ($summary.setupFailures -or $summary.warmupFailures -or !$summary.operations.Count) {
            throw "No valid full-load window at $run. Inspect setup and warmup before proceeding."
        }
        Write-Warning "Measured failures or incomplete coverage at $run. Results retained."
    }
}
