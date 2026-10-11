<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Everything under the "Webcasata Content Filter" admin menu:
 * - the list screen (all filters)
 * - the add/edit screen (the form users actually fill in)
 * - saving that form into the wbcf_filter CPT + postmeta
 *
 * Meta schema (all keys prefixed _wbcf_ to stay out of other plugins' way):
 *   _wbcf_post_type      string   e.g. 'doctor'
 *   _wbcf_taxonomy       string   ONE taxonomy slug, scoped to taxonomies registered on the post type
 *   _wbcf_terms          array    term IDs from that taxonomy -- EACH ONE BECOMES A TAB
 *   _wbcf_posts_per_page int
 *   _wbcf_show_all_tab   'yes'|'no'   'All' tab = every selected term combined
 *   _wbcf_default_tab    string   term ID, or 'all'
 *   _wbcf_template       string   e.g. 'card-classic'  (Phase 2 adds more)
 *   _wbcf_pagination     string   'none' | 'load_more' | 'infinite_scroll'
 *   _wbcf_styles         array    reserved for Phase 3 styling options
 *
 * Selection chain in the admin UI: Post Type -> (AJAX) Taxonomy options
 * scoped to that post type -> Taxonomy -> (AJAX) Term checkboxes for that
 * taxonomy. Each checked term becomes a tab on the front end.
 */
class WBCF_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wbcf_save_filter', array( $this, 'save_filter' ) );
		add_action( 'admin_post_wbcf_delete_filter', array( $this, 'delete_filter' ) );
		add_action( 'admin_post_wbcf_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_wbcf_get_taxonomies', array( $this, 'ajax_get_taxonomies' ) );
		add_action( 'wp_ajax_wbcf_get_terms', array( $this, 'ajax_get_terms' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_menu() {
		$cap = 'manage_options';

		add_menu_page(
			__( 'Webcasata Content Filter', 'webcasata-content-filter' ),
			__( 'Content Filter', 'webcasata-content-filter' ),
			$cap,
			'wbcf-filters',
			array( $this, 'render_list_screen' ),
			'dashicons-filter',
			26
		);

		add_submenu_page(
			'wbcf-filters',
			__( 'All Filters', 'webcasata-content-filter' ),
			__( 'All Filters', 'webcasata-content-filter' ),
			$cap,
			'wbcf-filters',
			array( $this, 'render_list_screen' )
		);

		add_submenu_page(
			'wbcf-filters',
			__( 'Add New Filter', 'webcasata-content-filter' ),
			__( 'Add New', 'webcasata-content-filter' ),
			$cap,
			'wbcf-add-filter',
			array( $this, 'render_edit_screen' )
		);

		add_submenu_page(
			'wbcf-filters',
			__( 'Settings', 'webcasata-content-filter' ),
			__( 'Settings', 'webcasata-content-filter' ),
			$cap,
			'wbcf-settings',
			array( $this, 'render_settings_screen' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'content-filter_page_wbcf-add-filter', 'content-filter_page_wbcf-settings', 'toplevel_page_wbcf-filters' ), true ) ) {
			return;
		}

		$suffix = wbcf_asset_suffix();

		wp_enqueue_style( 'wbcf-admin', WBCF_PLUGIN_URL . 'assets/css/wbcf-admin' . $suffix . '.css', array(), WBCF_VERSION );

		if ( isset( $_GET['page'] ) && 'wbcf-add-filter' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_script( 'wbcf-admin', WBCF_PLUGIN_URL . 'assets/js/wbcf-admin' . $suffix . '.js', array( 'wp-color-picker' ), WBCF_VERSION, true );
			wp_localize_script(
				'wbcf-admin',
				'wbcfAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wbcf_admin_nonce' ),
					'i18n'    => array(
						'loadingTax'   => __( 'Loading taxonomies…', 'webcasata-content-filter' ),
						'loadingTerms' => __( 'Loading terms…', 'webcasata-content-filter' ),
						'noneTax'      => __( 'This post type has no public taxonomies.', 'webcasata-content-filter' ),
						'noneTerms'    => __( 'No terms found for this taxonomy.', 'webcasata-content-filter' ),
						'error'        => __( 'Something went wrong loading this list.', 'webcasata-content-filter' ),
						'all'          => __( 'All', 'webcasata-content-filter' ),
					),
				)
			);
		}
	}

	/* ---------------------------------------------------------------
	 * List screen
	 * ------------------------------------------------------------- */

	public function render_list_screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webcasata-content-filter' ) );
		}

		$filters = get_posts(
			array(
				'post_type'      => WBCF_POST_TYPE,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		include WBCF_PLUGIN_DIR . 'admin/view-list.php';
	}

	/* ---------------------------------------------------------------
	 * Settings screen
	 * ------------------------------------------------------------- */

	public function render_settings_screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webcasata-content-filter' ) );
		}

		$delete_data_on_uninstall = 'yes' === get_option( 'wbcf_delete_data_on_uninstall', 'no' );

		include WBCF_PLUGIN_DIR . 'admin/view-settings.php';
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'webcasata-content-filter' ) );
		}

		check_admin_referer( 'wbcf_save_settings' );

		$delete_data = ( isset( $_POST['wbcf_delete_data_on_uninstall'] ) && 'yes' === $_POST['wbcf_delete_data_on_uninstall'] ) ? 'yes' : 'no';
		update_option( 'wbcf_delete_data_on_uninstall', $delete_data, false );

		wp_safe_redirect( admin_url( 'admin.php?page=wbcf-settings&saved=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * Add / edit screen
	 * ------------------------------------------------------------- */

	public function render_edit_screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webcasata-content-filter' ) );
		}

		$filter_id = isset( $_GET['filter_id'] ) ? absint( $_GET['filter_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$data = array(
			'ID'              => $filter_id,
			'title'           => '',
			'post_type'       => 'post',
			'taxonomy'        => '',
			'terms'           => array(),
			'posts_per_page'  => 6,
			'show_all_tab'    => 'yes',
			'default_tab'     => 'all',
			'template'        => 'card-classic',
			'pagination'      => 'none',
			'styles'          => WBCF_Render::get_style_defaults(),
			'display'         => WBCF_Render::get_display_defaults(),
			'readmore_text'   => '',
		);

		if ( $filter_id ) {
			$post = get_post( $filter_id );
			if ( $post && WBCF_POST_TYPE === $post->post_type ) {
				$data['title']          = $post->post_title;
				$data['post_type']      = get_post_meta( $filter_id, '_wbcf_post_type', true ) ?: 'post';
				$data['taxonomy']       = get_post_meta( $filter_id, '_wbcf_taxonomy', true ) ?: '';
				$data['terms']          = array_map( 'absint', (array) get_post_meta( $filter_id, '_wbcf_terms', true ) );
				$data['posts_per_page'] = get_post_meta( $filter_id, '_wbcf_posts_per_page', true ) ?: 6;
				$data['show_all_tab']   = get_post_meta( $filter_id, '_wbcf_show_all_tab', true ) ?: 'yes';
				$data['default_tab']    = get_post_meta( $filter_id, '_wbcf_default_tab', true ) ?: 'all';
				$data['template']       = get_post_meta( $filter_id, '_wbcf_template', true ) ?: 'card-classic';
				$data['pagination']     = get_post_meta( $filter_id, '_wbcf_pagination', true ) ?: 'none';
				$saved_styles           = get_post_meta( $filter_id, '_wbcf_styles', true );
				$data['styles']         = wp_parse_args( is_array( $saved_styles ) ? $saved_styles : array(), WBCF_Render::get_style_defaults() );
				$saved_display          = get_post_meta( $filter_id, '_wbcf_display', true );
				$data['display']        = wp_parse_args( is_array( $saved_display ) ? $saved_display : array(), WBCF_Render::get_display_defaults() );
				$data['readmore_text']  = get_post_meta( $filter_id, '_wbcf_readmore_text', true ) ?: '';
			}
		}

		$post_types = $this->usable_post_types();

		// Always keep an already-saved filter's own post type selectable,
		// even if it no longer has a public taxonomy (e.g. one was later
		// unregistered) -- otherwise re-opening that filter would silently
		// drop its current selection from the picker.
		if ( $data['post_type'] && ! isset( $post_types[ $data['post_type'] ] ) && post_type_exists( $data['post_type'] ) ) {
			$existing_pt = get_post_type_object( $data['post_type'] );
			if ( $existing_pt ) {
				$post_types[ $data['post_type'] ] = $existing_pt;
			}
		}

		include WBCF_PLUGIN_DIR . 'admin/view-edit.php';
	}

	/* ---------------------------------------------------------------
	 * Save / delete
	 * ------------------------------------------------------------- */

	public function save_filter() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'webcasata-content-filter' ) );
		}

		check_admin_referer( 'wbcf_save_filter' );

		$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;
		$title     = isset( $_POST['wbcf_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wbcf_title'] ) ) : '';

		$postarr = array(
			'post_type'   => WBCF_POST_TYPE,
			'post_title'  => $title ? $title : __( 'Untitled Filter', 'webcasata-content-filter' ),
			'post_status' => 'publish',
		);

		if ( $filter_id ) {
			$postarr['ID'] = $filter_id;
			wp_update_post( $postarr );
		} else {
			$filter_id = wp_insert_post( $postarr );
		}

		if ( is_wp_error( $filter_id ) || ! $filter_id ) {
			wp_die( esc_html__( 'Could not save filter.', 'webcasata-content-filter' ) );
		}

		$post_type_val = isset( $_POST['wbcf_post_type'] ) ? sanitize_key( $_POST['wbcf_post_type'] ) : 'post';

		$this->apply_settings(
			$filter_id,
			array(
				'post_type'      => $post_type_val,
				'taxonomy'       => isset( $_POST['wbcf_taxonomy'] ) ? sanitize_key( $_POST['wbcf_taxonomy'] ) : '',
				'terms'          => isset( $_POST['wbcf_terms'] ) ? array_map( 'absint', (array) $_POST['wbcf_terms'] ) : array(),
				'posts_per_page' => isset( $_POST['wbcf_posts_per_page'] ) ? absint( $_POST['wbcf_posts_per_page'] ) : 6,
				'show_all_tab'   => isset( $_POST['wbcf_show_all_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['wbcf_show_all_tab'] ) ) : 'no',
				'default_tab'    => isset( $_POST['wbcf_default_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['wbcf_default_tab'] ) ) : 'all',
				'template'       => isset( $_POST['wbcf_template'] ) ? sanitize_key( $_POST['wbcf_template'] ) : 'card-classic',
				'pagination'     => isset( $_POST['wbcf_pagination'] ) ? sanitize_key( $_POST['wbcf_pagination'] ) : 'none',
				'styles'         => ( isset( $_POST['wbcf_style'] ) && is_array( $_POST['wbcf_style'] ) ) ? map_deep( wp_unslash( $_POST['wbcf_style'] ), 'sanitize_text_field' ) : array(),
				'display'        => ( isset( $_POST['wbcf_display'] ) && is_array( $_POST['wbcf_display'] ) ) ? map_deep( wp_unslash( $_POST['wbcf_display'] ), 'sanitize_text_field' ) : array(),
				'readmore_text'  => isset( $_POST['wbcf_readmore_text'] ) ? sanitize_text_field( wp_unslash( $_POST['wbcf_readmore_text'] ) ) : '',
			)
		);

		/**
		 * Fires after a filter's settings have been saved.
		 * Pro add-ons (Advanced Query Builder, Visual Layout Builder, etc.)
		 * hook in here -- deliberately NOT fired by apply_settings() itself,
		 * since that method is also used by Pro's Import feature with a
		 * plain settings array that has no real $_POST behind it (firing
		 * this there would make Pro's own save-handlers overwrite the very
		 * data Import just set, with empty defaults).
		 *
		 * Only $filter_id is passed -- not $_POST or any other request
		 * data. A consumer that needs its own custom form field reads and
		 * sanitizes it directly from $_POST inside its own callback (the
		 * same pattern core's own save_post hook uses), rather than
		 * receiving raw request data as a hook argument. See
		 * WBCFP_Layout_Builder::save_layout() and
		 * WBCFP_Query_Builder::save_rules() for the pattern, and
		 * DEVELOPER.md for the documented contract third-party code
		 * should also follow.
		 */
		do_action( 'wbcf_after_save_filter', $filter_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wbcf-add-filter',
					'filter_id' => $filter_id,
					'saved'     => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Whitelist-sanitize and save a filter's core settings from a plain
	 * array -- the same validation rules save_filter() applies from
	 * $_POST, factored out so Pro's Import feature (which has a plain
	 * array from a decoded JSON export, not a real form submission) gets
	 * identical guarantees without duplicating this logic.
	 *
	 * Deliberately does NOT fire 'wbcf_after_save_filter' -- see the
	 * comment on that do_action() call in save_filter() for why.
	 *
	 * @param int   $filter_id Existing wbcf_filter post ID.
	 * @param array $settings  Keys: post_type, taxonomy, terms (array of
	 *                          term IDs valid on THIS site), posts_per_page,
	 *                          show_all_tab, default_tab, template,
	 *                          pagination, styles (raw array, same shape
	 *                          the Styling step submits).
	 */
	public function apply_settings( $filter_id, array $settings ) {
		$post = get_post( $filter_id );
		if ( ! $post || WBCF_POST_TYPE !== $post->post_type ) {
			return false;
		}

		$post_type_val = isset( $settings['post_type'] ) ? sanitize_key( $settings['post_type'] ) : 'post';
		if ( ! post_type_exists( $post_type_val ) ) {
			$post_type_val = 'post';
		}

		$taxonomy_val = isset( $settings['taxonomy'] ) ? sanitize_key( $settings['taxonomy'] ) : '';
		if ( $taxonomy_val && ! in_array( $taxonomy_val, get_object_taxonomies( $post_type_val ), true ) ) {
			$taxonomy_val = '';
		}

		$terms_val = isset( $settings['terms'] ) ? array_map( 'absint', (array) $settings['terms'] ) : array();
		if ( $taxonomy_val ) {
			// Defend against IDs that don't actually belong to $taxonomy_val on this site.
			$terms_val = array_values(
				array_filter(
					$terms_val,
					function ( $term_id ) use ( $taxonomy_val ) {
						$term = get_term( $term_id, $taxonomy_val );
						return $term && ! is_wp_error( $term );
					}
				)
			);
		} else {
			$terms_val = array();
		}

		$ppp_val        = isset( $settings['posts_per_page'] ) ? absint( $settings['posts_per_page'] ) : 6;
		$show_all_val   = ( isset( $settings['show_all_tab'] ) && 'yes' === $settings['show_all_tab'] ) ? 'yes' : 'no';
		$default_val    = isset( $settings['default_tab'] ) ? sanitize_text_field( $settings['default_tab'] ) : 'all';
		$template_val   = isset( $settings['template'] ) ? sanitize_key( $settings['template'] ) : 'card-classic';
		$pagination_val = isset( $settings['pagination'] ) ? sanitize_key( $settings['pagination'] ) : 'none';
		if ( ! in_array( $pagination_val, array( 'none', 'load_more', 'infinite_scroll' ), true ) ) {
			$pagination_val = 'none';
		}

		update_post_meta( $filter_id, '_wbcf_post_type', $post_type_val );
		update_post_meta( $filter_id, '_wbcf_taxonomy', $taxonomy_val );
		update_post_meta( $filter_id, '_wbcf_terms', $terms_val );
		update_post_meta( $filter_id, '_wbcf_posts_per_page', max( 1, min( 50, $ppp_val ) ) );
		update_post_meta( $filter_id, '_wbcf_show_all_tab', $show_all_val );
		update_post_meta( $filter_id, '_wbcf_default_tab', $default_val );
		update_post_meta( $filter_id, '_wbcf_template', $template_val );
		update_post_meta( $filter_id, '_wbcf_pagination', $pagination_val );
		update_post_meta( $filter_id, '_wbcf_styles', $this->sanitize_styles( isset( $settings['styles'] ) ? $settings['styles'] : array() ) );
		update_post_meta( $filter_id, '_wbcf_display', $this->sanitize_display( isset( $settings['display'] ) ? $settings['display'] : array() ) );

		$readmore_val = isset( $settings['readmore_text'] ) ? sanitize_text_field( $settings['readmore_text'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$readmore_val = mb_substr( $readmore_val, 0, 50 );
		} else {
			$readmore_val = substr( $readmore_val, 0, 50 );
		}
		update_post_meta( $filter_id, '_wbcf_readmore_text', $readmore_val );

		return true;
	}

	public function delete_filter() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'webcasata-content-filter' ) );
		}

		$filter_id = isset( $_GET['filter_id'] ) ? absint( $_GET['filter_id'] ) : 0;
		check_admin_referer( 'wbcf_delete_filter_' . $filter_id );

		if ( $filter_id ) {
			wp_delete_post( $filter_id, true );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wbcf-filters&deleted=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * AJAX: populate the Taxonomy dropdown when Post Type changes
	 * ------------------------------------------------------------- */

	public function ajax_get_taxonomies() {
		check_ajax_referer( 'wbcf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'webcasata-content-filter' ) ), 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'webcasata-content-filter' ) ) );
		}

		$out = array();
		foreach ( $this->public_taxonomies_for( $post_type ) as $tax ) {
			$out[] = array(
				'slug'  => $tax->name,
				'label' => $tax->labels->name,
			);
		}

		wp_send_json_success( array( 'taxonomies' => $out ) );
	}

	/**
	 * Public, admin-UI taxonomies registered to a post type -- skips
	 * internal/non-public ones (post_format, nav menu, link category,
	 * etc.), the same filter this plugin's own tabs need, since a tab
	 * IS a taxonomy term. Shared by ajax_get_taxonomies() (which term
	 * checkboxes a given post type offers) and usable_post_types()
	 * below (whether a post type belongs in the picker at all).
	 *
	 * @return WP_Taxonomy[]
	 */
	private function public_taxonomies_for( $post_type ) {
		$out = array();
		foreach ( get_object_taxonomies( $post_type ) as $tax_name ) {
			$tax = get_taxonomy( $tax_name );
			if ( $tax && $tax->public && $tax->show_ui ) {
				$out[] = $tax;
			}
		}
		return $out;
	}

	/**
	 * Post types worth offering in the "Select post type" step: public,
	 * has an admin UI, AND has at least one taxonomy a visitor could
	 * actually tab through -- this plugin's tabs are a taxonomy's terms,
	 * so a post type with none is a dead end here (Step 3 would just say
	 * "This post type has no public taxonomies").
	 *
	 * This is what keeps page-builder-internal post types out of the
	 * list -- e.g. Elementor registers "Template" and "Floating Element"
	 * as public, show_ui post types with no taxonomy of their own --
	 * without this plugin needing to know Elementor (or any other
	 * builder) exists at all. Any post type that genuinely has no public
	 * taxonomy is excluded the same way, on the same general rule.
	 *
	 * @return WP_Post_Type[]
	 */
	private function usable_post_types() {
		$post_types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );

		return array_filter(
			$post_types,
			function ( $pt ) {
				return ! empty( $this->public_taxonomies_for( $pt->name ) );
			}
		);
	}

	/* ---------------------------------------------------------------
	 * AJAX: populate the Terms checkbox list when Taxonomy changes
	 * ------------------------------------------------------------- */

	public function ajax_get_terms() {
		check_ajax_referer( 'wbcf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'webcasata-content-filter' ) ), 403 );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid taxonomy.', 'webcasata-content-filter' ) ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		$out = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
				);
			}
		}

		wp_send_json_success( array( 'terms' => $out ) );
	}

	/**
	 * Whitelist-sanitize the Styling step's submission into the shape
	 * WBCF_Render expects. Anything missing or invalid falls back to the
	 * "inherit default" value, never to unsanitized user input.
	 */
	private function sanitize_styles( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		$hex = function ( $key ) use ( $raw ) {
			if ( empty( $raw[ $key ] ) ) {
				return '';
			}
			$color = sanitize_hex_color( wp_unslash( $raw[ $key ] ) );
			return $color ? $color : '';
		};

		$in_list = function ( $key, $allowed, $default ) use ( $raw ) {
			$val = isset( $raw[ $key ] ) ? sanitize_key( $raw[ $key ] ) : $default;
			return in_array( $val, $allowed, true ) ? $val : $default;
		};

		$bounded_int = function ( $key, $min, $max ) use ( $raw ) {
			if ( ! isset( $raw[ $key ] ) || '' === $raw[ $key ] ) {
				return '';
			}
			return max( $min, min( $max, absint( $raw[ $key ] ) ) );
		};

		return array(
			'tab_color'        => $hex( 'tab_color' ),
			'tab_active_color' => $hex( 'tab_active_color' ),
			'tab_hover_color'  => $hex( 'tab_hover_color' ),
			'tab_radius'       => isset( $raw['tab_radius'] ) && '' !== $raw['tab_radius'] ? max( 0, min( 999, absint( $raw['tab_radius'] ) ) ) : 0,
			'card_radius'      => $bounded_int( 'card_radius', 0, 60 ),
			'card_shadow'      => $in_list( 'card_shadow', array( 'default', 'none', 'soft', 'strong' ), 'default' ),
			'card_gap'         => $bounded_int( 'card_gap', 0, 80 ),
			'card_columns'     => $in_list( 'card_columns', array( 'auto', '2', '3', '4', '5' ), 'auto' ),
			'title_size'       => $in_list( 'title_size', array( 'small', 'medium', 'large' ), 'medium' ),
			'title_color'      => $hex( 'title_color' ),
			'meta_color'       => $hex( 'meta_color' ),
		);
	}

	/**
	 * Whitelist-sanitize the Step 5 "Show/Hide" toggles. Same checkbox
	 * semantics as show_all_tab above: a checked box posts 'yes' for its
	 * key, an unchecked one is simply absent from $_POST -- so anything
	 * other than an explicit 'yes' is treated as 'no'.
	 */
	private function sanitize_display( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		$out = array();
		foreach ( array_keys( WBCF_Render::get_display_defaults() ) as $key ) {
			$out[ $key ] = ( isset( $raw[ $key ] ) && 'yes' === $raw[ $key ] ) ? 'yes' : 'no';
		}
		return $out;
	}
}
