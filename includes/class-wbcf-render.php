<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Single source of truth for turning a filter_id (+ optional active tab)
 * into HTML. The shortcode calls this for the initial, SEO-friendly
 * server render; the AJAX handler calls the exact same methods for tab
 * switches, so markup can never drift between the two.
 *
 * Tabs = terms of the filter's chosen taxonomy. The optional "All" tab
 * combines every selected term into one query.
 */
class WBCF_Render {

	/**
	 * Phase 3 styling system defaults. Every value here means "inherit
	 * the plugin's normal look" -- inline_style_vars() only emits a CSS
	 * override for fields that differ from these, so a filter saved
	 * before Phase 3 existed renders identically to before.
	 */
	public static function get_style_defaults() {
		return array(
			'tab_color'        => '',
			'tab_active_color' => '',
			'tab_hover_color'  => '',
			'tab_radius'       => 0,
			'card_radius'      => '',
			'card_shadow'      => 'default',
			'card_gap'         => '',
			'card_columns'     => 'auto',
			'title_size'       => 'medium',
			'title_color'      => '',
			'meta_color'       => '',
		);
	}

	/**
	 * Per-element show/hide toggles for the built-in card templates
	 * (Classic, Cards, Magazine, Minimal, News). Not every template shows
	 * every element to begin with (e.g. News never had an author/excerpt/
	 * comment count) -- a template simply ignores a toggle for an element
	 * it never rendered in the first place. 'yes' for everything keeps a
	 * filter saved before this setting existed looking identical to
	 * before.
	 */
	public static function get_display_defaults() {
		return array(
			'title'    => 'yes',
			'date'     => 'yes',
			'author'   => 'yes',
			'excerpt'  => 'yes',
			'comments' => 'yes',
		);
	}

	/**
	 * Read a filter's settings out of postmeta into a plain array.
	 */
	public static function get_settings( $filter_id ) {
		$post = get_post( $filter_id );
		if ( ! $post || WBCF_POST_TYPE !== $post->post_type ) {
			return false;
		}

		$styles = get_post_meta( $filter_id, '_wbcf_styles', true );
		$styles = wp_parse_args( is_array( $styles ) ? $styles : array(), self::get_style_defaults() );

		$display = get_post_meta( $filter_id, '_wbcf_display', true );
		$display = wp_parse_args( is_array( $display ) ? $display : array(), self::get_display_defaults() );

		$layout = get_post_meta( $filter_id, '_wbcf_layout', true );
		$layout = is_array( $layout ) ? $layout : array();

		return array(
			'id'             => $filter_id,
			'post_type'      => get_post_meta( $filter_id, '_wbcf_post_type', true ) ?: 'post',
			'taxonomy'       => get_post_meta( $filter_id, '_wbcf_taxonomy', true ) ?: '',
			'terms'          => array_map( 'absint', (array) get_post_meta( $filter_id, '_wbcf_terms', true ) ),
			'posts_per_page' => (int) ( get_post_meta( $filter_id, '_wbcf_posts_per_page', true ) ?: 6 ),
			'show_all_tab'   => get_post_meta( $filter_id, '_wbcf_show_all_tab', true ) ?: 'yes',
			'default_tab'    => get_post_meta( $filter_id, '_wbcf_default_tab', true ) ?: 'all',
			'template'       => get_post_meta( $filter_id, '_wbcf_template', true ) ?: 'card-classic',
			'pagination'     => get_post_meta( $filter_id, '_wbcf_pagination', true ) ?: 'none', // 'none' | 'load_more' | 'infinite_scroll'
			'layout'         => $layout, // Pro's Visual Layout Builder; only read when template === 'wbcf-visual-layout'
			'styles'         => $styles,
			'display'        => $display, // Show/hide toggles: title, date, author, excerpt, comments.
			'readmore_text'  => get_post_meta( $filter_id, '_wbcf_readmore_text', true ) ?: '', // '' = use the default "Read More" string; see get_readmore_text().
		);
	}

	/**
	 * Resolve a filter's card button label, falling back to the default
	 * translated "Read More" string when nothing custom was saved.
	 * Templates call this once wherever the button's label is echoed,
	 * rather than each repeating the same empty-string check.
	 */
	public static function get_readmore_text( $settings ) {
		if ( ! empty( $settings['readmore_text'] ) ) {
			return $settings['readmore_text'];
		}
		return __( 'Read More', 'webcasata-content-filter' );
	}

	/**
	 * Turn a filter's saved styles into a CSS custom-property string for
	 * the widget's inline style="" attribute. Only customized fields are
	 * included -- everything else falls back to the defaults declared on
	 * .wbcf-widget in wbcf-public.css.
	 */
	public static function inline_style_vars( $styles ) {
		$vars = array();

		if ( ! empty( $styles['tab_color'] ) ) {
			$vars[] = '--wbcf-tab-color:' . $styles['tab_color'];
		}
		if ( ! empty( $styles['tab_active_color'] ) ) {
			// Reused everywhere the plugin uses an "accent" color (read more links, avatars), not just the tab underline.
			$vars[] = '--wbcf-active-color:' . $styles['tab_active_color'];
		}
		if ( ! empty( $styles['tab_hover_color'] ) ) {
			$vars[] = '--wbcf-tab-hover-color:' . $styles['tab_hover_color'];
		}
		if ( ! empty( $styles['tab_radius'] ) ) {
			// A non-zero radius turns the tabs from an underline style into filled pill buttons.
			$vars[]     = '--wbcf-tab-radius:' . absint( $styles['tab_radius'] ) . 'px';
			$active_bg  = ! empty( $styles['tab_active_color'] ) ? $styles['tab_active_color'] : '#2271b1';
			$hover_src  = ! empty( $styles['tab_hover_color'] ) ? $styles['tab_hover_color'] : $active_bg;
			$vars[]     = '--wbcf-tab-active-bg:' . $active_bg;
			$vars[]     = '--wbcf-tab-active-text:#ffffff';
			$vars[]     = '--wbcf-tab-underline:transparent';
			$hover_tint = self::hex2rgba( $hover_src, 0.12 );
			if ( $hover_tint ) {
				$vars[] = '--wbcf-tab-hover-bg:' . $hover_tint;
			}
		}

		if ( '' !== $styles['card_radius'] && null !== $styles['card_radius'] ) {
			$vars[] = '--wbcf-border-radius:' . absint( $styles['card_radius'] ) . 'px';
		}
		if ( ! empty( $styles['card_shadow'] ) && 'default' !== $styles['card_shadow'] ) {
			$shadow_map = array(
				'none'   => 'none',
				'soft'   => '0 1px 3px rgba(0, 0, 0, 0.06)',
				'strong' => '0 10px 24px rgba(0, 0, 0, 0.16)',
			);
			if ( isset( $shadow_map[ $styles['card_shadow'] ] ) ) {
				$vars[] = '--wbcf-card-shadow:' . $shadow_map[ $styles['card_shadow'] ];
			}
		}
		if ( '' !== $styles['card_gap'] && null !== $styles['card_gap'] ) {
			$vars[] = '--wbcf-gap:' . absint( $styles['card_gap'] ) . 'px';
		}
		if ( ! empty( $styles['card_columns'] ) && 'auto' !== $styles['card_columns'] ) {
			$n = absint( $styles['card_columns'] );
			if ( $n > 0 ) {
				$vars[] = '--wbcf-grid-columns:repeat(' . $n . ', 1fr)';
				// Same setting, expressed as a raw number -- CSS multi-column
				// layouts (column-count) can't consume a grid-template-columns
				// string, so templates like Pro's Masonry use this instead.
				$vars[] = '--wbcf-columns-count:' . $n;
			}
		}

		if ( ! empty( $styles['title_size'] ) && 'medium' !== $styles['title_size'] ) {
			$size_map = array(
				'small' => '15px',
				'large' => '22px',
			);
			if ( isset( $size_map[ $styles['title_size'] ] ) ) {
				$vars[] = '--wbcf-title-size:' . $size_map[ $styles['title_size'] ];
			}
		}
		if ( ! empty( $styles['title_color'] ) ) {
			$vars[] = '--wbcf-title-color:' . $styles['title_color'];
		}
		if ( ! empty( $styles['meta_color'] ) ) {
			$vars[] = '--wbcf-meta-color:' . $styles['meta_color'];
		}

		return implode( ';', $vars );
	}

	/**
	 * #RGB / #RRGGBB -> "rgba(r,g,b,alpha)". Returns null for anything
	 * that doesn't parse, so callers can skip the var entirely rather
	 * than emit broken CSS.
	 */
	private static function hex2rgba( $hex, $alpha ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return "rgba($r,$g,$b,$alpha)";
	}

	/**
	 * Full widget markup: tab bar + initial (server-rendered) results panel.
	 *
	 * @param array $overrides Optional. Used by the Gutenberg block's
	 *              "Override Posts" / "Override Layout" controls without
	 *              touching the filter's own saved settings.
	 *              Keys: 'posts_per_page' (int), 'template' (string slug).
	 */
	public static function render_filter( $filter_id, $overrides = array() ) {
		$settings = self::get_settings( $filter_id );
		if ( ! $settings || empty( $settings['taxonomy'] ) || empty( $settings['terms'] ) ) {
			return '';
		}
		$settings = self::apply_overrides( $settings, $overrides );

		$requested_slug = self::tab_from_request( $filter_id, $settings );
		if ( null !== $requested_slug ) {
			// The URL says which tab should be active -- e.g. someone followed a shared/bookmarked link.
			$active_id   = self::slug_to_id( $requested_slug, $settings );
			$active_slug = $requested_slug;
		} else {
			$active_id   = self::resolve_active_tab( $settings ); // 'all' or a term ID (as string/int)
			$active_slug = self::id_to_slug( $active_id, $settings['taxonomy'] ); // what actually goes in the URL/markup
		}
		$style_attr = self::inline_style_vars( $settings['styles'] );

		ob_start();
		?>
		<div class="wbcf-widget"
			id="wbcf-widget-<?php echo esc_attr( $filter_id ); ?>"
			data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
			data-active="<?php echo esc_attr( $active_slug ); ?>"
			data-ppp="<?php echo esc_attr( $settings['posts_per_page'] ); ?>"
			<?php if ( ! empty( $overrides['posts_per_page'] ) ) : ?>data-override-ppp="<?php echo esc_attr( absint( $overrides['posts_per_page'] ) ); ?>"<?php endif; ?>
			<?php if ( ! empty( $overrides['template'] ) ) : ?>data-override-template="<?php echo esc_attr( sanitize_key( $overrides['template'] ) ); ?>"<?php endif; ?>
			<?php if ( $style_attr ) : ?>style="<?php echo esc_attr( $style_attr ); ?>"<?php endif; ?>>

			<div class="wbcf-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Content filter', 'webcasata-content-filter' ); ?>">
				<?php if ( 'yes' === $settings['show_all_tab'] ) : ?>
					<button type="button"
						class="wbcf-tab<?php echo 'all' === $active_id ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 'all' === $active_id ? 'true' : 'false'; ?>"
						tabindex="<?php echo 'all' === $active_id ? '0' : '-1'; ?>"
						data-term="all">
						<?php esc_html_e( 'All', 'webcasata-content-filter' ); ?>
					</button>
				<?php endif; ?>

				<?php foreach ( $settings['terms'] as $term_id ) :
					$term = get_term( $term_id, $settings['taxonomy'] );
					if ( ! $term || is_wp_error( $term ) ) continue;
					$is_active = ( (string) $term_id === (string) $active_id );
				?>
					<button type="button"
						class="wbcf-tab<?php echo $is_active ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
						tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
						data-term="<?php echo esc_attr( $term->slug ); ?>">
						<?php echo esc_html( $term->name ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="wbcf-panel"
				role="tabpanel"
				aria-live="polite"
				aria-busy="false">
				<?php echo self::render_results_for_settings( $filter_id, $settings, $active_slug, $overrides ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Merge block-level overrides ('posts_per_page', 'template') onto a
	 * filter's saved settings without touching postmeta.
	 */
	private static function apply_overrides( $settings, $overrides ) {
		if ( ! empty( $overrides['posts_per_page'] ) ) {
			$settings['posts_per_page'] = max( 1, min( 50, absint( $overrides['posts_per_page'] ) ) );
		}
		if ( ! empty( $overrides['template'] ) ) {
			$settings['template'] = sanitize_key( $overrides['template'] );
		}
		return $settings;
	}

	/**
	 * Convert an internal tab value ('all' or a term ID) to the slug used
	 * in markup / URLs / AJAX. Terms are still stored and matched
	 * internally by ID (stable even if a term is renamed) -- the slug is
	 * only the public-facing representation.
	 */
	private static function id_to_slug( $tab_id, $taxonomy ) {
		if ( 'all' === $tab_id ) {
			return 'all';
		}
		$term = get_term( (int) $tab_id, $taxonomy );
		return ( $term && ! is_wp_error( $term ) ) ? $term->slug : 'all';
	}

	/**
	 * The reverse of id_to_slug() -- needed when the active tab comes
	 * from the URL (which only knows the slug) but the tab-button loop
	 * still compares against term IDs internally.
	 */
	private static function slug_to_id( $slug, $settings ) {
		if ( 'all' === $slug ) {
			return 'all';
		}
		foreach ( $settings['terms'] as $term_id ) {
			$term = get_term( $term_id, $settings['taxonomy'] );
			if ( $term && ! is_wp_error( $term ) && $term->slug === $slug ) {
				return (string) $term_id;
			}
		}
		return 'all';
	}

	/**
	 * Check the current request for `?wbcf_tab_{filter_id}=<slug>` -- lets
	 * a shared/bookmarked link (or the browser back/forward buttons via
	 * wbcf-public.js) land on the right tab without a client-side flash.
	 * Returns null if there's no valid override, so the caller falls
	 * back to the filter's own configured default.
	 */
	private static function tab_from_request( $filter_id, $settings ) {
		$param = 'wbcf_tab_' . $filter_id;
		if ( empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}
		$slug = sanitize_title( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'all' === $slug ) {
			return ( 'yes' === $settings['show_all_tab'] ) ? 'all' : null;
		}
		return in_array( $slug, self::valid_tab_slugs( $settings ), true ) ? $slug : null;
	}

	/**
	 * Return the slugs of every term selected for this filter, so the
	 * AJAX handler can validate an incoming slug without ever trusting
	 * client input directly.
	 */
	public static function valid_tab_slugs( $settings ) {
		$slugs = array();
		foreach ( $settings['terms'] as $term_id ) {
			$term = get_term( $term_id, $settings['taxonomy'] );
			if ( $term && ! is_wp_error( $term ) ) {
				$slugs[] = $term->slug;
			}
		}
		return $slugs;
	}

	/**
	 * Work out which tab should be active on first load, falling back
	 * sensibly if the saved default is no longer valid (e.g. a term was
	 * later removed from the selection).
	 */
	private static function resolve_active_tab( $settings ) {
		$default = $settings['default_tab'];

		if ( 'all' === $default && 'yes' === $settings['show_all_tab'] ) {
			return 'all';
		}
		if ( 'all' !== $default && in_array( (int) $default, $settings['terms'], true ) ) {
			return (string) (int) $default;
		}
		if ( 'yes' === $settings['show_all_tab'] ) {
			return 'all';
		}
		return (string) $settings['terms'][0];
	}

	/**
	 * Just the results grid for one tab -- reused for the initial
	 * server render AND every AJAX tab switch.
	 *
	 * @param string $tab       Either 'all' or a term slug.
	 * @param array  $overrides Optional block-level overrides, see render_filter().
	 */
	public static function render_results( $filter_id, $tab, $overrides = array() ) {
		$settings = self::get_settings( $filter_id );
		if ( ! $settings ) {
			return '';
		}
		$settings = self::apply_overrides( $settings, $overrides );

		return self::render_results_for_settings( $filter_id, $settings, $tab, $overrides );
	}

	/**
	 * Same as render_results() but takes already-fetched, already
	 * overridden settings, so callers that already have them (namely
	 * render_filter(), for the initial panel) don't pay for a second
	 * postmeta round trip. This is also where the transient cache lives.
	 *
	 * Returns the grid (page 1) plus a Load More button or Infinite
	 * Scroll sentinel when the filter is configured for one and there's
	 * a page 2 to fetch.
	 */
	private static function render_results_for_settings( $filter_id, $settings, $tab, $overrides = array() ) {
		$cache_key = WBCF_Cache::build_key( $filter_id, $tab, $overrides, 1 );
		$cached    = WBCF_Cache::get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		list( $query, $has_more ) = self::run_paged_query( $filter_id, $tab, $settings, 1 );

		if ( $query->have_posts() ) {
			$html = '<div class="wbcf-grid">' . self::render_cards( $query, $filter_id, $settings, 1 ) . '</div>';
			$html .= self::render_pagination_control( $filter_id, $settings, $has_more, 2 );
		} else {
			$html = '<p class="wbcf-no-results">' . esc_html__( 'No posts found.', 'webcasata-content-filter' ) . '</p>';
		}

		wp_reset_postdata();

		/**
		 * Filter the fully-rendered results HTML for a tab.
		 */
		$html = apply_filters( 'wbcf_results_html', $html, $filter_id, $tab );

		self::maybe_cache( $cache_key, $html, $filter_id, $tab );

		return $html;
	}

	/**
	 * One additional page of cards for Load More / Infinite Scroll --
	 * just the `<article>` markup to append, plus whether a further page
	 * still exists.
	 *
	 * @return array { html: string, has_more: bool }
	 */
	public static function render_more( $filter_id, $tab, $page, $overrides = array() ) {
		$settings = self::get_settings( $filter_id );
		if ( ! $settings ) {
			return array(
				'html'     => '',
				'has_more' => false,
			);
		}
		$settings = self::apply_overrides( $settings, $overrides );
		$page     = max( 2, absint( $page ) );

		$cache_key = WBCF_Cache::build_key( $filter_id, $tab, $overrides, $page );
		$cached    = WBCF_Cache::get( $cache_key );
		if ( is_array( $cached ) && isset( $cached['html'] ) ) {
			return $cached;
		}

		list( $query, $has_more ) = self::run_paged_query( $filter_id, $tab, $settings, $page );

		$html = self::render_cards( $query, $filter_id, $settings, $page );
		wp_reset_postdata();

		$html = apply_filters( 'wbcf_results_html', $html, $filter_id, $tab );

		$result = array(
			'html'     => $html,
			'has_more' => $has_more,
		);

		self::maybe_cache( $cache_key, $result, $filter_id, $tab );

		return $result;
	}

	/**
	 * Run the WP_Query for one page of a tab. Fetches one extra post to
	 * know whether a further page exists, without needing
	 * SQL_CALC_FOUND_ROWS (see the no_found_rows note below) -- the
	 * standard "fetch N+1, trim to N" trick.
	 *
	 * @return array { 0: WP_Query, 1: bool $has_more }
	 */
	private static function run_paged_query( $filter_id, $tab, $settings, $page ) {
		$tax_query = array();
		if ( 'all' === $tab ) {
			if ( ! empty( $settings['terms'] ) ) {
				$tax_query[] = array(
					'taxonomy' => $settings['taxonomy'],
					'field'    => 'term_id',
					'terms'    => $settings['terms'],
				);
			}
		} else {
			$tax_query[] = array(
				'taxonomy' => $settings['taxonomy'],
				'field'    => 'slug',
				'terms'    => array( sanitize_title( $tab ) ),
			);
		}

		$per_page = max( 1, (int) $settings['posts_per_page'] );

		$query_args = array(
			'post_type'           => $settings['post_type'],
			'posts_per_page'      => $per_page + 1, // the extra one tells us whether another page exists
			'offset'              => ( $page - 1 ) * $per_page,
			'post_status'         => 'publish',
			'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'ignore_sticky_posts' => true,
			// 'offset' makes SQL_CALC_FOUND_ROWS pointless here anyway -- skip it.
			'no_found_rows'       => true,
		);

		/**
		 * Filter the WP_Query args before a filter's results are fetched.
		 * This is the main extension point for Pro's Advanced Query Builder.
		 * Note: if you override 'posts_per_page' here, has_more detection
		 * still relies on one extra post being fetched.
		 */
		$query_args = apply_filters( 'wbcf_query_args', $query_args, $filter_id, $tab );

		$query = new WP_Query( $query_args );

		$has_more = count( $query->posts ) > $per_page;
		if ( $has_more ) {
			array_pop( $query->posts );
			$query->post_count = count( $query->posts );
		}

		return array( $query, $has_more );
	}

	/**
	 * Render just the `<article>` cards for a query (no wrapping .wbcf-grid
	 * div) -- shared by the full-grid render and Load More/Infinite
	 * Scroll appends, which insert directly into an existing grid.
	 *
	 * @param array $settings Already-fetched, already-overridden settings
	 *              (must come from the caller -- re-fetching here would
	 *              silently drop a block's "Override Layout" template).
	 * @param int   $page     Which page this query represents. Available
	 *              to template files as $page (PHP's include() shares
	 *              the calling scope) -- e.g. Pro's Hero template uses
	 *              `1 === $page && 0 === $query->current_post` to know
	 *              whether it's rendering the very first post overall,
	 *              not just the first post of a later Load More page.
	 */
	private static function render_cards( $query, $filter_id, $settings, $page = 1 ) {
		$template_path = self::locate_template( $settings['template'] );

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();

			do_action( 'wbcf_before_card', get_post(), $filter_id );

			if ( $template_path ) {
				include $template_path;
			}

			do_action( 'wbcf_after_card', get_post(), $filter_id );
		}
		return ob_get_clean();
	}

	/**
	 * The Load More button or Infinite Scroll sentinel appended after
	 * page 1's grid. wbcf-public.js reads data-next-page/data-tab off
	 * this element to fetch subsequent pages and knows to remove it once
	 * has_more comes back false.
	 */
	private static function render_pagination_control( $filter_id, $settings, $has_more, $next_page ) {
		if ( ! $has_more || 'none' === $settings['pagination'] ) {
			return '';
		}

		if ( 'load_more' === $settings['pagination'] ) {
			return sprintf(
				'<div class="wbcf-loadmore-wrap"><button type="button" class="wbcf-load-more" data-next-page="%d">%s</button></div>',
				(int) $next_page,
				esc_html__( 'Load More', 'webcasata-content-filter' )
			);
		}

		// infinite_scroll
		return sprintf(
			'<div class="wbcf-infinite-sentinel" data-next-page="%d" aria-hidden="true"></div>',
			(int) $next_page
		);
	}

	/**
	 * Shared cache-write path for both render_results_for_settings() and
	 * render_more(), so the wbcf_cache_ttl filter behaves identically for
	 * page 1 and every subsequent page.
	 */
	private static function maybe_cache( $cache_key, $value, $filter_id, $tab ) {
		/**
		 * Filter how long a tab's rendered HTML stays cached, in seconds.
		 * Return 0 (or less) to disable caching for a given filter/tab --
		 * useful for Pro query rules whose results depend on something
		 * that isn't reflected in a post save (e.g. view counts).
		 */
		$ttl = (int) apply_filters( 'wbcf_cache_ttl', 12 * HOUR_IN_SECONDS, $filter_id, $tab );
		if ( $ttl > 0 ) {
			WBCF_Cache::set( $cache_key, $value, $ttl );
		}
	}

	/**
	 * WooCommerce compatibility: echoes a product's price + the correct
	 * Add to Cart button (WooCommerce itself decides simple/variable/
	 * external/out-of-stock markup) right inside a card template. No-op
	 * for every other post type, and no-op entirely when WooCommerce
	 * isn't active, so calling this from a template is always safe.
	 *
	 * Relies on the WooCommerce product global that core WooCommerce
	 * itself populates on its own 'the_post' hook during the WP_Query
	 * loop in render_cards() -- the same mechanism every WooCommerce
	 * theme template relies on -- with a wc_get_product() fallback in
	 * case something cleared that global between posts.
	 *
	 * wc-add-to-cart.js (which WooCommerce ships and this plugin
	 * enqueues alongside its own public assets when WooCommerce is
	 * active, see WBCF_Assets::enqueue()) listens for clicks on
	 * .add_to_cart_button anywhere on the page, so the AJAX add-to-cart
	 * behavior works here with no extra wiring on this plugin's side.
	 */
	public static function maybe_render_woocommerce_meta() {
		if ( 'product' !== get_post_type() || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) || (int) $product->get_id() !== get_the_ID() ) {
			$product = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the WooCommerce loop global exactly the way WooCommerce's own `the_post` handler populates it, so core WooCommerce template functions below (which read this global, not a parameter) behave the same as they do in a native WooCommerce loop.
		}
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		/**
		 * Filter whether the price + Add to Cart block renders for a given
		 * product card. Return false to hide it for specific products
		 * (e.g. Pro's Advanced Query Builder / a custom condition) without
		 * having to override the whole template.
		 */
		if ( ! apply_filters( 'wbcf_show_woocommerce_meta', true, $product->get_id() ) ) {
			return;
		}
		?>
		<div class="wbcf-card__wc">
			<span class="wbcf-card__price"><?php echo wp_kses( $product->get_price_html(), self::price_html_allowed_tags() ); ?></span>
			<div class="wbcf-card__addtocart">
				<?php woocommerce_template_loop_add_to_cart(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Allowed tags for escaping WC_Product::get_price_html(). Based on
	 * the standard 'post' whitelist plus <bdi>, which WooCommerce's own
	 * price markup relies on to keep currency symbols correctly ordered
	 * in RTL languages -- wp_kses_post() alone would silently strip it.
	 */
	private static function price_html_allowed_tags() {
		return array_merge(
			wp_kses_allowed_html( 'post' ),
			array( 'bdi' => array() )
		);
	}

	/**
	 * The list of templates shown in the admin's Card Template dropdown.
	 * Keys are template slugs (resolved by locate_template() / the
	 * wbcf_locate_template filter), values are display labels. Pro's
	 * premium templates add themselves here rather than needing this
	 * file edited.
	 */
	public static function get_template_choices() {
		$choices = array(
			'card-classic'  => __( 'Classic', 'webcasata-content-filter' ),
			'card-cards'    => __( 'Cards', 'webcasata-content-filter' ),
			'card-magazine' => __( 'Magazine', 'webcasata-content-filter' ),
			'card-minimal'  => __( 'Minimal', 'webcasata-content-filter' ),
			'card-news'     => __( 'News', 'webcasata-content-filter' ),
		);

		return apply_filters( 'wbcf_template_choices', $choices );
	}

	/**
	 * Template slugs where the Styling system's "Columns" setting is
	 * meaningless -- these templates always render one full-width entry
	 * per row (News here; Pro's Timeline and Split are the same shape).
	 * The admin JS hides the Columns field when one of these is selected
	 * rather than leaving a control that visibly does nothing.
	 */
	public static function get_templates_without_columns() {
		$list = array( 'card-news' );
		return apply_filters( 'wbcf_templates_without_columns', $list );
	}

	/**
	 * Resolve a template slug (e.g. "card-classic") to a file path.
	 * Checks the active theme first (theme override support) then falls
	 * back to the plugin's own /templates directory.
	 */
	public static function locate_template( $slug ) {
		$slug = sanitize_file_name( $slug );
		$file = $slug . '.php';

		$theme_path = locate_template( 'webcasata-content-filter/' . $file );
		if ( $theme_path ) {
			return $theme_path;
		}

		$plugin_path = WBCF_PLUGIN_DIR . 'templates/' . $file;
		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		/**
		 * Filter the resolved template path when a slug isn't found in the
		 * theme override folder or this plugin's own /templates directory.
		 * This is how Pro's premium templates (and any third-party
		 * templates) plug in, without ever needing files copied into this
		 * plugin's folder -- which would just get wiped on the next update.
		 *
		 * Return a full file path if you can resolve $slug, or leave the
		 * passed-in $found value (false) alone otherwise.
		 */
		$found = apply_filters( 'wbcf_locate_template', false, $slug, $file );
		if ( $found && file_exists( $found ) ) {
			return $found;
		}

		// Fall back to the Classic template so a bad/missing template
		// slug never produces a blank grid.
		$fallback = WBCF_PLUGIN_DIR . 'templates/card-classic.php';
		return file_exists( $fallback ) ? $fallback : false;
	}
}
