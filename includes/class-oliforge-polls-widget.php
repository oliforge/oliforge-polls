<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'oliforge_polls_widget',
            __( 'OliForge Polls', 'oliforge-polls' ),
            array( 'description' => __( 'Display a poll using a shortcode.', 'oliforge-polls' ) )
        );
    }

    /**
     * @param array $args
     * @param array $instance
     */
    public function widget( $args, $instance ) {
        $title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $poll_id = ! empty( $instance['poll_id'] ) ? absint( $instance['poll_id'] ) : 0;

        if ( ! $poll_id ) {
            return;
        }

        /** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
        $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

        echo wp_kses_post( $args['before_widget'] );

        if ( ! empty( $title ) ) {
            echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
        }

        echo wp_kses_post( OliForge_Polls_Shortcode::render_shortcode( array( 'id' => absint( $poll_id ) ) ) );

        echo wp_kses_post( $args['after_widget'] );
    }

    /**
     * @param array $instance
     * @return string|void
     */
    public function form( $instance ) {
        $title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $poll_id = ! empty( $instance['poll_id'] ) ? absint( $instance['poll_id'] ) : 0;

        $polls = get_posts( array(
            'post_type'      => OliForge_Polls_CPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'oliforge-polls' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'poll_id' ) ); ?>"><?php esc_html_e( 'Select Poll:', 'oliforge-polls' ); ?></label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'poll_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'poll_id' ) ); ?>">
                <option value="0"><?php esc_html_e( '— Select —', 'oliforge-polls' ); ?></option>
                <?php foreach ( $polls as $poll ) : ?>
                    <option value="<?php echo esc_attr( $poll->ID ); ?>" <?php selected( $poll_id, $poll->ID ); ?>>
                        <?php echo esc_html( $poll->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    /**
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title']   = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['poll_id'] = ( ! empty( $new_instance['poll_id'] ) ) ? absint( $new_instance['poll_id'] ) : 0;

        return $instance;
    }

    public static function init() {
        add_action( 'widgets_init', function() {
            register_widget( 'OliForge_Polls_Widget' );
        } );
    }
}
