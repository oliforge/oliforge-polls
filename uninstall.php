<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$oliforge_polls_delete_data = get_option( 'oliforge_polls_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $oliforge_polls_delete_data ) {
    return;
}

$oliforge_polls_post_ids = get_posts(
    array(
        'post_type'      => 'oliforge-polls',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    )
);

foreach ( $oliforge_polls_post_ids as $oliforge_polls_post_id ) {
    wp_delete_post( $oliforge_polls_post_id, true );
}

delete_option( 'widget_oliforge_polls_widget' );
delete_option( 'oliforge_polls_delete_data_on_uninstall' );

global $wpdb;

$oliforge_polls_tables = array(
    $wpdb->prefix . 'oliforge_polls_votes',
    $wpdb->prefix . 'oliforge_polls_vote_counts',
);

foreach ( $oliforge_polls_tables as $oliforge_polls_table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required during uninstall to remove plugin-owned custom tables when the admin enabled data deletion.
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $oliforge_polls_table ) );
}

delete_option( 'oliforge_polls_db_version' );
