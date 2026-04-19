<?php
/**
 * Resolver contract.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Resolvers;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a single resolved value for a given context, or null if nothing
 * in the fallback chain produced a usable value.
 */
interface ResolverInterface {

	public function resolve( Context $context ): ?string;
}
