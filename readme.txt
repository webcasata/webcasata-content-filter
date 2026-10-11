=== Webcasata Content Filter ===
Contributors: webcasata
Tags: content filter, tabs, ajax, posts, showcase
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create tabbed, AJAX-filtered showcases of your posts. No page reload. SEO-friendly initial render.

== Description ==

Webcasata Content Filter lets you build a tabbed post filter (by category or any taxonomy) and drop it
anywhere with a shortcode. Visitors switch tabs via AJAX -- no page reload -- while the first tab is
rendered server-side so search engines see real content.

= Free features (this version) =
* Unlimited filters, each with its own shortcode or Gutenberg block
* Filter by any post type + taxonomy, with tabs generated from that taxonomy's terms
* AJAX tab switching, no page reload, with skeleton loading placeholders
* URL-shareable tabs (bookmark or share a link straight to a specific tab) and "remember my last tab" across visits
* Load More button or Infinite Scroll, your choice per filter
* Accessible, keyboard-navigable tabs with smooth fade-in animations
* 5 card templates: Classic, Cards, Magazine, Minimal, News
* Full styling controls: tab colors, card radius/shadow/gap/columns, typography
* Card Elements toggles: show or hide the Title, Date, Username/Author, Excerpt, and Comment Count per filter
* Custom button text per filter (e.g. "Visit Hospital") instead of the fixed "Read More"
* Gutenberg block with a live preview and per-block overrides (posts per page, layout)
* Transient-based output caching with automatic invalidation, lazy-loaded images, minified assets, and conditional asset loading
* WooCommerce-aware: filtering the Product post type shows price and an Add to Cart button (AJAX-enabled) on every card template

= Pro =
Premium templates, a visual drag-and-drop layout builder, an advanced query builder, click analytics,
and import/export are available in Webcasata Content Filter Pro:
https://www.webcasata.com/webcasata-content-filter/

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate through the "Plugins" menu in WordPress
3. Go to "Content Filter" in the admin menu and create your first filter
4. Copy the generated shortcode into any post or page, or add the "Webcasata Content Filter" block instead

== Frequently Asked Questions ==

= Does this work with WooCommerce? =

Yes. Select "Product" as the post type (and a product taxonomy such as Product Categories) and
every card template shows the product's price and the correct Add to Cart button -- simple,
variable, external, whatever WooCommerce decides -- with the same AJAX add-to-cart behavior
WooCommerce's own theme templates use.

= Why don't I see all my post types in the "Select post type" step? =

Only post types that have a public taxonomy attached are listed, since this plugin's tabs are
built from that taxonomy's terms -- a post type with none would be a dead end here anyway. This is
also what keeps page-builder-internal post types (e.g. Elementor's "Template" and "Floating
Element" entries) out of the list without this plugin needing to know those plugins exist. If a
post type you expect is missing, check whether it has a public, admin-visible taxonomy registered
to it.

= Does this plugin have hooks for developers? =

Yes -- every action and filter this plugin exposes is documented with a working code example in
docs/DEVELOPER.md inside the plugin folder, and directly in code as a comment above each
do_action()/apply_filters() call.

== Changelog ==

= 1.4.0 =
* Added a per-filter "Card Button Label" field under Display Options: give each filter's card
  button its own text (e.g. "Read more about doctor" for a Doctors filter, "Visit Hospital" for a
  Hospitals filter) instead of the fixed "Read More". Leave it blank to keep the default text.

= 1.3.0 =
* WooCommerce compatibility: filtering the "Product" post type now shows each product's price and
  the correct Add to Cart button (simple, variable, external -- WooCommerce decides) on every
  built-in card template, with the same AJAX add-to-cart behavior WooCommerce's own templates use.
* The "Select post type" step now only lists post types that actually have a public taxonomy to
  build tabs from. Page-builder-internal post types (e.g. Elementor's "Template" and "Floating
  Element") no longer clutter the list; any post type with a real taxonomy attached is unaffected.

= 1.2.0 =
* Refreshed the whole admin area -- filter list, the Add/Edit wizard, and Settings -- with a cleaner
  card-based layout, refined spacing/colors, and smooth toggle switches for every on/off option.
* Added per-filter "Card Elements" toggles under Display Options: show or hide the Title, Date,
  Username/Author, Excerpt, and Comment Count independently, for every built-in template. Off by
  default is "show everything," so existing filters keep looking exactly as they did before
  updating.

= 1.1.4 =
* The "Upgrade to Pro" admin banner is now permanently dismissible: clicking its close button
  hides it right away and remembers the dismissal per admin user, so it does not come back on a
  later visit, a different screen, or a new browser session.

= 1.1.3 =
* Changed the wbcf_after_save_filter extension hook to pass only the filter ID, not request
  data -- consumers now read and sanitize their own specific form field directly from $_POST
  inside their own callback, matching how core's save_post hook works, rather than receiving
  raw request data as a hook argument.

= 1.1.2 =
* Tested up to WordPress 7.1.
* Simplified an internal function name back to the plugin's standard "wbcf" prefix for consistency
  (was temporarily longer than necessary during an earlier naming fix).
* Documented, in code comments, why template/view files' local variables aren't further prefixed:
  they're always included from within a method's scope, never loaded standalone.

= 1.1.0 =
* All internal identifiers (classes, functions, constants, options, postmeta keys, the custom post
  type slug, shortcode tag, AJAX actions, and script/style handles) renamed from the "wcf"/"WCF"
  prefix to "wbcf"/"WBCF" to meet the required minimum prefix length. The plugin slug and text
  domain are unchanged. Existing test filters will need to be recreated after updating, since the
  custom post type's internal slug changed.
* Escaped output for post titles and author names in every template (previously relied on
  the_title()/the_author() directly, which do not escape by default).
* Unslashed $_POST once, centrally, before passing it to the wbcf_after_save_filter extension hook,
  rather than passing the raw superglobal.

= 1.0.6 =
* Added DEVELOPER.md -- full reference for every action/filter hook this plugin exposes, with
  working code examples, covering both this plugin and Pro's own hooks.
* Various small additive hooks and a settings-application refactor added across 1.0.1-1.0.6, all in
  support of Pro's builder modules (Visual Layout Builder, Advanced Query Builder, Analytics,
  Import/Export) -- see DEVELOPER.md for the full list; nothing here changes existing behavior.

= 1.0.0 =
* Initial release: core filtering engine, admin UI, AJAX, 5 card templates, styling system,
  Gutenberg block with live preview, output caching, and Load More/Infinite Scroll pagination.
