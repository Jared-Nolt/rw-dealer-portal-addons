=== RW Dealer Portal Addons ===
Contributors: jarednolt
Tags: dealer, map, elementor, directory, addons
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optional add-ons for RW Dealer Portal, including contractor list print/PDF tools and service area enhancements.

== Description ==

RW Dealer Portal Addons extends RW Dealer Portal with:

- Contractor list print and PDF features.
- Service Area radius field support on dealers.
- Service Area display enhancements for map results and map popups.
- Dealer tiers: rank RW Dealer Portal roles as tiers, set the tier on each dealer, and sync it to linked users' portal roles.
- Sales managers and territories: a manager role, territories with a manager and covered states, and per-dealer overrides.
- Portal display shortcodes: [rwdpa_tier_card], [rwdpa_sales_manager], [rwdpa_office_contact], [rwdpa_account_bar].
- Optional phone field on the Request Access form.

All 1.1.0 features are off until enabled on Dealer Portal → Portal Display.

== Changelog ==

= 1.5.1 =
- Fixed a fatal infinite loop (502/500) when [rwdpa_asset_category] is used on an Elementor page by a logged-in portal user: the core asset view runs the_content, which Elementor answered by re-rendering the page. Elementor's content filter is now paused while the asset view renders.

= 1.5.0 =
- Added Portal Display → Import Assets: create/update RW Dealer Portal assets from a CSV (galleries from Modula galleries or image files, videos, PDFs with covers, ZIPs, links), matched by title within a category, with Visible To roles. PDF/ZIP files get the core file protection.
- Added [rwdpa_asset_category term="slug"] to render one asset category anywhere, so a page can show several categories in order.
- Added [rwdpa_tier_badge]: the current dealer's tier badge with a download button.

= 1.4.1 =
- Added Addons → Dealer URL Base: change the /dealer/ permalink base for RW Dealer Portal dealers when another post type already uses it. Empty keeps the default; permalinks are flushed on change.

= 1.4.0 =
- Added Addons → "Use service area" setting (on by default). When off, the radius field, saving, and map circle/labels are disabled.
- The Service Area radius field now lives in the Portal Details box with the other dealer portal fields; the separate "Dealer Add-ons" box only appears for Business Hours on older core versions. Stored data (_rwdp_service_radius_miles) is unchanged.
- Tier benefits that use {protected_radius} are now hidden for dealers without a protected radius (previously filled with fallback text).

= 1.3.1 =
- Sales managers (who are not also administrators) can never see, edit, promote or delete administrators or other managers; never get plugin, theme, core-update, settings (manage_options) or unfiltered HTML capabilities; and are excluded from the Elementor editor. Filters: rwdpa_manager_blocked_caps, rwdpa_manager_protected_roles.

= 1.3.0 =
- Added Sales Managers → Manager Permissions: the manager role can copy every capability of another role (e.g. Administrator) in addition to portal access. Default stays "Portal access only".

= 1.2.1 =
- Sales managers can now be administrators or any other role: a "This user is a sales manager" checkbox on user profiles adds the manager role as an extra role, and it is re-applied after WordPress's role dropdown or bulk role changes.

= 1.2.0 =
- Added Portal Display → Import: update dealers' tier, protected radius, territory and manager override, and link users by email, from a CSV matched on dealer title. Includes a dry run, optional Dealer role for linked users, and optional removal of a legacy role.
- Portal Display tabs are now filterable (rwdpa_portal_tabs).

= 1.1.0 =
- Added Dealer Portal → Portal Display settings (Dealer Tiers, Sales Managers, Office Contact, Registration tabs). Everything is off by default.
- Added dealer tiers: rank, title, intro, badge, accent color and benefits per portal role. Tier and protected radius fields on dealers. Linked users' portal roles sync from their dealer's tier; optionally cumulative so "Visible To" a tier includes higher tiers.
- Added sales managers: configurable manager role, Territories (manager + states) under Dealer Portal, territory/override fields on dealers, manager contact fields on user profiles.
- Added shortcodes [rwdpa_tier_card] (with staff-only tier preview), [rwdpa_sales_manager], [rwdpa_office_contact] and [rwdpa_account_bar].
- Added optional phone field on the Request Access form (shortcode and Elementor widget).
- Added a read-only Dealer Portal Summary (tier, sales manager, phone) on user profiles.

= 1.0.3 =
- Restored service area radius toggle, results/popup text, and map labels after RW Dealer Portal 1.0.19 removed the `rwdp_map_localized_data` and `rwdp_ajax_dealer_data` filters. These now run through independent add-on-owned localized data instead of depending on core filters.
- Restored the contractor list print/PDF button on the Dealer Map widget after RW Dealer Portal 1.0.19 removed the widget's built-in "Show Print/PDF Button" control. The button is now added via Elementor's `elementor/widget/render_content` filter and controlled from Addons → Settings (new "Map Print Button" option).

= 1.0.2 =
- Added compatibility fallback in add-ons for Dealer edit fields when core editor hooks change.
- Restored Business Hours and Service Area field rendering/saving compatibility after RW Dealer Portal core updates.
- Maintained compatibility with latest core geocoding validation flow.

= 1.0.1 =
- Initial public release with GitHub updater support.
