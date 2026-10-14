# Dispositions — Copilot AUTO quality review of v0.1.2 (issue #5, draft PR #6)

Review report: `copilot-quality-review-auto-2026-10-14.md` (on the PR #6 branch).
Result: 0 Critical, 0 High, 7 Medium, 6 Low. All Mediums and the actionable Lows
were fixed in v0.1.3 (2026-10-14). Verification evidence is in TESTING.md.

| ID | Finding | Disposition |
| --- | --- | --- |
| C1 | Flow-cookie path (COOKIEPATH) can exclude the callback on split home/site-path installs | **Fixed** — cookie scoped to `/` via `AgentID_Auth::flow_cookie_path()`; live-verified `path=/` on the callback response. |
| C2 | Usernames over the 60-char `user_login` cap fail provisioning | **Fixed** — base bounded so base + collision suffix ≤ 60; probe-verified at exactly 60 in both cases. |
| C3 | HTTP Basic credentials not form-encoded (RFC 6749 §2.3.1) | **Fixed** — each credential `rawurlencode()`d before joining (WPCS-preferred encoder; form parsers decode `%20` and `+` identically). |
| C4 | Subscriber fallback bypasses the shared role policy | **Fixed** — `AgentID_Settings::fallback_role()` validates against the provisionable set everywhere (settings sanitize, `safe_default_role()`, demotion); provisioning returns a controlled `no_role` error when no safe role exists. Probe-verified with a poisoned Subscriber role. |
| C5 | WooCommerce login drops the return destination | **Fixed** — button passes checkout URL when embedded at checkout, My Account otherwise. Code-verified; WooCommerce is not installed in the test rig. |
| C6 | Uninstall/reinstall strands retained agent accounts | **Fixed for placeholder accounts** (subject-derived address reconnects automatically — the only case that is safe to automate); **documented for verified-email accounts** (admin reconnect path in the readme FAQ). Deliberately NOT solved by signing agents in by email. Probe-verified both ways. |
| S1 | Settings sanitization changes opaque credential bytes (double unslash + `sanitize_text_field`) | **Fixed** — no second unslash; credentials accepted as strings with an explicit-charlist trim only. Probe-verified byte-identical round-trip. |
| Q1 | Connection test fetches discovery twice | **Fixed** — `AgentID_OIDC::jwks()` accepts a known discovery document; the AJAX handler passes the one it just validated. |
| Q2 | Unreachable `no_email` error message | **Fixed** — removed. |
| V1 | README disclaimer retains the former product name | **Fixed** — reworded to "renamed in v0.1.2". |
| T1 | JWT suite does not cover the v0.1.2 integration fixes | **Acknowledged, partially addressed** — v0.1.3's fixes were verified with ad-hoc Playground probes (recorded in TESTING.md); converting them into committed automated integration tests remains future work. |
| T2 | Owner-ceiling wording omits the unresolved-owner policy | **Fixed** — README and readme.txt now state the ceiling applies when an owner is recorded, and that administration capabilities are denied regardless. |
| T3 | Privacy description overstates which email is stored | **Fixed** — readme privacy section documents placeholder addresses and that the account email is set at provisioning. |

The parked draft PR #6 is left as-is (report only, per its brief); this file is
the record of what was done about it.
