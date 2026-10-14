# Code review — Agentic Agent SSO v0.1.3 (Wonder model, fresh pass)

**Date:** 2026-10-14 · **Reviewer:** Cane (Muse Wonder), at the maintainer's
request · **Scope:** every shipped file at v0.1.3 (main `5b1e16d`) — the main
plugin file, all five `includes/` classes, `uninstall.php`, `assets/js/admin.js`,
`blocks/login-button/` (`block.json`, `index.js`, `index.asset.php`), and the
test suite. This pass deliberately re-read the v0.1.3 fixes adversarially, not
just the older code.

**Verdict: 0 Critical / 0 High / 1 Medium / 5 Low.** The v0.1.3 fixes (C1–C6,
S1, Q1, Q2, V1, T2, T3) implement what their dispositions claim; the findings
below are new, and none of them is in code the earlier reviews had cleared as
a named finding.

## Medium

### M1 — The userinfo merge widens claim types past the ID-token guards

`AgentID_Auth::callback()` merges the userinfo response into the verified
ID-token claims with `array_merge()` once the subjects match. The ID-token
path is carefully type-guarded (v0.1.1 hardening: audience members, `azp`,
`sub`, `owner_email` are all checked `is_string`), but merged userinfo values
are not: provisioning casts `preferred_username` with `(string)`, which on a
non-scalar value raises an "Array to string conversion" warning and produces
the username `array`, and `refresh_agent_meta()` re-checks most fields but the
merged array as a whole is trusted more than the token path trusts the token.

Blast radius is contained — the userinfo endpoint is the pinned issuer's, over
HTTPS, queried with the flow's own access token, and the subject must match
the verified token exactly — so this is robustness in the identity path, not
a demonstrated vulnerability. It is still the one place the otherwise-uniform
"never trust an unguarded claim" rule has a gap.

*Fix:* merge only whitelisted fields (`email`, `email_verified`, `name`,
`preferred_username`, `owner_sub`, `owner_email`, `owner_email_verified`,
`owner_name`) and only when each value is a scalar of the expected type.

## Low

### L1 — A kid-less token is only tried against the first JWKS key

`AgentID_OIDC::verify_id_token()`: when the token header carries no `kid`,
the loop selects `keys[0]` and stops. During an issuer key rotation with two
or more published keys, a kid-less token signed by any other key fails
verification. Fail-closed (an availability nit, not a security hole), and
AgentID tokens observed live do carry `kid`. *Fix:* when `kid` is absent, try
each key until one verifies.

### L2 — A malformed discovery document can fatal instead of failing closed

`AgentID_OIDC::discovery()` runs
`in_array( 'ES256', $data['id_token_signing_alg_values_supported'], true )`.
If that field were a string rather than an array, PHP 8 raises a `TypeError`
and the request dies with a fatal error instead of the plugin's usual
`WP_Error`. The document comes from the pinned issuer over HTTPS, so this is
robustness only. *Fix:* `is_array()` guard before the `in_array()` call.

### L3 — `iat` future-skew bound disagrees with the leeway used elsewhere

`verify_id_token()` rejects an `nbf` more than `LEEWAY` (60 s) in the future
but allows an `iat` up to 300 s in the future. Harmless — expiry and
not-before are the operative checks — but the inconsistency invites a future
edit to assume one constant governs both. *Fix:* align on `LEEWAY` or comment
why `iat` differs.

### L4 — A stale recorded owner is never cleared

`refresh_agent_meta()` only ever *sets* `agentid_owner_user_id`. If the owner
link is later removed or the owner's email changes so the owner no longer
resolves, the old owner ID stays on the agent account and the capability
ceiling keeps intersecting with that person's capabilities. The direction is
fail-safe (intersection only removes capabilities, and a deleted owner drops
the account to read-only), so this is policy hygiene, not exposure. *Fix:*
delete the meta when a fresh sign-in resolves no owner and no explicit link
exists.

### L5 — Block editor script mixes injected dependencies with a global

`blocks/login-button/index.js` receives its dependencies as IIFE arguments
but reads `wp.components.PanelBody` / `wp.components.TextControl` off the
global. It works because `wp-components` is a declared dependency in
`index.asset.php`, so the global is guaranteed present — but the pattern is
one refactor away from a silent editor break. *Fix:* pass `components` into
the IIFE like the other packages.

## Informational / carried items (not new findings)

- **Settings role select:** when the stored `default_role` is not currently
  provisionable, the settings `<select>` renders with no stored value
  selected, so the first provisionable role displays. Saving is still guarded
  by `sanitize_options()`. Cosmetic only.
- **Uninstall leaves flow transients** (`agentid_sso_flow_*`): they are
  single-use, keyed by a hash of a random state, and expire within ten
  minutes; they cannot be enumerated for deletion. No action.
- **Flow-start writes a transient per unauthenticated hit** (scan #2
  candidate, still without a disposition): `start()` performs a cached
  discovery fetch and one transient write per hit on `agentid_login`. A rate
  limit remains a reasonable future hardening.
- **Guards are inactive while the plugin is deactivated** (scan #2
  candidate): inherent to plugin architecture; the readme's guidance stands.
- **Committed integration tests remain outstanding** (Copilot Auto review
  T1): the v0.1.2/v0.1.3 behaviors are covered by recorded Playground probes
  in TESTING.md, not by committed automated tests.

## What was verified clean in this pass

- **Single source of truth for the admin-capability deny set:**
  `hard_denied_caps()` drives `provisionable_roles()`, the settings
  sanitizer's fallback, demotion at sign-in, and the runtime ceiling —
  the four consumers cannot drift apart.
- **v0.1.3 C1:** the flow cookie is set and cleared with the same `/` path
  at both `setcookie()` sites in `start()`/`callback()`.
- **v0.1.3 C2:** the username base is bounded before the collision loop and
  re-bounded per suffix inside it; the loop cannot exceed 60 characters.
- **v0.1.3 C4:** every role-assignment path (sanitize, `safe_default_role`,
  demotion, reconnect) validates against `provisionable_roles()` or the
  validated fallback; provisioning refuses with `no_role` when none exists.
- **v0.1.3 C6:** reconnect is gated on the subject-derived placeholder
  address *and* an empty `agentid_sub`; verified-email collisions still fail
  closed with `email_taken`, and the reconnected account passes through the
  same disabled check and login actions as any other sign-in.
- **v0.1.3 S1:** no double-unslash remains in `sanitize_options()`; the
  credential fields accept strings verbatim apart from surrounding
  whitespace, and the "empty secret means keep" contract is intact.
- **Discovery pinning:** every endpoint in the discovery document must sit
  under the pinned issuer URL before any of it is used — stronger than a
  typical OIDC client.
- **Callback ordering:** the flow transient is deleted before the token
  exchange (single-use even if the exchange fails), the browser-binding
  cookie is compared with `hash_equals()`, and a `kid` miss triggers exactly
  one forced JWKS refetch.

## Disposition (2026-10-14, maintainer-approved)

- **M1 — FIXED in v0.1.4.** The userinfo merge in `callback()` is now a
  whitelist: `email`, `name`, `preferred_username`, `owner_sub`,
  `owner_email`, `owner_name` merge only as strings, and `email_verified` /
  `owner_email_verified` only as bool/string. Token-verified claims can no
  longer be overwritten by userinfo.
- **L2 — FIXED in v0.1.4.** `discovery()` guards the signing-algorithm list
  with `is_array()` before `in_array()`; a malformed document now returns
  the plugin's `WP_Error` instead of raising a `TypeError`.
- **L1, L3, L4, L5 — backlog.** Recorded here; no release is blocked on
  them (all fail closed or are cosmetic).
