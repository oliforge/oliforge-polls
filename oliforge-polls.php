<?php
/**
 * Plugin Name: OliForge Polls
 * Plugin URI:  https://github.com/oliforge/oliforge-polls
 * Description: Lightweight polls plugin with Vote CPT, shortcode rendering, REST API voting, DB-backed results, hardened result visibility, CSV export, reset/recount tools, vote start/end dates, React admin editor, and Gutenberg dynamic block built with @wordpress/scripts, Elementor widget.
 * Version:           0.9.0
 * Author:Oleksandr Lilik
 * Text Domain: oliforge-polls
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const OLIFORGE_POLLS_VERSION = '0.9.0';
const OLIFORGE_POLLS_FILE = __FILE__;
define( 'OLIFORGE_POLLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'OLIFORGE_POLLS_URL', plugin_dir_url( __FILE__ ) );

require_once OLIFORGE_POLLS_PATH . 'includes/class-oliforge-polls-plugin.php';

function oliforge_polls_boot() {
    return OliForge_Polls_Plugin::instance();
}

oliforge_polls_boot();
