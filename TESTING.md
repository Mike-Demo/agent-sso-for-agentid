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
- [ ] **Full sign-in with a real app** (the one remaining leg): register
      a real AgentID application with redirect URI
      `http://127.0.0.1:9400/wp-admin/admin-post.php?action=agentid_callback`
      (the Playground URL; whether AgentID accepts a loopback http
      redirect URI is itself part of the test), put its client ID/secret
      in the plugin settings, and complete a sign-in as a real agent.
      Also still to exercise live: WooCommerce login, owner link, and
      each guard (password sign-in refused, admin demotion, session
      length).
- [ ] **AgentMail permission**: written OK from AgentMail to use the
      AgentID name in the plugin name/slug, or fall back to a purely
      descriptive name before submitting.
- [ ] **Exact legal URLs**: confirm AgentID's current Terms and Privacy
      URLs and put them in readme.txt (it currently links agentid.com).
- [ ] **Outside security review** of the auth flow before submission —
      this is an identity plugin; a second pair of expert eyes is the
      responsible bar (see the 2026 miniOrange SAML bypass for why).
- [ ] **Plugin Check plugin** run on the test install (the official
      WP.org pre-submission checker) and a screenshot set for the
      .org listing.
