=== CommunityTech Workflow ===
Contributors: communitytech
Tags: elementor, mcp, automation, rest-api, workflow
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin for CommunityTech automation workflows. Exposes REST API endpoints for MCP integrations and future workflow tooling.

== Description ==

CommunityTech Workflow is a modular companion plugin that bridges WordPress/Elementor with external automation tools (MCP servers, CI/CD, etc.).

**Current Modules:**

* **Elementor Kit Settings** — Exposes global colors, typography, and theme style via REST API endpoints, enabling programmatic read/write of the Elementor design system.

The plugin uses an auto-discovery module system. Drop a new module folder into `includes/modules/` and it will be picked up automatically.

== Installation ==

1. Upload the `communitytech-plugin` folder to `/wp-content/plugins/`.
2. Activate via the Plugins admin page.
3. Visit the **CommunityTech** menu item to see module status and available endpoints.

== REST API Endpoints ==

All endpoints require authentication (Application Passwords or cookie auth).

= Elementor Kit =

* `GET  /wp-json/communitytech/v1/elementor/kit`             — Full kit settings
* `POST /wp-json/communitytech/v1/elementor/kit`             — Update kit settings (merge)
* `GET  /wp-json/communitytech/v1/elementor/kit/colors`      — Global colors
* `POST /wp-json/communitytech/v1/elementor/kit/colors`      — Update global colors
* `GET  /wp-json/communitytech/v1/elementor/kit/typography`   — Global typography
* `POST /wp-json/communitytech/v1/elementor/kit/typography`   — Update global typography
* `GET  /wp-json/communitytech/v1/elementor/kit/theme-style`  — Theme style settings
* `POST /wp-json/communitytech/v1/elementor/kit/theme-style`  — Update theme style
* `GET  /wp-json/communitytech/v1/elementor/kit/css-variables` — CSS variable map (read-only)

== Changelog ==

= 1.0.2 =
* Tested up to WordPress 6.9.

= 1.0.1 =
* Fix plugin and author URIs to communitytech.co.uk.
* Add GitHub-based auto-updates via plugin-update-checker.

= 1.0.0 =
* Initial release.
* Elementor Kit Settings module with full REST API for global colors, typography, and theme style.
