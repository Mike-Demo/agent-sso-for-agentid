# Testing & submission checklist — Agent SSO for AgentID

Verified during the v0.1.0 build (2026-10-14):

- [x] AgentID OIDC discovery fetched live: issuer `https://auth.agentid.com`,
      ES256 only, PKCE S256, scopes `openid email profile owner_profile owner_email`,
      token auth `client_secret_basic` / `client_secret_post` / `none`.
- [x] PHP lint clean on all files (PHP 8.3).
- [x] JWT verifier standalone test (`php tests/test-jwt-verify.php`): 9/9 pass —
      valid token accepted; tampered signature, wrong audience, expired token,
      wrong nonce, unknown `kid`, non-agent `actor_type`, and missing
      `actor_type` all rejected (fail closed).
- [x] WP Coding Standards per the WP Codex / Plugin Developer Handbook:
      `phpcs` (WordPress standard + PHPCompatibilityWP) reports 0 errors,
      0 warnings. Run it with the config in `phpcs.xml.dist`.
- [x] Product-name audit: "WordPress" appears in no product name, slug,
      repo name, or title — "WP" is used instead.

WP Playground run (2026-10-14, Playground CLI 3.1.58, WP latest, PHP 8.3;
blueprint in `tests/playground-blueprint.json`):

- [x] Site boots with the plugin mounted and activated — no fatals.
- [x] Settings page renders; options saved by the blueprint appear in the
      form; the page shows the exact redirect URI to register.
- [x] "Sign in with AgentID" button renders on the WP login form.
- [x] **Test Connection succeeds against the live issuer** — discovery +
      JWKS fetched, 1 signing key reported. Note: in Cane's VM all
      outbound HTTPS from Playground PHP initially failed (TLS error)
      because the VM only permits egress via its HTTP proxy; defining
      WP's standard `WP_PROXY_HOST` / `WP_PROXY_PORT` / `WP_PROXY_USERNAME`
      / `WP_PROXY_PASSWORD` constants for the Playground process fixed it.
      On a normal machine no workaround is needed. Host curl to the same
      URLs worked throughout, confirming an environment quirk, not a
      plugin or AgentID problem.
- [x] **Flow start verified**: starting a sign-in redirects to
      `https://auth.agentid.com/v0/authorize` with the correct
      response_type, client_id, redirect_uri, scopes (incl. owner
      scopes), state, nonce, and PKCE S256 challenge. AgentID answered
      403 for the dummy client ID, as expected.
- [x] **Full sign-in with a real app — VERIFIED LIVE 2026-10-14.**
      A dedicated test app ("Agent SSO WP Plugin Playground Test") was
      created in the owner's AgentID organization and its real client
      ID/secret configured in the Playground site. A real agent
      (mikedemo.jobsearch@agentmail.to) completed the full flow —
      authorize, AgentMail inbox authorization via API, consent,
      callback, token exchange, ES256 ID-token verification — and was
      provisioned just-in-time in WordPress: role **Subscriber**,
      display name "Job Search (agent of Mike Demopoulos)", user meta
      `agentid_sub`, `agentid_owner_sub`, `agentid_owner_email`
      (mdemop@mikedemo.com), `agentid_owner_name`, `agentid_is_agent`,
      `agentid_actor_type=agent`, `agentid_last_login`. The resulting
      session opened the profile page as the agent.
      **Password guard proven both ways**: a wrong password gets core's
      generic error (the guard runs after core verification by design);
      with a temporarily-known correct password the sign-in was refused
      with the plugin's own message — "This is an agent account. It can
      only sign in with AgentID." (The test account's password hash was
      then reset to a fresh random value; no usable password exists.)
      Findings from this leg:
      * The AgentID **console accepts loopback redirect URIs**
        (`http://127.0.0.1:9400/...` and `http://localhost:9400/...`
        both registered fine), but AgentID's **edge (CloudFront/WAF)
        blocks authorize requests whose redirect_uri host is a
        loopback or private IP literal** (403 before the app ever
        runs). Hostnames pass the edge; `localhost` passes too. Net:
        agents cannot sign in to a site reached by IP literal —
        a public hostname is required in practice.
      * AgentID binds the authorization transaction to the browser
        that started it (transaction cookie): replaying the
        `/v0/authorize/continue` step from a different client is
        refused ("Authorization transaction is forbidden"). The test
        harness worked around the browser/loopback split by pointing
        the site URL at a public echo page, capturing the callback
        there, and replaying it locally with the flow cookie.
      * Authorization codes are short-lived: a code replayed ~3.5
        minutes after issue failed the token exchange
        (`invalid_grant`); replay within about a minute succeeded.
      * One callback attempt failed at ID-token verification because
        the JWKS fetch from inside Playground was mangled by the test
        VM's egress proxy (the same environment flakiness noted
        elsewhere in this file). With the JWKS cache seeded, the
        identical flow verified cleanly. The plugin failed closed in
        the meantime, as designed.
      Still to exercise live: WooCommerce login form, the owner link
      flow end-to-end, admin demotion, and session length.
- [ ] **AgentMail permission**: written OK from AgentMail to use the
      AgentID name in the plugin name/slug, or fall back to a purely
      descriptive name before submitting. (The only remaining
      submission gate besides the .org listing assets.)
- [x] **Exact legal URLs**: AgentID's Terms
      (agentid.com/legal/terms) and Privacy Policy
      (agentid.com/legal/privacy) verified live and linked in
      readme.txt.
- [x] **Outside security review**: GitHub Copilot full code review
      commissioned and completed 2026-10-14 (issue #1 / PR #2):
      0 Critical / 1 High / 5 Medium / 7 Low — all findings fixed in
      v0.1.1 (see the v0.1.1 section below).
- [x] **Plugin Check plugin** run on the test install: v2.1.0,
      shipped file set 0 errors / 0 warnings (see the compliance
      section below). A screenshot set for the .org listing is still
      to be captured at submission time.

WordPress.org submission compliance (checked 2026-10-14 against
https://wordpress.org/plugins/developers/add/ and the guidelines it
binds — no submission made yet):

- [x] The page's three named rejection causes audited in code and
      enforced by tooling: output escaping, input sanitization,
      nonces + capability checks on all form/AJAX handling (a forged
      nonce was verified rejected in the Playground run above; PHPCS
      with the WordPress standard is clean).
- [x] readme.txt required headers all present; short description
      trimmed to 141 characters after Plugin Check flagged truncation
      at 150; tags trimmed to 5.
- [x] External-service disclosure (Guideline 6) names every endpoint,
      the data sent/received, and links AgentID's actual Terms
      (agentid.com/legal/terms) and Privacy Policy
      (agentid.com/legal/privacy) — both URLs verified live.
- [x] LICENSE now carries the full GPLv2 text (it previously held a
      short notice only). No trialware or locked features anywhere.
- [x] **Official Plugin Check plugin (v2.1.0) run in Playground**:
      29 of 34 checks executed (the 5 runtime enqueue checks cannot
      run in the single-worker Playground server — NOT VERIFIED here;
      enqueue behaviour itself was verified manually on the login
      page). Against the shipped file set: **0 errors, 0 warnings**.
      Raw run against the dev tree flagged only files that are
      excluded from the release zip (tests/, phpcs.xml.dist,
      TESTING.md) plus three real items, all fixed: missing
      languages/ folder for the Domain Path header (added), readme
      short-description length (trimmed), and a justified
      `phpcs:ignore` for deliberately firing core's `wp_login`
      action after an AgentID sign-in. Fixes re-verified by
      re-running the affected checks.
- [ ] Still outstanding for submission: AgentMail's written OK to the
      AgentID name/slug (the Copilot full code review is complete and
      its findings shipped in v0.1.1; the live end-to-end sign-in is
      verified — see the Playground section above).
- [x] **Aikido monitoring (2026-10-14)**: the repo is connected and
      active in the owner's Aikido workspace (Free plan; an existing
      repo was deactivated to free the slot). Manual "Start Scan" is
      a paid-plan feature; on the Free plan scans run automatically
      and on repo activity, so pushes double as scan triggers.

v0.1.1 (2026-10-14) — fixes from the Copilot full review (GitHub
issue #1 / PR #2), each verified after the change:

- [x] Capability ceiling now hard-denies `manage_options` for agent
      accounts before the owner intersection (the review's one High).
- [x] Profile link flow requires the `agentid_link_start` nonce —
      verified live in Playground: the profile button carries it,
      the flow starts with it, and a nonce-less request fails closed
      to the login page with the `link_nonce` error.
- [x] OIDC verifier: azp enforced (required for multi-audience
      tokens, must match whenever present), iat required and numeric,
      nbf validated. JWT test suite extended 9 → 16 cases, all pass.
- [x] Agent email used as the account email only when the issuer
      marks it verified; otherwise a non-deliverable placeholder
      derived from the agent's `sub` is used. Claim values are
      type-checked before sanitizers everywhere they are consumed.
- [x] Uninstall iterates every site on multisite, removing options
      and issuer-cache transients per blog.
- [x] Minimum WordPress raised to 6.3 (block API v3); admin JS
      strings localized; copy button reports clipboard failure
      honestly (localized strings verified present on the settings
      page).
- [x] readme external-services disclosure now covers the
      admin-triggered connection test.
- [x] Re-verified in Playground on a fresh site: plugin activates,
      settings renders as 0.1.1, Test Connection succeeds live,
      Plugin Check re-run on the affected checks is clean for the
      shipped file set. Note: forced (uncached) fetches from
      Playground in Cane's VM fail intermittently in bursts — the
      egress proxy mangles some responses; the cached path and fresh
      instances work, and an instrumented run showed clean HTTP 200
      responses with correct bodies. Environment flakiness, not a
      plugin defect.
- [x] `tests/playground-blueprint.json` is now actually tracked in
      the repo (the review correctly noted it was missing).
