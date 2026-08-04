<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->hooks();
        $this->load_textdomain();
    }

    private function includes() {
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-db.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-util.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-cpt.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-admin.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-shortcode.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-rest.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-block.php';
        require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-widget.php';
    }

    private function hooks() {
        register_activation_hook( OLIFORGE_POLLS_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( OLIFORGE_POLLS_FILE, array( $this, 'deactivate' ) );
        add_action( 'plugins_loaded', array( 'OliForge_Polls_DB', 'maybe_create_tables' ) );

        OliForge_Polls_CPT::init();
        OliForge_Polls_Admin::init();
        OliForge_Polls_Shortcode::init();
        OliForge_Polls_REST::init();
        OliForge_Polls_Block::init();
        OliForge_Polls_Widget::init();

        add_action( 'elementor/loaded', function() {
            add_action( 'elementor/widgets/register', function( $widgets_manager ) {
                require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-elementor-widget.php';
                $widgets_manager->register( new OliForge_Polls_Elementor_Widget() );
            } );
        } );
    }

    private function load_textdomain() {
}

    public function activate() {
        OliForge_Polls_CPT::register_post_type();
        OliForge_Polls_DB::create_tables();
        OliForge_Polls_DB::migrate_meta_votes_to_counts();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}
