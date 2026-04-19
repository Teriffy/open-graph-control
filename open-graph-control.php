<?php
/**
 * Plugin Name:       Open Graph Control
 * Plugin URI:        https://wordpress.org/plugins/open-graph-control/
 * Description:       Full control over Open Graph and social meta tags across 12 platforms.
 * Version:           0.2.1
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Evžen Leonenko
 * Author URI:        https://leonenko.cz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Text Domain:       open-graph-control
 * Domain Path:       /languages
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

use EvzenLeonenko\OpenGraphControl\Bootstrap;
use EvzenLeonenko\OpenGraphControl\Container;
use EvzenLeonenko\OpenGraphControl\Plugin;

defined( 'ABSPATH' ) || exit;

// PSR-4 autoload.
require_once __DIR__ . '/vendor/autoload.php';

// Constants.
define( 'OGC_VERSION', '0.2.1' );
define( 'OGC_FILE', __FILE__ );
define( 'OGC_DIR', plugin_dir_path( __FILE__ ) );
define( 'OGC_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, [ \EvzenLeonenko\OpenGraphControl\Lifecycle::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \EvzenLeonenko\OpenGraphControl\Lifecycle::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		$container = new Container();
		Bootstrap::register( $container );
		( new Plugin( $container ) )->boot();
	}
);
