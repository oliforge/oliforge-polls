<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'oliforge-polls';
    }

    public function get_title() {
        return __( 'OliForge Polls', 'oliforge-polls' );
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_style_depends() {
        return array( 'oliforge-polls-frontend' );
    }

    public function get_script_depends() {
        return array( 'oliforge-polls-frontend' );
    }

    protected function register_controls() {
        $polls = get_posts( array(
            'post_type'      => OliForge_Polls_CPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $options = array( 0 => __( '— Select poll —', 'oliforge-polls' ) );
        foreach ( $polls as $poll ) {
            $options[ $poll->ID ] = $poll->post_title;
        }

        $this->start_controls_section(
            'section_content',
            array( 'label' => __( 'Poll', 'oliforge-polls' ) )
        );

        $this->add_control(
            'poll_id',
            array(
                'label'   => __( 'Select Poll', 'oliforge-polls' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $options,
                'default' => 0,
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $poll_id = (int) $this->get_settings_for_display( 'poll_id' );

        if ( ! $poll_id || ! OliForge_Polls_Util::can_publicly_access_poll( $poll_id ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p style="padding:1em;border:1px dashed #ccc;">' . esc_html__( 'Please select a poll in the widget settings.', 'oliforge-polls' ) . '</p>';
            }
            return;
        }

        OliForge_Polls_Shortcode::register_assets();
        wp_enqueue_style( 'oliforge-polls-frontend' );
        wp_enqueue_script( 'oliforge-polls-frontend' );

        echo wp_kses_post( OliForge_Polls_Shortcode::render_poll( absint( $poll_id ) ) );
    }
}
