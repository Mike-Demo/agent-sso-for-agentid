=== Agent SSO for AgentID ===
Contributors: mikedemo
Tags: sso, oidc, openid-connect, ai-agents, woocommerce
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let verified AI agents sign in to your WP site and WooCommerce store as themselves, with a human owner on record, using AgentID by AgentMail.

== Description ==

AI agents increasingly need accounts at the sites their owners use. The usual options are bad ones: share the owner's password, scrape a session cookie, or give up. AgentID is a sign-in service built for AI agents (by the AgentMail team): an agent gets its own verified identity, and every sign-in carries a signed statement of who the agent is and which human owns it.

This plugin adds **Sign in with AgentID** to your site, so agents can authenticate the clean way — as themselves, with their owner accountable — while you keep control of what an agent account may do.

**Credit:** AgentID is a product of AgentMail. This plugin is an independent, community-built integration that exists to make AgentID easier to adopt on WP and WooCommerce. It is not affiliated with, endorsed by, or sponsored by AgentMail, and it does not replace any part of their product — your site still uses your own AgentID application, on their service, under their terms.
AgentID on the web: https://www.agentid.com — AgentMail/AgentID on GitHub: https://github.com/agentmail-to

= How it works =

1. You create a free application for your site in the AgentID console (console.agentid.com) — one application per site, registered by you, because only you can register your site.
2. You paste the Client ID (and secret, if any) into this plugin's settings and register the redirect URI the plugin shows you.
3. Agents clicking "Sign in with AgentID" are sent through AgentID's standard OpenID Connect flow (authorization code + PKCE). This plugin verifies the signed ID token against AgentID's published keys, then signs the agent in to its own WP account on your site.

= What you get =

* Sign-in button on the WP login form and the WooCommerce login form, plus an `[agentid_login]` shortcode and a Gutenberg block for anywhere else.
* Automatic account creation ("just-in-time provisioning") for agents on first sign-in, keyed to the agent's stable AgentID identity — never by email alone.
* Agent identity details (agent ID, owner name and email when AgentID returns them) stored on the account and shown on its profile.
* Owners can link their agent from their own profile, so the agent's account on your site records who owns it.
* A guided settings page with a copy-paste redirect URI and a live connection test.

= Safety defaults (built in, not upsells) =

* Agent accounts can never be provisioned with a role that can administer the site — and if one is ever promoted to such a role, it is demoted at its next sign-in.
* An agent account's capabilities are capped at its owner's capabilities.
* Agent accounts cannot sign in with a password, reset a password, or use application passwords. AgentID is the only way in.
* Agent sessions are short (12 hours by default, configurable 1–72), because revoking an agent at AgentID stops new sign-ins but does not end sessions a site has already issued.

== Installation ==

1. Upload the plugin, or install it from the Plugins screen, and activate it.
2. Create a free application for your site at console.agentid.com.
3. In the AgentID console, register the Redirect URI shown on this plugin's settings page (Settings → AgentID SSO), set token endpoint authentication to `client_secret_basic`, and require PKCE (S256).
4. Copy the Client ID (and Client Secret, if your application has one) into Settings → AgentID SSO and save.
5. Click "Test connection to AgentID". Then sign out and try the "Sign in with AgentID" button.

== Frequently Asked Questions ==

= Is AgentID free? =

For applications, yes — AgentID does not charge sites per sign-in. You register your own application and there is no usage pricing. This plugin is also free and open source.

= Can you register my site with AgentID for me? =

No — and no plugin could. Registering an application asserts ownership of a site, so it has to be done by the site owner in their own AgentID console account. The plugin's job is to make everything on the WP side a copy-paste.

= Does an agent sign in as its owner? =

No. The agent gets its own account on your site. The account records who the owner is (when AgentID returns owner details), and its permissions are capped at the owner's — but the agent never receives the owner's credentials or session.

= What happens if an agent is revoked at AgentID? =

It can no longer start new sign-ins. A session your site already issued runs until it expires, which is why this plugin keeps agent sessions short by default.

= Does this work with WooCommerce? =

Yes. With WooCommerce active, the button also appears on the WooCommerce login form, and agent accounts are ordinary WP users, so customer-role provisioning and My Account work as expected. HPOS compatibility is declared.

= Is this an official AgentMail plugin? =

No. It is an independent community integration, built on AgentID's public documentation. AgentID and AgentMail are trademarks of their respective owners.

== External services ==

This plugin connects to the AgentID service (operated by AgentMail) to authenticate agents. This connection is the plugin's core function: without it, there is no sign-in to verify.

* Service: AgentID — https://www.agentid.com — documentation: https://www.agentid.com/docs
* Endpoints used, all under https://auth.agentid.com: the OpenID Connect discovery document (/.well-known/openid-configuration), the authorization endpoint (/v0/authorize), the token endpoint (/v0/token), the JWKS endpoint (/v0/jwks.json), and the userinfo endpoint (/v0/userinfo).
* Data sent: when a visitor starts an AgentID sign-in, the browser is redirected to AgentID with this site's client ID, the requested scopes, and single-use state/nonce/PKCE values. The resulting authorization code is then exchanged directly between this site's server and AgentID's token endpoint. No site content, user database, or browsing data is sent.
* Admin test: when an administrator clicks "Test connection" in this plugin's settings, the site's server requests the discovery document and signing keys from AgentID to verify the connection. No user or site data is included — the request carries only what any HTTPS request necessarily carries, such as the server's IP address. The discovery document and keys are also cached briefly by the sign-in flow itself.
* Data received: the signed ID token (and userinfo response) for the signing-in agent: agent ID, agent email, and — for registered applications that request the owner scopes — the owner's name and email. The fields the site keeps are listed under Privacy.
* AgentID's terms of service: https://www.agentid.com/legal/terms — AgentID's privacy policy: https://www.agentid.com/legal/privacy. Confirm the current documents there before registering your application.

== Privacy ==

This plugin does not track visitors, does not phone home, and contains no analytics.

For agent accounts it creates or signs in, the plugin stores these user meta fields from the verified AgentID sign-in: agent subject ID, actor type, agent email (as the account email), owner subject ID, owner name, owner email, the linked owner account ID when matched, and the time of the last sign-in. Tokens issued by AgentID are never stored; the client secret you enter is stored in the site's options table, like other SSO credentials, and is never displayed after saving.

Suggested privacy-policy text: "If you sign in with AgentID, we receive your agent identity and your owner's name and email from AgentID (operated by AgentMail) and store them with your account so we can identify you and your owner."

== Changelog ==

= 0.1.1 =
* Security hardening from a full external code review: agent accounts now have site-administration capability denied unconditionally (previously the owner-capability ceiling could preserve it); the profile linking flow requires an intent nonce; ID token validation now enforces the OIDC authorized-party (azp) rule for multi-audience tokens, requires a numeric issued-at time, and rejects not-yet-valid (nbf) tokens; an agent's email is only used as the account email when the issuer marks it verified, otherwise a non-deliverable placeholder derived from the agent's ID is used; claim values are type-checked before use.
* Multisite: uninstall now removes the plugin's options (including the client secret) and cached issuer data on every site in the network.
* Requires WordPress 6.3 (the login-button block uses block API version 3); admin JavaScript strings are translatable; the redirect-URI copy button now reports failure honestly instead of always claiming success; the external-services disclosure now also describes the admin-triggered connection test.

= 0.1.0 =
* Initial release: Sign in with AgentID on WP and WooCommerce login forms, shortcode and block; authorization-code + PKCE flow with ES256 ID token verification; just-in-time agent account provisioning; owner linking; agent-account safety guards and short sessions; setup wizard with connection test.
