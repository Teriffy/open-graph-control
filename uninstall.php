<?php
/**
 * Uninstall handler for Open Graph Control.
 * Fires when a user deletes the plugin from WP admin.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';
\EvzenLeonenko\OpenGraphControl\Lifecycle::uninstall();
