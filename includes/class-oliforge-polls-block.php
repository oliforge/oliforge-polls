<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Block {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_block' ) );
    }

    public static function register_block() {
        $asset = OliForge_Polls_Util::get_build_asset( 'block-editor' );
        $deps  = array_unique( array_merge( $asset['dependencies'], array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch' ) ) );
        wp_register_script(
            'oliforge-polls-block-editor',
            OliForge_Polls_Util::get_build_url( 'block-editor.js' ),
            $deps,
            $asset['version'],
            true
        );

        wp_localize_script(
            'oliforge-polls-block-editor',
            'OliForgePollsBlock',
            array(
                'restPath' => '/wp/v2/' . OliForge_Polls_CPT::POST_TYPE,
                'debug'    => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
            )
        );

        register_block_type(
            OLIFORGE_POLLS_PATH . 'blocks/vote-embed',
            array(
                'editor_script'   => 'oliforge-polls-block-editor',
                'render_callback' => array( __CLASS__, 'render_block' ),
            )
        );
    }

    public static function render_block( $attributes ) {
        $post_id = absint( $attributes['pollId'] ?? 0 );
        if ( ! $post_id ) {
            return '<div class="oliforge-polls-block-placeholder">' . esc_html__( 'Select a poll in block settings.', 'oliforge-polls' ) . '</div>';
        }

        if ( ! OliForge_Polls_Util::can_publicly_access_poll( $post_id ) ) {
            return '';
        }

        return OliForge_Polls_Shortcode::render_poll( $post_id );
    }
}
