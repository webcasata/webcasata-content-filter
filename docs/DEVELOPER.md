# Webcasata Content Filter — Developer API

Every hook in this document is a real, stable extension point — not a
suggestion for where you *could* patch core files. If you find yourself
about to edit a plugin file directly, there's almost certainly a hook
here that does the same job safely, and survives plugin updates.

Hooks are split into two groups:

- **Free plugin hooks** — always available once Webcasata Content Filter
  is active.
- **Pro plugin hooks** — only available if Webcasata Content Filter Pro
  is also active (they're registered by Pro's own classes).

All standard WordPress hook conventions apply: `add_action()` /
`add_filter()` in your theme's `functions.php`, a small custom plugin,
or a [Code Snippets](https://wordpress.org/plugins/code-snippets/)-style
tool.

---

## Free plugin hooks

### Actions

#### `wbcf_before_card` / `wbcf_after_card`
Fires immediately before/after each card is rendered, inside the
`WP_Query` loop — for *every* template (Classic, Cards, Magazine, any
Pro template, Visual Layout Builder, all of them).

```php
do_action( 'wbcf_before_card', $post, $filter_id );
do_action( 'wbcf_after_card', $post, $filter_id );
```

**Use for:** click/impression tracking, injecting a badge or ribbon
around specific cards, debug logging.

```php
// Wrap every card in a data attribute exposing the post ID, for your own JS to read.
add_action( 'wbcf_before_card', function ( $post, $filter_id ) {
	echo '<div data-my-plugin-post="' . esc_attr( $post->ID ) . '">';
}, 10, 2 );
add_action( 'wbcf_after_card', function () {
	echo '</div>';
} );
```

#### `wbcf_tab_viewed`
Fires when a visitor actually clicks a tab (an AJAX tab-switch request)
— not on the server-rendered initial tab, and not on Load More/Infinite
Scroll requests for a tab that's already active. This is exactly what
Pro's Analytics module hooks to record click counts.

```php
do_action( 'wbcf_tab_viewed', $filter_id, $tab );
```

`$tab` is either `'all'` or a term slug.

```php
// Log every tab click to your own analytics system.
add_action( 'wbcf_tab_viewed', function ( $filter_id, $tab ) {
	my_analytics_log( 'wbcf_tab_click', array( 'filter' => $filter_id, 'tab' => $tab ) );
}, 10, 2 );
```

#### `wbcf_after_save_filter`
Fires after a filter's core settings have been saved **from the admin
form** (Title, Post Type, Taxonomy, Terms, Display Options, Styling).
This is how both of Pro's builder modules (Visual Layout Builder,
Advanced Query Builder) save their own postmeta right after Free saves
its own — without Free needing to know either of those modules exist.

```php
do_action( 'wbcf_after_save_filter', $filter_id );
```

Only `$filter_id` is passed — not `$_POST` or any other request data. If your callback
needs its own custom form field, read and sanitize it directly from `$_POST` inside your
callback, the same way core's own `save_post` hook expects its consumers to. This keeps
raw request data from ever being handed to a hook argument, where any callback (including
ones you don't control) could receive it unsanitized.

Note: this does **not** fire for `WBCF_Admin::apply_settings()` calls
that come from a plain array rather than a real form submission (Pro's
Import feature uses that path) — see that method's docblock for why.

```php
// Save your own extra field alongside a filter's settings.
add_action( 'wbcf_after_save_filter', function ( $filter_id ) {
	if ( isset( $_POST['my_custom_field'] ) ) {
		update_post_meta( $filter_id, '_my_custom_field', sanitize_text_field( wp_unslash( $_POST['my_custom_field'] ) ) );
	}
} );
```

#### `wbcf_assets_enqueued`
Fires right after the plugin's own public CSS/JS are enqueued —
whether that happened eagerly (`wp_enqueue_scripts`) or late (a
shortcode/block rendering inside a widget or page builder, where the
shortcode wasn't detectable ahead of time). This is the reliable way to
load your own stylesheet alongside the plugin's, rather than guessing
at page-load timing yourself. Pro's premium templates use this.

```php
do_action( 'wbcf_assets_enqueued' );
```

```php
add_action( 'wbcf_assets_enqueued', function () {
	wp_enqueue_style( 'my-extra-styles', plugins_url( 'my-styles.css', __FILE__ ) );
} );
```

#### `wbcf_admin_edit_after_template_field` / `wbcf_admin_edit_after_steps`
Two injection points on the filter edit screen (`admin.php?page=wbcf-add-filter`),
still inside the same `<form>`:

- `wbcf_admin_edit_after_template_field` fires right after the **Card
  Template** field (Step 5), before Styling — the right place for admin
  UI that depends on which template is selected. Pro's Visual Layout
  Builder step lives here, hidden/shown via JS based on the Card
  Template dropdown.
- `wbcf_admin_edit_after_steps` fires after **every** built-in step
  (including Styling) — the right place for admin UI that isn't
  template-specific. Pro's Advanced Query Builder step lives here.

Both receive `$data`, the same array the built-in steps use (includes
`'ID'` for the current filter, useful for loading your own saved state).

```php
do_action( 'wbcf_admin_edit_after_template_field', $data );
do_action( 'wbcf_admin_edit_after_steps', $data );
```

```php
add_action( 'wbcf_admin_edit_after_steps', function ( $data ) {
	$filter_id = isset( $data['ID'] ) ? absint( $data['ID'] ) : 0;
	$saved     = $filter_id ? get_post_meta( $filter_id, '_my_setting', true ) : '';
	echo '<div class="wbcf-step"><div class="wbcf-step__body">';
	echo '<h2>My Setting</h2>';
	echo '<input type="text" name="my_setting" value="' . esc_attr( $saved ) . '">';
	echo '</div></div>';
} );
// Then save it in a callback on 'wbcf_after_save_filter', as shown above.
```

---

### Filters

#### `wbcf_query_args`
The main extension point for changing *which posts* a filter shows.
Runs right before every `WP_Query` a filter makes (both the initial
page and every Load More/Infinite Scroll page). This is what Pro's
Advanced Query Builder uses for its Author/Taxonomy/Custom
Field/WooCommerce rules — and it's the same filter you'd use for
anything else query-related.

```php
$query_args = apply_filters( 'wbcf_query_args', $query_args, $filter_id, $tab );
```

```php
// Only show posts published in the last 30 days, for one specific filter.
add_filter( 'wbcf_query_args', function ( $args, $filter_id, $tab ) {
	if ( 123 === $filter_id ) {
		$args['date_query'] = array( array( 'after' => '30 days ago' ) );
	}
	return $args;
}, 10, 3 );
```

#### `wbcf_results_html`
Filters the fully-rendered HTML for one tab's results (the whole
`<div class="wbcf-grid">...</div>`, or the "no posts found" message),
right before it's returned to the AJAX handler or the initial render.

```php
$html = apply_filters( 'wbcf_results_html', $html, $filter_id, $tab );
```

```php
// Wrap every filter's results in a tracking container.
add_filter( 'wbcf_results_html', function ( $html, $filter_id, $tab ) {
	return '<div data-wbcf-tracked="' . esc_attr( $filter_id ) . '">' . $html . '</div>';
}, 10, 3 );
```

#### `wbcf_cache_ttl`
Filters how long (in seconds) a tab's rendered HTML stays cached in a
transient. Return `0` or less to disable caching entirely for a given
filter/tab — useful if your `wbcf_query_args` callback depends on
something that isn't reflected by a post save (a live vote count, a
per-visitor condition, etc.), so a cached result would go stale.

```php
$ttl = apply_filters( 'wbcf_cache_ttl', 12 * HOUR_IN_SECONDS, $filter_id, $tab );
```

```php
// Disable caching for one filter that shows live data.
add_filter( 'wbcf_cache_ttl', function ( $ttl, $filter_id, $tab ) {
	return ( 456 === $filter_id ) ? 0 : $ttl;
}, 10, 3 );
```

#### `wbcf_template_choices`
Filters the list of templates shown in the admin's Card Template
dropdown (and the Gutenberg block's Override Layout list). Keys are
template slugs resolved by `locate_template()` / `wbcf_locate_template`
below; values are display labels. This is how Pro's premium templates,
and the Visual Layout Builder's "🎨 Visual Layout (Custom)" entry, get
into that dropdown without editing the admin screen.

```php
$choices = apply_filters( 'wbcf_template_choices', $choices );
```

```php
add_filter( 'wbcf_template_choices', function ( $choices ) {
	$choices['my-template'] = '⭐ My Custom Template';
	return $choices;
} );
```

#### `wbcf_locate_template`
Fires when a template slug isn't found in the active theme's
`webcasata-content-filter/` override folder or this plugin's own
`/templates` directory. Return a full file path if you can resolve the
slug, otherwise leave the passed-in value (`false`) alone. This is how
you supply the actual template *file* for a slug registered via
`wbcf_template_choices` — without ever needing to copy files into this
plugin's folder, which would just get wiped on the next update.

```php
$path = apply_filters( 'wbcf_locate_template', false, $slug, $file );
```

```php
add_filter( 'wbcf_locate_template', function ( $found, $slug, $file ) {
	if ( 'my-template' === $slug ) {
		$path = plugin_dir_path( __FILE__ ) . 'templates/' . $file;
		if ( file_exists( $path ) ) return $path;
	}
	return $found;
}, 10, 3 );
```

Your template file gets `$settings` (the filter's full settings array),
`$filter_id`, `$page`, and `$query` available automatically — PHP's
`include()` shares the calling function's variable scope, the same
mechanism the Hero template uses to know if it's rendering the very
first post. See any file under `templates/` in the free plugin, or
Pro's, for the exact pattern.

#### `wbcf_templates_without_columns`
Filters the list of template slugs where the Styling system's
"Columns" setting doesn't apply (they always render one full-width
entry per row — Free's News template, Pro's Timeline and Split). The
admin hides the Columns field automatically for whichever slugs are in
this list, rather than leaving a control that visibly does nothing.

```php
$list = apply_filters( 'wbcf_templates_without_columns', $list );
```

```php
add_filter( 'wbcf_templates_without_columns', function ( $list ) {
	$list[] = 'my-full-width-template';
	return $list;
} );
```

---

#### `wbcf_show_woocommerce_meta`
Filters whether a product card's price + Add to Cart block renders.
Fires only when the post being rendered is a WooCommerce `product` and
WooCommerce is active — every other post type never calls this. Return
`false` to hide the block for a specific product without overriding
the whole card template.

```php
$show = apply_filters( 'wbcf_show_woocommerce_meta', true, $product_id );
```

```php
// Hide price/Add to Cart for out-of-stock products only.
add_filter( 'wbcf_show_woocommerce_meta', function ( $show, $product_id ) {
	$product = wc_get_product( $product_id );
	return ( $product && ! $product->is_in_stock() ) ? false : $show;
}, 10, 2 );
```

---

## Pro plugin hooks

*Only fire if Webcasata Content Filter Pro is active.*

#### `wbcfp_layout_element_definitions`
Filters the list of element types shown in the Visual Layout Builder
(the drag-and-drop list of Featured Image / Title / Excerpt / etc.).
Keys are element type slugs, values are display labels.

```php
$defs = apply_filters( 'wbcfp_layout_element_definitions', $defs );
```

```php
add_filter( 'wbcfp_layout_element_definitions', function ( $defs ) {
	$defs['my_element'] = 'My Custom Element';
	return $defs;
} );
```

#### `wbcfp_render_layout_element`
The companion to the filter above — renders your custom element type
when a card containing it is actually displayed. Return the element's
**inner** HTML only (not echoed, not wrapped) — the builder applies the
same `.wbcfp-layout__element` wrapper and alignment handling every
built-in element gets. `$element` is the element's full saved array
(`type`, `enabled`, `align`, plus whatever custom keys you're storing).

```php
$html = apply_filters( 'wbcfp_render_layout_element', '', $type, $element );
```

```php
add_filter( 'wbcfp_render_layout_element', function ( $html, $type, $element ) {
	if ( 'my_element' !== $type ) return $html;
	return '<strong>Post ID: ' . get_the_ID() . '</strong>';
}, 10, 3 );
```

Runs inside the same post loop as every built-in element, so standard
template tags (`get_the_ID()`, `get_the_title()`, etc.) all work as
expected without any extra setup.

#### `wbcfp_layout_alignable_types`
Filters which element types show the left/center/right alignment
buttons in their options panel. Add your custom type here if it makes
sense for it to be alignable (most text-like elements do; things like
images usually don't need it).

```php
$types = apply_filters( 'wbcfp_layout_alignable_types', $types );
```

```php
add_filter( 'wbcfp_layout_alignable_types', function ( $types ) {
	$types[] = 'my_element';
	return $types;
} );
```

---

## Common patterns

**Reading a filter's settings from anywhere:**
```php
$settings = WBCF_Render::get_settings( $filter_id );
// $settings['post_type'], ['taxonomy'], ['terms'], ['template'], ['styles'], etc.
```

**Manually rendering a filter (outside the shortcode/block):**
```php
echo WBCF_Render::render_filter( $filter_id );
```

**Checking whether Pro is active before hooking a Pro-only filter:**
```php
if ( class_exists( 'WBCFP_Layout_Builder' ) ) {
	add_filter( 'wbcfp_layout_element_definitions', 'my_callback' );
}
```
