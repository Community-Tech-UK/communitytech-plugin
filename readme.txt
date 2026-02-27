=== CommunityTech Workflow ===
Contributors: communitytech
Tags: elementor, mcp, automation, rest-api, workflow
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin for CommunityTech automation workflows. Exposes REST API endpoints for MCP integrations and future workflow tooling.

== Description ==

CommunityTech Workflow is a modular companion plugin that bridges WordPress/Elementor with external automation tools (MCP servers, CI/CD, etc.).

**Current Modules:**

* **Elementor Kit Settings** — Exposes global colors, typography, and theme style via REST API endpoints, enabling programmatic read/write of the Elementor design system.
* **Elementor CSS Manager** — Regenerate Elementor CSS files after programmatic page data updates.
* **Elementor Widget Registry** — Discover all registered widgets, their controls, content fields, and categories via REST API.
* **SiteSEO** — Read and update SEO metadata per post/page, audit SEO completeness across the site, and read global SiteSEO settings.
* **Elementor Render Rebuild** — Force Elementor to rebuild rendered HTML (post_content) after programmatic data updates via REST API.
* **WP Options** — Read, update, and delete wp_options entries via REST API (admin only).

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

= Elementor CSS =

* `POST /wp-json/communitytech/v1/elementor/css/regenerate`    — Regenerate CSS for a page (requires post_id)

= Elementor Widgets =

* `GET  /wp-json/communitytech/v1/elementor/widgets`            — List all registered widgets
* `GET  /wp-json/communitytech/v1/elementor/widgets/categories` — Widget categories with counts
* `GET  /wp-json/communitytech/v1/elementor/widgets/<name>`     — Single widget detail with controls

= SiteSEO =

* `GET  /wp-json/communitytech/v1/siteseo/post/<id>`    — Get all SEO meta for a post/page
* `POST /wp-json/communitytech/v1/siteseo/post/<id>`    — Update SEO meta for a post/page
* `GET  /wp-json/communitytech/v1/siteseo/audit`        — Audit SEO completeness across posts
* `GET  /wp-json/communitytech/v1/siteseo/settings`     — Read global SiteSEO configuration

= Elementor Render =

* `POST /wp-json/communitytech/v1/elementor/render/rebuild` — Rebuild rendered HTML for a page (requires post_id)

= WP Options =

* `GET    /wp-json/communitytech/v1/options?names=opt1,opt2` — Read options
* `POST   /wp-json/communitytech/v1/options`                 — Update options (body: `{"options":{"key":"value"}}`)
* `DELETE /wp-json/communitytech/v1/options`                  — Delete options (body: `{"names":["opt1","opt2"]}`)

== Changelog ==

= 1.4.0 =
* New: WP Options module — read, update, and delete wp_options entries via REST API. Restricted to administrators.

= 1.3.0 =
* New: Elementor Render Rebuild module — force Elementor to rebuild rendered HTML (post_content) after programmatic _elementor_data updates via REST API.

= 1.2.0 =
* New: SiteSEO module — read/update per-post SEO metadata, audit SEO completeness across the site, and read global SiteSEO settings.

= 1.1.0 =
* New: Elementor Widget Registry module — discover all registered widgets, controls, and content fields via REST API.
* Fix: CSS regeneration endpoint — use correct `get_path()` method instead of non-existent `get_file_path()`.

= 1.0.2 =
* Tested up to WordPress 6.9.

= 1.0.1 =
* Fix plugin and author URIs to communitytech.co.uk.
* Add GitHub-based auto-updates via plugin-update-checker.

= 1.0.0 =
* Initial release.
* Elementor Kit Settings module with full REST API for global colors, typography, and theme style.
