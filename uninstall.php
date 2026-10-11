<?php
// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Off by default -- unless the person explicitly opted in via
// Content Filter -> Settings, deleting the plugin must never delete
// their filters. Simply returning here leaves every wbcf_filter post,
// its meta, and this very option untouched, so reinstalling the plugin
// later brings everything (including this preference) right back.
if ( 'yes' !== get_option( 'wbcf_delete_data_on_uninstall', 'no' ) ) {
	return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- uninstall.php is always include()'d by WordPress core's own uninstall handling (never loaded standalone), so every variable declared here is scoped to that internal call frame in actual execution, not the true PHP global scope.
$filters = get_posts(
	array(
		'post_type'      => 'wbcf_filter',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $filters as $filter_id ) {
	wp_delete_post( $filter_id, true ); // true = skip trash, delete meta too.
}

delete_option( 'wbcf_cache_version' );
delete_option( 'wbcf_delete_data_on_uninstall' );
