<?php

namespace Kirby\Cms;

use Kirby\Toolkit\Locale;

/**
 * @package   Kirby Cms
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
class SingleLanguage extends Language
{
	protected string $code = '';
	protected bool $default = true;
	protected string $direction = 'ltr';
	protected array $locale = [];
	protected string $name = '';
	protected array $slugs = [];
	protected array $smartypants = [];
	protected array $translations = [];
	protected string|null $url = null;

	public function __construct()
	{
		$this->locale = Locale::normalize(
			App::instance()->option('locale', 'en_US.utf-8')
		);
	}
}
