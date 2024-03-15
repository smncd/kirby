<?php

namespace Kirby\Content;

use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;

/**
 * The Content class handles all fields
 * for content from pages, the site and users
 *
 * @package   Kirby Content
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
class Content
{
	/**
	 * Cached field objects
	 * Once a field is being fetched
	 * it is added to this array for
	 * later reuse
	 */
	protected array $fields = [];

	/**
	 * Magic getter for content fields
	 */
	public function __call(string $name, array $arguments = []): Field
	{
		return $this->get($name);
	}

	/**
	 * Creates a new Content object
	 *
	 * @param bool $normalize Set to `false` if the input field keys are already lowercase
	 */
	public function __construct(
		protected ModelWithContent $model,
		protected Language $language,
		protected array $data = [],
		bool $normalize = true
	) {
		if ($normalize === true) {
			$data = array_change_key_case($data, CASE_LOWER);
		}

		$this->data = $data;
	}

	/**
	 * Same as `self::data()` to improve
	 * `var_dump` output
	 * @codeCoverageIgnore
	 *
	 * @see self::data()
	 */
	public function __debugInfo(): array
	{
		return $this->toArray();
	}

	/**
	 * Returns the raw data array
	 */
	public function data(): array
	{
		return $this->data;
	}

	/**
	 * Returns all registered field objects
	 */
	public function fields(): array
	{
		foreach ($this->data as $key => $value) {
			$this->get($key);
		}
		return $this->fields;
	}

	/**
	 * Returns either a single field object
	 * or all registered fields
	 */
	public function get(string $key = null): Field|array
	{
		if ($key === null) {
			return $this->fields();
		}

		$key = strtolower($key);

		return $this->fields[$key] ??= new Field(
			content: $this,
			key:     $key,
			value:   $this->data()[$key] ?? null
		);
	}

	/**
	 * Checks if a content field is set
	 */
	public function has(string $key): bool
	{
		return isset($this->data[strtolower($key)]) === true;
	}

	/**
	 * Returns all field keys
	 */
	public function keys(): array
	{
		return array_keys($this->data());
	}

	/**
	 * Returns the content language object
	 */
	public function language(): Language
	{
		return $this->language;
	}

	/**
	 * Returns the parent
	 * Site, Page, File or User object
	 */
	public function model(): ModelWithContent
	{
		return $this->model;
	}

	/**
	 * Returns a clone of the content object
	 * without the fields, specified by the
	 * passed key(s)
	 */
	public function not(string ...$keys): static
	{
		$copy = clone $this;
		$copy->fields = [];

		foreach ($keys as $key) {
			unset($copy->data[strtolower($key)]);
		}

		return $copy;
	}

	/**
	 * Returns the raw data array
	 *
	 * @see self::data()
	 */
	public function toArray(): array
	{
		return $this->data();
	}

	/**
	 * Updates the content and returns
	 * a cloned object
	 *
	 * @return $this
	 */
	public function update(
		array $content = [],
		bool $overwrite = false
	): static {
		$content = array_change_key_case((array)$content, CASE_LOWER);
		$this->data = $overwrite === true ? $content : array_merge($this->data, $content);

		// clear cache of Field objects
		$this->fields = [];

		return $this;
	}
}
