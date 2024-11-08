<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://robertdevore.com
 * @since             1.0.0
 * @package           Block_Usage_Tracker
 *
 * @wordpress-plugin
 *
 * Plugin Name: Block Usage Tracker
 * Description: A plugin that lists active blocks on the site and counts their usage.
 * Plugin URI:  https://github.com/robertdevore/block-usage-tracker/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: block-usage-tracker
 * Domain Path: /languages
 * Update URI:  https://github.com/robertdevore/block-usage-tracker/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/robertdevore/block-usage-tracker/',
	__FILE__,
	'block-usage-tracker'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

/**
 * Current plugin version.
 */
define( 'BLOCK_USAGE_TRACKER_VERSION', '1.0.0' );

class BlockUsageTracker {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'admin_init', [ $this, 'track_block_usage' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            esc_html__( 'Block Usage Tracker', 'block-usage-tracker' ),
            esc_html__( 'Block Usage Tracker', 'block-usage-tracker' ),
            'manage_options',
            'block-usage-tracker',
            [ $this, 'display_settings_page' ]
        );
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_style( 'block-usage-tracker-styles', plugin_dir_url( __FILE__ ) . 'style.css', [], BLOCK_USAGE_TRACKER_VERSION );
        wp_enqueue_script( 'block-usage-tracker-scripts', plugin_dir_url( __FILE__ ) . 'script.js', [ 'jquery' ], BLOCK_USAGE_TRACKER_VERSION, true );
    }

    public function display_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Active Block Usage', 'block-usage-tracker' ) . '</h1>';
        
        $table = new BlockUsageTable();
        $table->prepare_items();
        $table->display();
        
        echo '</div>';

        // Modal container for displaying post links
        echo '<div id="block-usage-modal" class="block-usage-modal">
                <div class="block-usage-modal-content">
                    <span class="block-usage-close">&times;</span>
                    <h2>' . esc_html__( 'Block Usage Details', 'block-usage-tracker' ) . '</h2>
                    <div id="block-usage-modal-body"></div>
                </div>
              </div>';
    }

    public function track_block_usage() {
        // Initialize block counts and posts arrays
        $block_counts = get_transient( 'block_usage_counts' );
        $block_posts = get_transient( 'block_usage_posts' );
    
        // Check if transients already exist; if not, calculate block usage
        if ( false === $block_counts || false === $block_posts ) {
            $block_counts = [];
            $block_posts  = [];
    
            $posts = get_posts(
                [
                    'post_type'   => 'post',
                    'numberposts' => -1,
                    'post_status' => 'publish',
                ]
            );
    
            foreach ( $posts as $post ) {
                $blocks = parse_blocks( $post->post_content );
                
                foreach ( $blocks as $block ) {
                    $block_name = $block['blockName'] ? $block['blockName'] : 'core/unknown';
                    
                    if ( isset( $block_counts[ $block_name ] ) ) {
                        $block_counts[ $block_name ]++;
                    } else {
                        $block_counts[ $block_name ] = 1;
                    }
    
                    // Track post titles and links where this block is used
                    $block_posts[ $block_name ][] = [
                        'title' => get_the_title( $post->ID ),
                        'url'   => get_permalink( $post->ID ),
                    ];
                }
            }
    
            // Set the transients
            set_transient( 'block_usage_counts', $block_counts, DAY_IN_SECONDS );
            set_transient( 'block_usage_posts', $block_posts, DAY_IN_SECONDS );
        }
    
        // Store the counts and posts data in the global variable for use in the table
        $GLOBALS['block_usage_counts'] = $block_counts;
        $GLOBALS['block_usage_posts']  = $block_posts;
    }
        
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BlockUsageTable extends WP_List_Table {

    public function __construct() {
        parent::__construct(
            [
                'singular' => 'block',
                'plural'   => 'blocks',
                'ajax'     => false,
            ]
        );
    }

    public function prepare_items() {
        $columns               = $this->get_columns();
        $this->_column_headers = [ $columns, [], [] ];

        $this->items = [];
        foreach ( $GLOBALS['block_usage_counts'] as $block_name => $count ) {
            $this->items[] = [
                'name'  => $block_name,
                'count' => $count,
                'posts' => $GLOBALS['block_usage_posts'][ $block_name ] ?? [],
            ];
        }
    }

    public function get_columns() {
        return [
            'name'        => esc_html__( 'Block Type', 'block-usage-tracker' ),
            'count'       => esc_html__( 'Usage Count', 'block-usage-tracker' ),
            'view_details' => esc_html__( 'Details', 'block-usage-tracker' ),
        ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'name':
                return esc_html( $item['name'] );
            case 'count':
                return esc_html( number_format_i18n( $item['count'] ) );
            case 'view_details':
                // Properly JSON encode the posts data for HTML attribute use
                $posts_json = ! empty( $item['posts'] ) ? wp_json_encode( $item['posts'] ) : '[]';
    
                return sprintf(
                    '<a href="#" class="view-details-button" data-block="%s" data-posts="%s">%s</a>',
                    esc_attr( $item['name'] ),
                    esc_attr( $posts_json ),
                    esc_html__( 'View Details', 'block-usage-tracker' )
                );
            default:
                return '';
        }
    }
        
}

new BlockUsageTracker();
