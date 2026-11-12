# Reduced deployed test scope

Scope agreed on 8 November 2026: prioritize Faculty, Research Head, Research Secretary, and Research Coordinator. Exclude VCRDES and Faculty Researcher from this deployed round because of current test priorities and limited eligible data.

Subsequent user decision: run **20/50/100 virtual users sharing the three available accounts**, labelled as shared-account load across Head, Secretary and Coordinator. Faculty remains covered only by its earlier single-user check. The independent four-role plan below is retained as context; it is not the load model of these new runs. The shared-account runner is `Run-Mixed.ps1 -Mode SharedQuick`; its results go to `test-results/jmeter-mixed/deployed-shared`.

The approved shared-account sequence is complete: 520, 765 and 799 measured requests at 20, 50 and 100 virtual users, respectively; zero failures in the selected runs, all 11 operation types present, and every virtual user covered its assigned operations. The selected 100-user repeat used a 75-second startup/warm-up allowance before its 60-second measurement. The earlier short-duration and failed attempts are retained and excluded from the selected overview. See `test-results/jmeter-mixed/deployed-shared/RESULTS.md` for timing details and limitations.

VCRDES monitoring is a valid performance workload, but is outside this round's scope. Faculty Researcher implementation, document and monitoring journeys are separate from Faculty proposal work; they are untested on the deployed site, not covered by the Faculty result.

## Evidence available

| Workspace | Available evidence | Coverage limits |
| --- | --- | --- |
| Research Head | Earlier completed Research Head runs | Retain their original workload and user-count labels. |
| Faculty | One user, 60-second measurement; dashboard, research calls/details, proposal list/draft detail, submissions list | One accessible draft; no submitted-proposal detail or submission transition tested. |
| Research Secretary | One user, 60-second measurement; dashboard, report list, project detail, budget list | One accessible project; monitoring document download and review handoff untested on deployed site. |
| Research Coordinator | One user, 60-second measurement; dashboard, proposal list, report workspace | No accessible proposal-detail link discovered; proposal review actions and NTP creation untested. |

These separate deployed runs are single-user page-response checks. Combining their summaries does not create a concurrent mixed-role benchmark. The deployed 20/50/100-user mixed-role runs remain pending and require a plan matching the available authenticated sessions and records.

Limited data means multi-project access, submission/approval transitions, uploads, and production-scale data volume cannot be claimed as covered. No records were created merely to fill these gaps.

The completed local mixed-role runs and local disposable workflow retain their original scope; no repeat is required for this exclusion decision. Local and deployed workload scopes now differ, so their aggregate numbers should not be presented as a matched comparison.

## Reduced runner and readiness

`Run-Mixed.ps1 -Mode ReducedQuick` prepares four-role validation followed by 20, 50 and 100 users, once per level. Measurement is 60 seconds after a 15-second warm-up. Allocation is equal across the four roles (at 50 users: 13 Faculty, 13 Head, 12 Secretary, 12 Coordinator).

The reduced profile uses the accessible read operations and permits one record set per account. It retains checks for distinct accounts, distinct session cookies, actor identity and active workspace. Topic coverage is required only for Head and Secretary project detail operations; it does not claim multiple projects or submitted Faculty proposal coverage.

The live session audit found three distinct authenticated accounts. The Faculty and Head files both opened Head as account 4 and returned the same CSRF-token fingerprint, despite their different encrypted cookie strings. They cannot be counted as independent Faculty and Head users. Current pool preparation therefore rejects the repeated account before any four-role load starts.

A brief three-account concurrent preflight passed on Head, Secretary and Coordinator: 11 operation labels, no failed requests. This is a preflight, not a 20/50/100-user result. The requested independent-user load levels remain blocked by account/session availability.

The prepared `reduced-deployed.local.json` is an ignored local inventory, not a runnable pool until independent accounts and sessions are supplied. `-Mode Quick` still means the original six-role workload.
