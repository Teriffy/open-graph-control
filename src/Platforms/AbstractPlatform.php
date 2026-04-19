<?php
/**
 * Base class for platform implementations.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Description;
use EvzenLeonenko\OpenGraphControl\Resolvers\Image;
use EvzenLeonenko\OpenGraphControl\Resolvers\Locale;
use EvzenLeonenko\OpenGraphControl\Resolvers\Title;
use EvzenLeonenko\OpenGraphControl\Resolvers\Type;
use EvzenLeonenko\OpenGraphControl\Resolvers\Url;

/**
 * Provides shared dependencies + the enabled toggle for all platforms.
 *
 * Concrete platforms (Facebook, Twitter, ...) extend this class and
 * implement slug() and tags().
 */
abstract class AbstractPlatform implements PlatformInterface {

	public function __construct(
		protected OptionsRepository $options,
		protected Title $title,
		protected Description $description,
		protected Image $image,
		protected Type $type,
		protected Url $url,
		protected Locale $locale
	) {}

	abstract public function slug(): string;

	public function is_enabled(): bool {
		return (bool) $this->options->get_path( 'platforms.' . $this->slug() . '.enabled' );
	}

	public function json_ld( Context $context ): ?string {
		return null;
	}
}
