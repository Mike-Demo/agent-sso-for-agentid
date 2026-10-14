# WordPress.org submission checklist — Agentic Agent SSO

Status as of 2026-10-14: **everything is ready except the upload itself**, which needs
the owner's WordPress.org account.

## The package

| Item | State |
| --- | --- |
| Plugin zip | `agentic-agent-sso-0.1.4.zip` — folder `agentic-agent-sso/`, built per `.distignore` (no dev files, no `.wordpress-org/`) |
| Version / stable tag | 0.1.4 |
| License | GPLv2 (LICENSE file included) |
| Plugin Check (official, v2.1.0) | shipped file set: 0 errors / 0 warnings |
| PHPCS (WPCS + PHPCompatibilityWP) | 0 errors / 0 warnings |
| Code reviews | Copilot full review (v0.1.1), Copilot Auto quality review (v0.1.3), Wonder-model review (v0.1.4) — see `docs/code-review/`; all findings fixed or dispositioned |
| Live verification | Full real-agent AgentID sign-in verified end-to-end on v0.1.1 and again on v0.1.4 (see TESTING.md) |

## Listing assets (in `.wordpress-org/` in this repo)

These map to the SVN `assets/` folder after approval — they are deliberately NOT in
the plugin zip (`.distignore` excludes the directory).

| File | Size | What it is |
| --- | --- | --- |
| `banner-772x250.png` | 772×250 | Listing banner |
| `banner-1544x500.png` | 1544×500 | Listing banner, retina |
| `icon-256x256.png` | 256×256 | Plugin icon |
| `icon-128x128.png` | 128×128 | Plugin icon, small |
| `screenshot-1.png` | 1440×1205 | Settings → Agentic Agent SSO setup wizard |
| `screenshot-2.png` | 1440×1000 | "Sign in with AgentID" on wp-login |
| `screenshot-3.png` | 1440×1000 | Profile: "Link your agent" |
| `screenshot-4.png` | 1440×1000 | Agent account: identity + owner on record, password paths disabled |
| `screenshot-5.png` | 1440×1000 | AgentID Login Button block in the editor |

Screenshot captions live in readme.txt (`== Screenshots ==`) and match the numbering
above. Captions and screenshots must stay in sync — if a screenshot is added, removed
or reordered, update the captions in the same commit.

### How the assets were made (2026-10-14)

- Screenshots are real captures of the plugin running in the local Playground test
  rig (headless Chromium, logged in as the site admin). For the captures, the rig
  used a throwaway dummy Client ID and the agent account's identity values were
  temporarily replaced with sample data ("Alex Example", `alex@example.com`, a
  sample subject) so no real identifiers appear in public listing images. The rig
  was restored afterwards and the live sign-in was not affected.
- Icon and banners are vector artwork (persona + key mark on the brand gradient),
  rendered to PNG at the exact required sizes. Source: `docs/assets-source.html` —
  edit and re-render to change them; keep the exported pixel sizes exact.

## Remaining steps (owner)

1. Sign in at https://wordpress.org/plugins/developers/add/ with the owner's
   WordPress.org account and upload `agentic-agent-sso-0.1.4.zip`.
2. Wait for the review email (the plugin review team replies to the account email).
   If they request changes, fix on `main`, bump the version, rebuild the zip, and
   reply on the review thread.
3. After approval, the SVN repo is created under
   `https://plugins.svn.wordpress.org/agentic-agent-sso/`:
   - commit the plugin files (the zip's contents) to `trunk/`,
   - copy `trunk/` to `tags/0.1.4/`,
   - commit the `.wordpress-org/` files to `assets/`.
4. Verify the live listing page renders the banner, icon, screenshots and readme
   (the readme validator at wordpress.org/plugins/developers/readme-validator/
   can pre-check formatting).

## Standing notes

- The plugin name contains no "WordPress" (WP is used where needed), and the
  listing copy states the project is independent and not affiliated with AgentMail,
  with credit to the AgentID project — keep both intact in future edits.
- Do not add screenshots that show real client IDs, secrets, email addresses or
  subject identifiers; stage sample data like the 2026-10-14 captures did.
