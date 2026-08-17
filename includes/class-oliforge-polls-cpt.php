<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_CPT {
    const POST_TYPE = 'oliforge-polls';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'init', array( __CLASS__, 'register_meta' ) );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ), 100 );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 100, 2 );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
    }

    public static function sortable_columns( $columns ) {
        $columns['oliforge_polls_total']      = 'oliforge_polls_total';
        $columns['oliforge_polls_start_date'] = 'oliforge_polls_start_date';
        $columns['oliforge_polls_end_date']   = 'oliforge_polls_end_date';
        return $columns;
    }

    public static function register_post_type() {
        $labels = array(
            'name'               => __( 'Polls', 'oliforge-polls' ),
            'singular_name'      => __( 'Poll', 'oliforge-polls' ),
            'add_new'            => __( 'Add Poll', 'oliforge-polls' ),
            'add_new_item'       => __( 'Add New Poll', 'oliforge-polls' ),
            'edit_item'          => __( 'Edit Poll', 'oliforge-polls' ),
            'new_item'           => __( 'New Poll', 'oliforge-polls' ),
            'view_item'          => __( 'View Poll', 'oliforge-polls' ),
            'search_items'       => __( 'Search Polls', 'oliforge-polls' ),
            'not_found'          => __( 'No polls found', 'oliforge-polls' ),
            'not_found_in_trash' => __( 'No polls found in Trash', 'oliforge-polls' ),
            'all_items'          => __( 'All Polls', 'oliforge-polls' ),
            'menu_name'          => __( 'Polls', 'oliforge-polls' ),
        );

        register_post_type(
            self::POST_TYPE,
            array(
                'labels'            => $labels,
                'public'            => false,
                'show_ui'           => true,
                'show_in_menu'      => true,
                'show_in_rest'      => true,
                'supports'          => array( 'title' ),
                'menu_position'     => 26,
                'menu_icon'         => 'dashicons-chart-bar',
                'has_archive'       => false,
                'rewrite'           => false,
                'map_meta_cap'      => true,
            )
        );
    }

    public static function register_meta() {
        register_post_meta(
            self::POST_TYPE,
            '_oliforge_polls_data',
            array(
                'single'            => true,
                'type'              => 'object',
                'show_in_rest'      => array(
                    'schema' => array(
                        'type'       => 'object',
                        'properties' => array(),
                    ),
                ),
                'sanitize_callback' => array( 'OliForge_Polls_Util', 'sanitize_poll_data' ),
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    public static function columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['oliforge_polls_question']      = __( 'Question', 'oliforge-polls' );
                $new['oliforge_polls_answers_count'] = __( 'Polls', 'oliforge-polls' );
                $new['oliforge_polls_total']         = __( 'Votes', 'oliforge-polls' );
                $new['oliforge_polls_shortcode']     = __( 'Shortcode', 'oliforge-polls' );
                $new['oliforge_polls_start_date']    = __( 'Start date', 'oliforge-polls' );
                $new['oliforge_polls_end_date']      = __( 'End date', 'oliforge-polls' );
            }
        }
        return $new;
    }

    public static function column_content( $column, $post_id ) {
        $data = OliForge_Polls_Util::get_poll_data( $post_id );
        switch ( $column ) {
            case 'oliforge_polls_question':
                $question = ! empty( $data['question'] ) ? $data['question'] : '';
                if ( empty( $question ) ) {
                    $post = get_post( $post_id );
                    $question = $post ? $post->post_title : '';
                }
                echo esc_html( $question ?: '—' );
                break;
            case 'oliforge_polls_answers_count':
                echo esc_html( (string) ( is_array( $data['answers'] ) ? count( $data['answers'] ) : 0 ) );
                break;
            case 'oliforge_polls_total':
                $results = OliForge_Polls_Util::get_results( $post_id, $data );
                $votes_per_answer = wp_list_pluck( $results['items'], 'votes' );
                $total = absint( $results['total'] );
                echo esc_html( ! empty( $votes_per_answer ) ? implode( ' / ', array_map( 'absint', $votes_per_answer ) ) . ' : ' . $total : (string) $total );
                break;
            case 'oliforge_polls_shortcode':
                $shortcode = '[oliforge-polls id="' . $data['shortcode_id'] . '"]';
                echo '<code>' . esc_html( $shortcode ) . '</code> ';
                echo OliForge_Polls_Admin::render_copy_shortcode_button( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper.
                break;
            case 'oliforge_polls_start_date':
                echo esc_html( ! empty( $data['start_date'] ) ? $data['start_date'] : '—' );
                break;
            case 'oliforge_polls_end_date':
                echo esc_html( ! empty( $data['end_date'] ) ? $data['end_date'] : '—' );
                break;
        }
    }
}
