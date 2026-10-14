# Agentic Agent SSO v0.1.2 — Copilot quality review (Auto)

## Scope, result, and validation

Reviewed `main` snapshot `70c4030b738f1b54c32b04170e46b322799006f4` on 2026-10-14. The PR's initial-plan commit has no source differences from that snapshot. Scope includes the PHP bootstrap, OIDC/authentication, provisioning, guards, settings, button surfaces, block assets, uninstall, tests, and documentation.

**Report only:** no plugin, test, configuration, or existing documentation files changed. This is not a repeat of the earlier security audit or WordPress.org submission scan. No Critical issue was incidentally identified; this is not a security certification.

**Findings: 0 Critical, 0 High, 7 Medium, 6 Low.** Medium findings concern broken supported/configurable paths, not a demonstrated authentication bypass. Source-derived scenarios below are distinguished from executed checks.

Executed from `/home/runner/work/agentic-agent-sso/agentic-agent-sso`:

- `php tests/test-jwt-verify.php`: **16/16 checks pass**, exit 0, PHP 8.3.6.
- `php -l` on all 10 tracked PHP files: **all pass**.
- `node --check assets/js/admin.js` and `node --check blocks/login-button/index.js`: **both pass**.
- Source inspection of translation calls, block dependencies/stylesheet, rename remnants, packaging rules, and the reviewed baseline.
- Isolated, stubbed role checks confirmed stock Author/Contributor/Subscriber selection and administrator demotion; a customized Subscriber granting `manage_options` reproduced finding C4. These are not WordPress integration tests.

**Not executed:** PHPCS/WPCS/PHPCompatibilityWP (`phpcs` unavailable), PHP 7.4 runtime testing (only PHP 8.3 installed), WordPress/Playground, a real-provider sign-in, WooCommerce checkout, editor insertion, translation loading, or release-archive construction. Prior live results in TESTING.md are historical evidence, not rerun results. No new test infrastructure was added for this report-only task.

Recent completed GitHub Actions runs were successful; querying failed-job logs for run `38636814130` returned no failed jobs. There is no identified failing CI job to diagnose here. PR #6 was verified to be draft with base `main`.

## 1. Correctness

### C1 — Medium: Flow-cookie path can exclude the callback

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:170-180,213-215,220-229`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/agentic-agent-sso.php:78-80`.

The flow cookie uses `COOKIEPATH`, but the callback is under `admin_url()`. WordPress derives `COOKIEPATH` from the home URL, not necessarily the WordPress installation URL. For a same-host installation with home `/blog/` and site URL `/wordpress/`, the cookie is scoped to `/blog/` and is not sent to `/wordpress/wp-admin/admin-post.php`. An otherwise valid callback fails `state` every time. The ordinary root-home/subdirectory-core arrangement does not have this problem.

**Recommendation:** scope both cookie creation and deletion to a path covering the actual callback, such as the appropriate admin cookie path. Add a split-home/site-path integration test. WordPress's definitions were cross-checked in [6.3 default-constants.php](https://github.com/WordPress/WordPress/blob/6.3/wp-includes/default-constants.php).

### C2 — Medium: Long usernames prevent otherwise valid provisioning

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:430-444,455-465`.

The sanitized `preferred_username` and email-derived base are not length-limited before `wp_insert_user()`. WordPress limits `user_login` to 60 characters. A 61-character preferred username fails; even a valid 60-character base fails if it already exists and the loop appends `-2`. The useful underlying error is replaced with a generic provisioning failure.

**Recommendation:** bound the generated login to 60 characters, reserving space for each collision suffix, and test long claims and collisions at the boundary. Keep immutable-subject matching unchanged.

### C3 — Medium: HTTP Basic credentials are not OAuth form-encoded

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-oidc.php:335-348`.

`client_secret_basic` encodes the raw `client_id . ':' . secret`. OAuth 2.0 section 2.3.1 requires each credential to be application/x-www-form-urlencoded before joining with the colon and Base64-encoding. A client ID containing a colon becomes ambiguous; a literal plus in either credential can be decoded as a space by a conforming server. Ordinary alphanumeric credentials work, so the recorded successful live sign-in does not cover this edge case.

**Recommendation:** form-encode client ID and secret separately before constructing the Basic value. Add token-request assertions for colons, plus signs, percent signs, and spaces; do not log credentials.

### C4 — Medium: The Subscriber fallback bypasses the shared role policy

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:364-370,455-462`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-settings.php:121-125`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-guards.php:237-245`.

When the configured role is not provisionable, both role sanitization and `safe_default_role()` fall back to `subscriber` without validating it. If a role editor grants Subscriber a hard-denied capability, provisioning assigns a role excluded from `provisionable_roles()` and sign-in demotion assigns that same rejected role again. If Subscriber has been removed, the fallback is not an existing usable role.

The runtime hard-deny filter still removes the listed capabilities; this finding does **not** establish that those capabilities become usable. It does invalidate the broader claim that a role granting any denied capability can neither be assigned nor kept.

**Recommendation:** choose a fallback only from the current provisionable-role set. If none exists, return a controlled error instead of assigning a rejected/missing role. Reuse that decision in settings, provisioning, and demotion.

### C5 — Medium: WooCommerce login drops the return destination

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-button.php:80-85`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:146-147,164,292-293`.

The WooCommerce form button calls `html()` without a redirect. The flow consequently selects the site homepage rather than the My Account page or the checkout where login began. Authentication can succeed, but the customer journey is interrupted. The shortcode and WP login form have redirect plumbing that this integration does not use.

**Recommendation:** pass a validated local WooCommerce return destination, preserving checkout context where applicable and otherwise using My Account. Exercise both surfaces with a real WooCommerce installation; HPOS compatibility declarations alone do not verify this behavior.

### C6 — Medium: Uninstall/reinstall strands retained agent accounts

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/uninstall.php:5-8,42-55`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:273-277,354-355,417-427`.

Uninstall intentionally retains WordPress users and their emails but deletes their immutable subject mappings. After reinstall, the same agent is not found by subject; provisioning then rejects the retained account's email as `email_taken`. This also occurs without a verified email because the placeholder address is deterministic for the subject. The claimed clean reinstall is therefore not a straightforward recovery path.

**Recommendation:** define a consistent retain/delete identity policy and provide an explicit administrator-approved reassociation/recovery path for retained accounts. Do not solve this by automatically signing in existing users by email. Test provision → uninstall → reinstall → same-subject sign-in.

**Other reviewed paths:** explicit-link callbacks preserve the human's session rather than signing that person in as the agent; normal callbacks check disabled status before issuing authentication cookies and fire `wp_login`. Password/reset/application-password guards and the 1–72-hour session clamp are present. Shortcode output escapes labels and URLs and validates redirects. The dynamic block declares its stylesheet in metadata; absence of a stylesheet enqueue in its PHP render callback is not itself a defect.

## 2. Code quality and maintainability

The five component classes provide understandable boundaries, and centralizing the hard-denied capability list is a useful improvement. No broad restructuring is recommended. The following small issues are actionable:

### Q1 — Low: Connection testing fetches discovery twice

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-settings.php:198-204`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-oidc.php:76-86`.

The AJAX handler calls `discovery(true)` and then `jwks(true)`, which calls `discovery(true)` again. This makes three network requests instead of discovery plus JWKS, adds up to another 10-second timeout, and can fail a test on the second discovery request after the first succeeded.

**Recommendation:** allow the forced JWKS refresh to reuse the just-validated discovery document without accidentally reusing stale signing keys.

### Q2 — Low: The `no_email` error is unreachable and contradicts provisioning

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:84-99,408-427`.

The error map says an email is required to create an account, but provisioning now deliberately creates a placeholder when email is absent/unverified. No current path emits `no_email`.

**Recommendation:** remove the obsolete error entry or replace it with a message used by a real failure path. Keep the placeholder policy documented rather than maintaining contradictory dead guidance.

## 3. Standards: WPCS, PHPCompatibilityWP, escaping, and i18n

### S1 — Medium: Settings API sanitization changes opaque credential bytes

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-settings.php:46-54,100-109,256-257`.

The form posts through the Settings API. WordPress `options.php` already calls `wp_unslash()` before `update_option()` invokes the registered sanitizer. Calling it again here removes literal backslashes from client credentials. Separately, `sanitize_text_field()` strips percent-encoded sequences, so it is not a byte-preserving validator for an opaque client secret. Such credentials can save successfully but fail token exchange.

**Recommendation:** accept the Settings API's already-unslashed values, validate string types and explicitly reject unsupported input rather than silently altering credentials. Preserve supported credential bytes and continue escaping only when rendering. Add round-trip tests including backslashes and percent sequences. The core unslashing path was verified in [WordPress 6.3 options.php](https://github.com/WordPress/WordPress/blob/6.3/wp-admin/options.php).

**Static observations, not an automated standards certification:**

- `/home/runner/work/agentic-agent-sso/agentic-agent-sso/phpcs.xml.dist:12-18` selects minimum WP `6.3`, PHP `7.4-`, WordPress, and PHPCompatibilityWP. Tests are excluded at line 6, so a clean PHPCS run would not cover the standalone suite.
- Production PHP/JS translation calls inspected use `agentic-agent-sso`; the block metadata uses the same text domain. Role labels use `translate_user_role()`, and admin JS receives localized strings. No inconsistent production text domain was found.
- Inspected settings/profile/button output uses contextual `esc_html`, `esc_attr`, or `esc_url`; admin result text uses `textContent`. Settings use core nonce handling and the connection test checks both nonce and capability. These observations do not substitute for the earlier security scan.
- No obvious post-7.4 PHP syntax was found, and native PHP 8.3 lint passed. Actual PHP 7.4 execution and a fresh WPCS/PHPCompatibilityWP run remain unverified; historical “0 errors / 0 warnings” results are not claimed as newly reproduced.

## 4. Verification of the v0.1.2 changes

### V1 — Low: The current README retains the former product name

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/README.md:22-23`.

The README's disclaimer still spells out the former product name. It is clearly historical, not an active plugin identifier, but the issue's literal acceptance criterion allows historical exceptions in TESTING.md only.

**Recommendation:** replace the README wording with “renamed in v0.1.2” without the previous name. The legacy title in TESTING.md is within the explicit exception; the archived earlier review is historical evidence rather than a shipped product surface.

| Change | Assessment and evidence |
| --- | --- |
| Rename and retained internal prefixes | **Implemented, with V1 exception.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/agentic-agent-sso.php:3-13,22-32`, `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-settings.php:17,33-39`, and `/home/runner/work/agentic-agent-sso/agentic-agent-sso/blocks/login-button/block.json:1` use the new name/slug/domain. No old slug was found in current runtime sources, assets, blueprint, README, or readme. `agentid_` options/meta/classes/hooks remain intentionally unchanged. |
| Editor dependency manifest | **Correct by inspection.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/blocks/login-button/index.asset.php:15-18` lists all six packages used by `/home/runner/work/agentic-agent-sso/agentic-agent-sso/blocks/login-button/index.js:1-41`: blocks, element, block-editor, components, server-side-render, and i18n, version `0.1.2`. Metadata references that editor script; PHP registers the metadata directory. Syntax passed; actual editor loading/insertion was not rerun. |
| Shared capability policy | **Wired correctly, but not complete for customized fallback roles (C4).** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-guards.php:133-167,187-189,240-243` and `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-settings.php:69-81` share the hard-deny set. Stock Editor is excluded because of `unfiltered_html`; stock Author, Contributor, Subscriber remain eligible. |
| Explicit owner links before email matching | **Implemented.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:387-399` resolves `agentid_linked_sub` before consulting the email-matching toggle or requiring verified owner email. Metadata refresh invokes that resolver at lines 502–505. This fixes the stated resolution-order defect; see T2 for the separate unresolved-owner documentation limitation. |
| Login errors through `wp_login_errors` | **Implemented.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:39,114-120` adds recognized messages to core's `WP_Error` rather than printing pre-header HTML. The failure redirect carries the mapped error code at lines 67–75. No live login rendering was rerun. |
| Disclosures and upgrade notices | **Present and materially aligned with the flow.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/readme.txt:81-106` covers token exchange, Basic credentials, userinfo access token, admin connection testing, linked-subject storage, cookie/transient state, and both upgrade notices. Implementations are `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-oidc.php:335-355,374-384` and `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:159-180,217-230,321-323`. |
| Distribution exclusions | **Rules present, archive not verified.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/.distignore:1-8` excludes development paths including tests/docs/README/TESTING/PHPCS. `.distignore` requires a packaging tool that honors it; it does not itself alter GitHub source archives. No release workflow/archive was available to establish the final shipped contents. |
| PHPCS minimum WP | **Correctly changed to 6.3.** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/phpcs.xml.dist:12-13` matches the plugin/readme minimum and block API v3. Tool execution remains unverified here. |

## 5. Tests and documentation

### T1 — Low: The meaningful JWT suite does not cover the v0.1.2 integration fixes

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/tests/test-jwt-verify.php:27-51,53-78,133-239`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/tests/playground-blueprint.json:1`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/TESTING.md:180-189`.

The standalone suite loads the **current production verifier**, generates a fresh P-256 key, signs tokens, and checks both acceptance and specific rejection codes. Its 16 passing checks remain useful: signature, audience, expiry, nonce, actor type, unknown key, authorized party, issued-at/not-before validation, malformed audience, and PEM conversion are exercised. It is not a copied verifier or a live-provider-dependent test.

However, it neither boots WordPress nor covers callback state/cookie handling, JWKS refresh, account creation, owner resolution, role fallback/demotion, settings round trips, login error rendering, editor loading, or WooCommerce redirects. The blueprint activates/configures the plugin but asserts none of these behaviors. JWT branch gaps also include wrong issuer, missing subject, unsupported algorithm, malformed segments/key coordinates, future `iat`, and single-audience mismatched `azp`.

**Recommendation:** retain this suite and add focused verifier negatives plus reproducible WordPress integration coverage for the v0.1.2 claims and C1–C6/S1. Do not interpret “ALL TESTS PASSED” as full-stack regression coverage. The historical manual verification in TESTING.md is useful but not an automated regression gate.

### T2 — Low: Owner-ceiling wording omits the unresolved-owner policy

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/README.md:45-46`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/readme.txt:42-44,65-67`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-guards.php:190-205`.

Documentation unconditionally says an agent's capabilities are capped at its owner's. In code, an account with no recorded owner ID retains its role capabilities minus hard denies; only a recorded-but-deleted owner gets the read-only fallback. An unresolved agent configured as Author can therefore keep publishing capabilities even though no owner intersection occurred. This distinction was already relevant to the earlier review; it is noted here as a documentation contradiction, not a repeated security finding.

**Recommendation:** state exactly when the owner intersection applies and document the unmatched-owner behavior, or deliberately adopt and test a stricter unresolved-owner policy. Do not imply that v0.1.2's explicit-link ordering establishes an owner ceiling for every agent.

### T3 — Low: The privacy description overstates which email is stored

**Evidence:** `/home/runner/work/agentic-agent-sso/agentic-agent-sso/readme.txt:96`; `/home/runner/work/agentic-agent-sso/agentic-agent-sso/includes/class-agentid-auth.php:410-423,455-460,488-507`.

The privacy paragraph describes “agent email (as the account email)” for accounts the plugin creates or signs in. An absent, invalid, or unverified email produces a synthetic `@agentid.invalid` account address instead. On subsequent sign-ins, metadata refresh does not update `user_email`, so a placeholder remains even if a verified email later becomes available.

**Recommendation:** document the placeholder behavior and that the account email is selected at provisioning, not synchronized on every sign-in. If email refresh is intended, design it explicitly with collision/verification checks and regression coverage rather than suggesting it already occurs.

## Recommended follow-up order

1. Fix the validated-fallback-role and credential-handling paths (C4, S1, C3).
2. Fix sign-in availability/journey edge cases (C1, C2, C5) and define reinstall recovery (C6).
3. Add focused regression coverage, then verify on WP 6.3/PHP 7.4 and a current WP/WooCommerce/editor installation.
4. Reconcile the small documentation/dead-code issues and remove the duplicate discovery request.

All findings are intentionally left as recommendations in this report; no implementation fixes were made.
