<?php // phpcs:ignore Generic.Commenting.DocComment.MissingShort
/**
 * @wordpress-plugin
 * Plugin Name:       Outstand Post Terms
 * Description:       Adds a setting to the Post Terms block that lists terms without links.
 * Plugin URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-post-terms&utm_campaign=plugin-uri
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Version:           1.0.0
 * Author:            Outstand
 * Author URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-post-terms&utm_campaign=author-uri
 * License:           GPL-3.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-3.0-or-later.html
 * Update URI:        https://outstand.site/
 * GitHub Plugin URI: https://github.com/pixelalbatross/outstand-post-terms
 * Text Domain:       outstand-post-terms
 * Domain Path:       /languages
 */

namespace Outstand\WP\PostTerms;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OUTSTAND_POST_TERMS_VERSION', '1.0.0' );
define( 'OUTSTAND_POST_TERMS_BASENAME', plugin_basename( __FILE__ ) );
define( 'OUTSTAND_POST_TERMS_URL', plugin_dir_url( __FILE__ ) );
define( 'OUTSTAND_POST_TERMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'OUTSTAND_POST_TERMS_DIST_URL', OUTSTAND_POST_TERMS_URL . 'build/' );
define( 'OUTSTAND_POST_TERMS_DIST_PATH', OUTSTAND_POST_TERMS_PATH . 'build/' );

if ( file_exists( OUTSTAND_POST_TERMS_PATH . 'vendor/autoload.php' ) ) {
	require_once OUTSTAND_POST_TERMS_PATH . 'vendor/autoload.php';
}

if ( class_exists( PucFactory::class ) ) {
	PucFactory::buildUpdateChecker(
		'https://github.com/pixelalbatross/outstand-post-terms/',
		__FILE__,
		'outstand-post-terms'
	)->setBranch( 'main' );
}

add_action(
	'plugins_loaded',
	function () {
		Plugin::get_instance()->enable();
	}
);
