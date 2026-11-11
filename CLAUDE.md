# RW Dealer Portal Addons — instructions for Claude

WordPress plugin that extends **RW Dealer Portal** (the "core" plugin, `rw-dealer-portal`, separate repo/licensed product).
Owner: Jared Nolt / Rosewood Marketing. Used on several client sites (e.g. Monarch Rest), so every change must stay
**generic and reusable** — no client-specific names, IDs, colors or copy in this repo.

## Hard rules

- **Never edit the core RW Dealer Portal plugin.** Everything is done from this add-on via core functions, WordPress hooks,
  `register_post_type_args`, `user_has_cap` / `map_meta_cap`, output filters (`do_shortcode_tag`, `elementor/widget/render_content`), etc.
  Always guard core calls with `function_exists()` / `defined()`; the add-on bootstraps only when `RWDP_VERSION` is defined.
- **New features are off by default** (a setting or toggle), so updating a site never changes its behavior until enabled.
  Exception only when the setting preserves existing behavior by default (e.g. `enable_service_area` defaults to on).
- **Never change stored data shapes or meta keys** that existing sites rely on (e.g. `_rwdp_service_radius_miles`). Add new keys instead.
- Requires PHP 8.1+, WordPress 6.5+. Text domain `rw-dealer-portal-addons`. Prefix everything `rwdpa_` (functions, options, meta `_rwdpa_*`,
  shortcodes, CSS classes `rwdpa-`), constants `RWDPA_*`.
- Match the existing style: WordPress coding standards, tabs, docblocks on every function, escape on output, sanitize on input,
  nonce + capability checks on every save/handler.

## Layout

| File | Purpose |
|---|---|
| `rw-dealer-portal-addons.php` | Header, `RWDPA_VERSION`, bootstrap (`rwdpa_bootstrap` on `plugins_loaded`) — add new `includes/` files here. |
| `includes/github-updater.php` | Auto-updates from this repo's **latest GitHub Release** (tag = version, zipball = package). |
| `includes/settings-page.php` | Dealer Portal → **Addons** page, option `rwdpa_settings` (contractor list, service area toggle, Dealer URL Base). |
| `includes/contractor-list.php`, `elementor/widgets/contractor-list-widget.php`, `assets/js/print-filters.js` | Printable/PDF contractor list + map print button. |
| `includes/service-area.php`, `assets/*/service-area-map.*` | Service radius field (`_rwdp_service_radius_miles`), map circle/labels. |
| `includes/dealer-permalink.php` | Optional rw_dealer URL base (avoids `/dealer/` clashes); flushes rewrites on change. |
| `includes/portal-settings.php` | Dealer Portal → **Portal Display** page, option `rwdpa_portal`; tabbed, filterable via `rwdpa_portal_tabs`. A tab with a `rwdpa_portal_render_tab_{key}` action renders outside the options form. Sanitize merges per tab — never wipe other tabs. |
| `includes/dealer-portal-fields.php` | Shared "Portal Details" meta box on `rw_dealer` (`rwdpa_dealer_portal_fields` / `rwdpa_save_dealer_portal_fields` actions) + user-profile summary (`rwdpa_user_portal_summary_rows`). |
| `includes/tiers.php` | Dealer tiers = core portal roles with rank/title/intro/badge/accent/benefits; tier on dealer (`_rwdpa_tier`) synced to linked users' roles; `[rwdpa_tier_card]`, `[rwdpa_tier_badge]`, `{protected_radius}` token. |
| `includes/territories.php` | Sales manager role `rwdpa_sales_manager`, Territories taxonomy `rwdpa_territory` (manager + states), resolution override → territory → state → fallback; `[rwdpa_sales_manager]`; "is a sales manager" profile checkbox; Manager Permissions (copy another role's caps). |
| `includes/manager-restrictions.php` | Limits on managers (blocked caps, can't touch admins/managers, excluded from Elementor). Filters `rwdpa_manager_blocked_caps`, `rwdpa_manager_protected_roles`. |
| `includes/portal-shortcodes.php` | `[rwdpa_account_bar]`, `[rwdpa_office_contact]`, `[rwdpa_asset_category term=""]`. |
| `includes/registration-fields.php` | Optional phone (`_rwdpa_phone`) and address (`_rwdpa_address`: street/city/state/zip) fields injected into the core Request Access form. |
| `includes/import-tool.php` | Portal Display → **Import**: dealer tier/radius/territory/manager + link users by email (`rwdpa_import_run()`, dry run). |
| `includes/asset-import.php` | Portal Display → **Import Assets**: create/update `rw_asset` posts from CSV (`rwdpa_asset_import_run()`, dry run). |
| `assets/css/portal-display.css` | Front-end styles for the shortcodes; theming via CSS custom properties (`--rwdpa-*`), no site-specific values. |
| `assets/js/portal-admin.js` | Portal Display admin UI (media pickers, color pickers, repeaters). |

## Core plugin facts worth knowing (RW Dealer Portal 2.x)

- Post types `rw_dealer` (slug `dealer`), `rw_asset`; taxonomy `rw_asset_category`. Users link to dealers via user meta `_rwdp_dealer_ids` (int array).
- Portal roles: `rwdp_dealer` + custom roles in option `rwdp_portal_roles`; `rwdp_get_viewer_roles()`. Content "Visible To" = meta `RWDP_ALLOWED_ROLE_META` (one row per role); the check is a plain role match — that's why tiers can be **cumulative** (higher tiers also get lower tier roles).
- Approval status: user meta `_rwdp_account_status` (`pending` / `approved`; empty = approved).
- Asset meta shapes: `_rwdp_asset_type`, `_rwdp_asset_gallery_items` `[title,image_ids[]]`, `_rwdp_asset_video_items` `[title,url]`, `_rwdp_asset_pdf_items` `[pdf_id,thumb_id,use_cover,caption]`, `_rwdp_asset_zip_id`, `_rwdp_asset_external_items`. Core save handler needs its nonce, so programmatic saves write meta directly, then call `rwdp_protect_typed_asset_files()`.
- File protection **moves** PDF/ZIP attachments into a flattened `uploads/rwdp-protected-*/name.ext` folder (direct links break; re-resolve by file name).
- The core asset category view always orders sections by type: gallery, video, PDF, ZIP, link.
- Core has almost no hooks of its own — prefer WordPress/Elementor hooks and output filters.

## Gotchas (learned the hard way)

- **Elementor + `the_content` loop:** core `rwdp_render_asset_taxonomy_view()` runs `the_content` per asset; inside an Elementor page that re-renders the page → infinite loop → 500/502 for logged-in users. Any shortcode that calls core render code must pause Elementor's content filter (`\Elementor\Plugin::$instance->frontend->remove_content_filter()` / `add_content_filter()`) and guard re-entry — see `rwdpa_asset_category_shortcode()`.
- **WordPress role dropdown wipes extra roles** on profile save (`set_role`). Re-apply derived roles on `profile_update` (tier sync, manager flag).
- **Ultimate Member** (if active) overrides user-edit caps and adds reCAPTCHA to `wp-login.php`, which blocks the core login form. Test with UM off.
- **Elementor V4 atomic text elements** strip `<form>/<div>/<img>` from dynamic-tag output, but shortcode text typed into a paragraph is executed on render — that's how sites place these shortcodes without V3 widgets.
- `rewrite` changes need a flush **after** post types register (done via an option flag + `init` priority 999).
- Changing a role label: update the `wp_user_roles` option and call `wp_roles()->for_site()`.

## Workflow

1. Work on a feature branch; keep `main` = what's released.
2. Test on a local WordPress site with core + this add-on active (e.g. DevKinsta: `docker exec devkinsta_fpm php8.1 /usr/local/bin/wp --path=/www/kinsta/public/<site> --allow-root ...`).
   - `php -l` every changed PHP file.
   - Exercise features with `wp eval` against real functions (create test data, assert, then **delete the test data**).
   - For front-end output that needs a logged-in user, generate a `wp_generate_auth_cookie()` cookie and `curl` the page.
   - Never leave test users/posts/terms behind; back up the DB (`wp db export`) before destructive tests.
3. **Version bump for every release** (all three, same number, semver — patch for fixes, minor for features):
   - `Version:` in the plugin header and `RWDPA_VERSION` in `rw-dealer-portal-addons.php`
   - `Stable tag:` and a new `= x.y.z =` entry at the top of `== Changelog ==` in `readme.txt`
4. Commit messages: imperative summary + `(x.y.z)`.
5. **Release:** merge to `main`, tag `vX.Y.Z`, publish a GitHub Release from the tag → sites update through the built-in updater.
   For a manual zip, the plugin folder inside the zip is `rw-dealer-portal-addons-main/` (exclude `.git`, `.gitignore`, `CLAUDE.md`).
6. Don't push, tag or publish releases without the owner's go-ahead.
