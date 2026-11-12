# ATHENA mixed-role testing

Run commands from `src/`. Existing results are preserved; each run writes a new results folder.

## Approved deployed shared-account load

The deployed test now uses Head, Secretary and Coordinator: **20, 50 and 100 virtual users sharing three signed-in accounts and three server sessions**. This is an explicitly selected load model, separate from the independent-account modes below. Faculty is excluded because its saved session is also Head's session.

```powershell
./scripts/jmeter/Run-Mixed.ps1 -Pool 'scripts/jmeter/shared-deployed.local.json' -JMeterPath 'C:/Users/Neil/Downloads/apache-jmeter-5.6.3/bin/jmeter.bat' -Mode SharedQuick -OutputDirectory 'test-results/jmeter-mixed/deployed-shared'
```

This first runs one virtual user per role to verify all 11 selected page operations, then one timed run per load. Each timed run uses 10 seconds of ramp-up and 60 seconds of measurement, with a 1.5-second pause before each request. Warm-up/startup allowance is 15 seconds at 20/50 users and 75 seconds at 100 users; the higher allowance was needed after auditing an initial run whose startup consumed most of the schedule. Counts per role (Head/Secretary/Coordinator) are 7/7/6, 17/17/16 and 34/33/33. Cookies are intentionally shared within a role; each thread keeps its own JMeter cookie jar. No workspace switching or record mutation is included.

Completed results and the retained attempt audit are in `test-results/jmeter-mixed/deployed-shared/RESULTS.md`, with `overview.csv` and `overview.json` indexing the three selected measurements.

Coverage and summaries include `loadModel:shared-account`, the virtual user count, three accounts and three shared sessions. Session contention, shared caches and account-based limits can affect the result. It is not evidence for 20–100 independent signed-in people, rich project data or complete submission/approval flows. Setup, workspace identity, HTTP failures and role coverage are checked. Raw JTL and per-run summaries are retained in new folders.

For the current deployed round, see [DEPLOYED-SCOPE.md](DEPLOYED-SCOPE.md): VCRDES and Faculty Researcher are excluded. The six-role Quick command below remains the original workload and requires all six roles; the completed deployed page checks are recorded separately.

`-Mode ReducedQuick` is the four-role version. It first validates one independent account per workspace, then requires enough independently authenticated accounts for 20, 50 and 100 users. The current three-account inventory cannot satisfy it; copying saved cookies or switching the same account between workspaces does not fill the missing accounts. See the scope document for the live readiness audit and successful three-account concurrent preflight.

## Short run for the major features

Use `-Mode Quick` for one six-user validation followed by one run each at 20, 50, and 100 concurrent users. It covers 17 operations across Faculty, Faculty Researcher, Research Head, Research Coordinator, Research Secretary, and VCRDES:

- Research calls and proposal drafts/details/submissions lists.
- Researcher project lists, project details, and document downloads.
- Head project oversight, report reviews, and budgets.
- Coordinator proposal review.
- Secretary reports, project details, and monitoring documents.
- VCRDES dashboard.

Each non-VCRDES user needs at least two accessible projects/topics. Quick mode uses the same independent-account/session checks as the full mode. It tests page and document responses; it does not submit or approve records.

Each measured run has 10 seconds of ramp-up within a 15-second warm-up, then 60 seconds of measurement. The three timed runs total **3 minutes 45 seconds**, plus the initial smoke check, setup, report generation, and any requests still finishing. Coverage or request failures stop Quick mode so failed authentication is not counted as a valid test.

Local disposable copy:

```powershell
./scripts/jmeter/Run-LocalMixed.ps1 -JMeterPath 'C:/Users/Neil/Downloads/apache-jmeter-5.6.3/bin/jmeter.bat' -Mode Quick -OutputDirectory 'test-results/jmeter-mixed/local-quick'
```

Deployed website, after filling the test account pool and accessible record IDs:

```powershell
./scripts/jmeter/Run-Mixed.ps1 -Pool 'scripts/jmeter/mixed-deployed.local.json' -JMeterPath 'C:/Users/Neil/Downloads/apache-jmeter-5.6.3/bin/jmeter.bat' -Mode Quick -OutputDirectory 'test-results/jmeter-mixed/deployed-quick'
```

`-Mode Measure` retains the full workload and three repetitions per user level. `-Mode Workflow` runs the separate disposable local draft creation and monitoring handoff checks. Previously completed results do not need to be repeated to use Quick mode.

## Reuse the saved login

Use the same `--out` file for the same test account:

```powershell
node scripts/jmeter/capture-session.mjs --workspace research_head --out scripts/jmeter/private/research_head-01.json
```

The capture tool loads that saved ATHENA session, checks the requested workspace, and refreshes the saved cookies. It asks for sign-in only if there is no saved session or ATHENA redirects it back to sign-in. A server error stops the check without overwriting the saved session or asking for login again.

JMeter runs use the captured sessions directly; capture is not needed before every run. Distinct test accounts keep separate session files. The saved files contain private ATHENA cookies and must stay local. They do not retain Google cookies or passwords. Server expiry or logout can still require a new sign-in.
