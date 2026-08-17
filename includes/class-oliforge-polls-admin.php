<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Admin {
    /** @var string Hook suffix of the Settings submenu page, captured from add_submenu_page(). */
    private static $settings_hook = '';

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
        add_action( 'save_post_' . OliForge_Polls_CPT::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_notices', array( __CLASS__, 'render_list_header' ) );
        add_action( 'admin_post_oliforge_polls_reset_votes', array( __CLASS__, 'handle_reset_votes' ) );
        add_action( 'admin_post_oliforge_polls_recount_votes', array( __CLASS__, 'handle_recount_votes' ) );
        add_action( 'admin_post_oliforge_polls_export_votes', array( __CLASS__, 'handle_export_votes' ) );
    }


    public static function register_settings_page() {
        self::$settings_hook = add_submenu_page(
            'edit.php?post_type=' . OliForge_Polls_CPT::POST_TYPE,
            __( 'OliForge Polls Settings', 'oliforge-polls' ),
            __( 'Settings', 'oliforge-polls' ),
            'manage_options',
            'oliforge-polls-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function register_settings() {
        register_setting(
            'oliforge_polls_settings',
            'oliforge_polls_delete_data_on_uninstall',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
                'default'           => 'no',
            )
        );

        add_settings_section(
            'oliforge_polls_data_section',
            __( 'Data retention', 'oliforge-polls' ),
            function() {
                echo '<p>' . esc_html__( 'Control what happens to OliForge Polls data when the plugin is deleted from WordPress.', 'oliforge-polls' ) . '</p>';
            },
            'oliforge-polls-settings'
        );

        add_settings_field(
            'oliforge_polls_delete_data_on_uninstall',
            __( 'Delete data on uninstall', 'oliforge-polls' ),
            array( __CLASS__, 'render_delete_data_field' ),
            'oliforge-polls-settings',
            'oliforge_polls_data_section'
        );
    }

    public static function sanitize_yes_no( $value ) {
        return 'yes' === $value ? 'yes' : 'no';
    }

    public static function render_delete_data_field() {
        $value = get_option( 'oliforge_polls_delete_data_on_uninstall', 'no' );
        ?>
        <label class="oliforge-toggle">
            <input
                class="oliforge-toggle__input"
                type="checkbox"
                name="oliforge_polls_delete_data_on_uninstall"
                value="yes"
                <?php checked( $value, 'yes' ); ?>
            />
            <span class="oliforge-toggle__track"><span class="oliforge-toggle__thumb"></span></span>
            <span class="oliforge-toggle__label"><?php esc_html_e( 'Delete all OliForge Polls polls, vote tables, counters and plugin options when uninstalling the plugin.', 'oliforge-polls' ); ?></span>
        </label>
        <p class="description">
            <?php esc_html_e( 'Disabled by default. Keep this unchecked if you want to preserve poll data when deleting/reinstalling the plugin.', 'oliforge-polls' ); ?>
        </p>
        <?php
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage OliForge Polls settings.', 'oliforge-polls' ) );
        }
        ?>
        <div class="wrap">
            <?php self::render_brand_header( __( 'Polls', 'oliforge-polls' ), __( 'Settings', 'oliforge-polls' ) ); ?>
            <form method="post" action="options.php" class="oliforge-card">
                <?php
                settings_fields( 'oliforge_polls_settings' );
                do_settings_sections( 'oliforge-polls-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the shared branded header used by both this plugin's own
     * pages and the Polls list table (via admin_notices).
     *
     * @param string $title_main   Main (dark) part of the page title.
     * @param string $title_accent Accented (orange) part of the page title.
     * @return void
     */
    private static function render_brand_header( string $title_main, string $title_accent ): void {
        ?>
        <div class="oliforge-header">
            <div class="oliforge-header__brand">
                <img class="oliforge-header__logo" src="<?php echo esc_url( OLIFORGE_POLLS_URL . 'src/OliForge_logo.png' ); ?>" alt="<?php esc_attr_e( 'OliForge', 'oliforge-polls' ); ?>" width="64" height="64" />
                <div class="oliforge-header__brandtext">
                    <span class="oliforge-header__name"><?php esc_html_e( 'OliForge', 'oliforge-polls' ); ?></span>
                    <span class="oliforge-header__tagline"><?php esc_html_e( 'Engineering without complexity.', 'oliforge-polls' ); ?></span>
                </div>
            </div>
            <div class="oliforge-header__title">
                <h1><?php echo esc_html( $title_main ); ?> <span class="oliforge-accent"><?php echo esc_html( $title_accent ); ?></span></h1>
                <span class="oliforge-badge oliforge-badge--version">v<?php echo esc_html( OLIFORGE_POLLS_VERSION ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Injects the branded header above the Polls list table screen only —
     * admin_notices fires on every admin page, so this must self-gate by
     * screen ID rather than running everywhere.
     *
     * @return void
     */
    public static function render_list_header(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-' . OliForge_Polls_CPT::POST_TYPE !== $screen->id ) {
            return;
        }
        self::render_brand_header( __( 'Polls', 'oliforge-polls' ), __( 'List', 'oliforge-polls' ) );
    }

    public static function register_meta_box() {
        add_meta_box(
            'oliforge_polls_builder',
            __( 'Poll Builder', 'oliforge-polls' ),
            array( __CLASS__, 'render_builder_meta_box' ),
            OliForge_Polls_CPT::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'oliforge_polls_live_preview',
            __( 'Live Preview', 'oliforge-polls' ),
            array( __CLASS__, 'render_preview_meta_box' ),
            OliForge_Polls_CPT::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'oliforge_polls_shortcode',
            __( 'Shortcode', 'oliforge-polls' ),
            array( __CLASS__, 'render_shortcode_meta_box' ),
            OliForge_Polls_CPT::POST_TYPE,
            'normal',
            'low'
        );
    }

    public static function render_builder_meta_box( $post ) {
        wp_nonce_field( 'oliforge_polls_save_meta', 'oliforge_polls_nonce' );
        $data = OliForge_Polls_Util::get_poll_data( $post->ID );
        echo '<div id="oliforge-polls-admin-root"></div>';
        echo '<textarea name="oliforge_polls_data" id="oliforge-polls-data-field" style="display:none;">' . esc_textarea( wp_json_encode( $data ) ) . '</textarea>';
    }

    public static function render_preview_meta_box() {
        echo '<div id="oliforge-polls-preview-root"></div>';
    }

    public static function render_shortcode_meta_box( $post ) {
        self::render_tools( $post );
    }

    /**
     * Renders the "Copy shortcode" button shared by the Shortcode meta box
     * and the Polls list table's Shortcode column, so both stay visually
     * and behaviorally identical instead of duplicating the markup.
     *
     * @param string $shortcode Full shortcode text, e.g. [oliforge-polls id="..."].
     * @return string
     */
    public static function render_copy_shortcode_button( $shortcode ) {
        return sprintf(
            '<button type="button" class="button button-small oliforge-polls-copy-btn" title="%1$s" data-oliforge-polls-copy-shortcode="%2$s"><img src="%3$s" alt="%4$s" width="16" height="16" /></button>',
            esc_attr__( 'Copy shortcode', 'oliforge-polls' ),
            esc_attr( $shortcode ),
            esc_url( OLIFORGE_POLLS_URL . 'src/icon-copy.png' ),
            esc_attr__( 'Copy', 'oliforge-polls' )
        );
    }

    private static function render_tools( $post ) {
        if ( ! $post || empty( $post->ID ) ) {
            return;
        }

        $post_id = absint( $post->ID );
        $data    = OliForge_Polls_Util::get_poll_data( $post_id );

        if ( ! empty( $data['shortcode_id'] ) ) {
            $shortcode = '[oliforge-polls id="' . esc_attr( $data['shortcode_id'] ) . '"]';
            echo '<div class="oliforge-polls-shortcode-box">';
            echo '<code>' . esc_html( $shortcode ) . '</code> ';
            echo self::render_copy_shortcode_button( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper.
            echo '</div>';
        }

        $reset_url   = wp_nonce_url( admin_url( 'admin-post.php?action=oliforge_polls_reset_votes&poll_id=' . $post_id ), 'oliforge_polls_reset_votes_' . $post_id );
        $recount_url = wp_nonce_url( admin_url( 'admin-post.php?action=oliforge_polls_recount_votes&poll_id=' . $post_id ), 'oliforge_polls_recount_votes_' . $post_id );
        $export_url  = wp_nonce_url( admin_url( 'admin-post.php?action=oliforge_polls_export_votes&poll_id=' . $post_id ), 'oliforge_polls_export_votes_' . $post_id );

        echo '<div class="oliforge-polls-admin-tools">';
        echo '<h3>' . esc_html__( 'Vote tools', 'oliforge-polls' ) . '</h3>';
        echo '<p>' . esc_html__( 'Use these tools after saving the poll. Reset removes all vote rows and counters. Recount rebuilds counters from vote rows. Export downloads a CSV file.', 'oliforge-polls' ) . '</p>';
        echo '<a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', 'oliforge-polls' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $recount_url ) . '">' . esc_html__( 'Recount results', 'oliforge-polls' ) . '</a> ';
        echo '<a class="button button-link-delete" data-oliforge-confirm="' . esc_attr__( 'Reset all votes for this poll?', 'oliforge-polls' ) . '" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset votes', 'oliforge-polls' ) . '</a>';
        echo '</div>';
    }

    private static function get_admin_poll_id() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is verified by check_admin_referer() in every caller before this value is used.
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;
        if ( ! $poll_id || OliForge_Polls_CPT::POST_TYPE !== get_post_type( $poll_id ) || ! current_user_can( 'edit_post', $poll_id ) ) {
            wp_die( esc_html__( 'You are not allowed to manage this poll.', 'oliforge-polls' ) );
        }
        return $poll_id;
    }

    public static function handle_reset_votes() {
        $poll_id = self::get_admin_poll_id();
        check_admin_referer( 'oliforge_polls_reset_votes_' . $poll_id );
        OliForge_Polls_DB::reset_poll( $poll_id );
        wp_safe_redirect( add_query_arg( 'oliforge_polls_notice', 'reset', get_edit_post_link( $poll_id, 'raw' ) ) );
        exit;
    }

    public static function handle_recount_votes() {
        $poll_id = self::get_admin_poll_id();
        check_admin_referer( 'oliforge_polls_recount_votes_' . $poll_id );
        OliForge_Polls_DB::recount_poll( $poll_id );
        wp_safe_redirect( add_query_arg( 'oliforge_polls_notice', 'recount', get_edit_post_link( $poll_id, 'raw' ) ) );
        exit;
    }

    public static function handle_export_votes() {
        $poll_id = self::get_admin_poll_id();
        check_admin_referer( 'oliforge_polls_export_votes_' . $poll_id );

        $data = OliForge_Polls_Util::get_poll_data( $poll_id );
        $answer_labels = array();
        foreach ( $data['answers'] as $answer ) {
            $answer_labels[ sanitize_key( $answer['id'] ) ] = sanitize_text_field( $answer['text'] );
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="oliforge-polls-' . $poll_id . '-votes.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'id', 'poll_id', 'answer_id', 'answer_text', 'user_id', 'voter_key', 'ip_hash', 'user_agent_hash', 'created_at' ) );

        foreach ( OliForge_Polls_DB::get_vote_rows( $poll_id ) as $row ) {
            fputcsv(
                $out,
                array(
                    $row['id'],
                    $row['poll_id'],
                    self::csv_cell( $row['answer_id'] ),
                    self::csv_cell( $answer_labels[ $row['answer_id'] ] ?? '' ),
                    $row['user_id'],
                    self::csv_cell( $row['voter_key'] ),
                    self::csv_cell( $row['ip_hash'] ),
                    self::csv_cell( $row['user_agent_hash'] ),
                    self::csv_cell( $row['created_at'] ),
                )
            );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- fclose() on php://output is not supported by WP_Filesystem; direct stream close is required here.
        fclose( $out );
        exit;
    }


    private static function csv_cell( $value ) {
        $value = (string) $value;
        if ( preg_match( '/^[=+@\-]/', $value ) ) {
            return "	" . $value;
        }
        return $value;
    }

    public static function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['oliforge_polls_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oliforge_polls_nonce'] ) ), 'oliforge_polls_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( OliForge_Polls_CPT::POST_TYPE !== $post->post_type ) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value is JSON; sanitizing before decode would corrupt it. OliForge_Polls_Util::sanitize_poll_data() sanitizes all fields after json_decode().
        $raw = isset( $_POST['oliforge_polls_data'] ) ? wp_unslash( $_POST['oliforge_polls_data'] ) : '';
        $decoded = json_decode( $raw, true );
        $data = OliForge_Polls_Util::sanitize_poll_data( $decoded );
        update_post_meta( $post_id, '_oliforge_polls_data', $data );
        OliForge_Polls_DB::ensure_answer_rows( $post_id, $data['answers'] );
    }

    /**
     * Enqueues the hand-written brand stylesheet/script shared by all three
     * of this plugin's PHP-rendered admin screens (Polls list, Poll edit
     * screen, Settings page). Unlike the React "Poll Builder" bundle, these
     * aren't part of the @wordpress/scripts build — plain files are enough.
     *
     * @return void
     */
    private static function enqueue_brand_assets() {
        wp_enqueue_style(
            'oliforge-polls-brand-admin',
            OLIFORGE_POLLS_URL . 'assets/css/admin.min.css',
            array(),
            OLIFORGE_POLLS_VERSION
        );

        wp_enqueue_script(
            'oliforge-polls-brand-admin',
            OLIFORGE_POLLS_URL . 'assets/js/admin.min.js',
            array(),
            OLIFORGE_POLLS_VERSION,
            true
        );
    }

    public static function enqueue_assets( $hook ) {
        global $post_type;

        if ( self::$settings_hook && $hook === self::$settings_hook ) {
            self::enqueue_brand_assets();
            return;
        }

        if ( 'edit.php' === $hook && OliForge_Polls_CPT::POST_TYPE === $post_type ) {
            self::enqueue_brand_assets();
            $asset = OliForge_Polls_Util::get_build_asset( 'admin-list' );
            wp_enqueue_script( 'oliforge-polls-admin-list', OliForge_Polls_Util::get_build_url( 'admin-list.js' ), $asset['dependencies'], $asset['version'], true );
            return;
        }

        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || OliForge_Polls_CPT::POST_TYPE !== $post_type ) {
            return;
        }

        self::enqueue_brand_assets();

        $list_asset = OliForge_Polls_Util::get_build_asset( 'admin-list' );
        wp_enqueue_script( 'oliforge-polls-admin-list', OliForge_Polls_Util::get_build_url( 'admin-list.js' ), $list_asset['dependencies'], $list_asset['version'], true );

        wp_enqueue_style(
            'oliforge-polls-admin',
            OliForge_Polls_Util::get_build_url( 'style-admin-editor.css' ),
            array(),
            OLIFORGE_POLLS_VERSION
        );

        $asset = OliForge_Polls_Util::get_build_asset( 'admin-editor' );
        $deps  = array_unique( array_merge( $asset['dependencies'], array( 'wp-element', 'wp-components' ) ) );
        wp_enqueue_script(
            'oliforge-polls-admin',
            OliForge_Polls_Util::get_build_url( 'admin-editor.js' ),
            $deps,
            $asset['version'],
            true
        );

        $post_id = get_the_ID();
        wp_localize_script(
            'oliforge-polls-admin',
            'OliForgePollsAdmin',
            array(
                'data'     => OliForge_Polls_Util::get_poll_data( $post_id ),
                'defaults' => OliForge_Polls_Util::default_settings(),
                'labels'   => array(
                    'addAnswer' => __( 'Add answer', 'oliforge-polls' ),
                    'remove'    => __( 'Remove', 'oliforge-polls' ),
                    'setDefaults' => __( 'Set default labels', 'oliforge-polls' ),
                    'confirmReset' => __( 'Are you sure you want to reset labels to defaults?', 'oliforge-polls' ),
                    'livePreview'  => __( 'Live preview', 'oliforge-polls' ),
                    'liveResults'  => __( 'Live Results', 'oliforge-polls' ),
                    'yourQuestion' => __( 'Your question', 'oliforge-polls' ),
                    'content'      => __( 'Content', 'oliforge-polls' ),
                    'answers'      => __( 'Answers', 'oliforge-polls' ),
                    'labels'       => __( 'Labels', 'oliforge-polls' ),
                    'container'    => __( 'Container', 'oliforge-polls' ),
                    'qAndD'        => __( 'Question & description', 'oliforge-polls' ),
                    'answersStyle' => __( 'Answers style', 'oliforge-polls' ),
                    'buttonAndRes' => __( 'Button & results', 'oliforge-polls' ),
                ),
            )
        );
    }
}
