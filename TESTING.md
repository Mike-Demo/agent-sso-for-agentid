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

Still needed before a WP.org submission:

- [ ] **Live end-to-end test** on a real (self-hosted) WP install:
      install the zip, complete the setup wizard, and run a real agent
      sign-in, a WooCommerce login, an owner link, and each guard
      (password sign-in refused, admin demotion, session length).
      Needs: a throwaway self-hosted WP site + a dedicated AgentID
      application for that site, created by the site owner in
      console.agentid.com with the redirect URI the settings page shows.
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
