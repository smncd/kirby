<?php

namespace Kirby\Cms;

use Closure;
use Kirby\Content\Content;
use Kirby\Content\ContentStorageHandler;
use Kirby\Content\PlainTextContentStorageHandler;
use Kirby\Content\Version;
use Kirby\Content\VersionId;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Form\Form;
use Kirby\Panel\Model;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Identifiable;
use Kirby\Uuid\Uuid;
use Kirby\Uuid\Uuids;
use Throwable;

/**
 * ModelWithContent
 *
 * @package   Kirby Cms
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
abstract class ModelWithContent implements Identifiable
{
	/**
	 * Each model must define a CLASS_ALIAS
	 * which will be used in template queries.
	 * The CLASS_ALIAS is a short human-readable
	 * version of the class name, i.e. page.
	 */
	public const CLASS_ALIAS = null;

	/**
	 * Cached array of valid blueprints
	 * that could be used for the model
	 */
	public array|null $blueprints = null;

	public Content|null $content;
	public static App $kirby;
	protected Site|null $site;
	protected ContentStorageHandler $storage;

	/**
	 * Store values used to initilaize object
	 */
	protected array $propertyData = [];

	public function __construct(array $props = [])
	{
		$this->site = $props['site'] ?? null;

		$this->setContent($props['content'] ?? null);
		$this->setTranslations($props['translations'] ?? null);

		$this->propertyData = $props;
	}

	/**
	 * Returns the blueprint of the model
	 */
	abstract public function blueprint(): Blueprint;

	/**
	 * Returns an array with all blueprints that are available
	 */
	public function blueprints(string $inSection = null): array
	{
		// helper function
		$toBlueprints = function (array $sections): array {
			$blueprints = [];

			foreach ($sections as $section) {
				if ($section === null) {
					continue;
				}

				foreach ((array)$section->blueprints() as $blueprint) {
					$blueprints[$blueprint['name']] = $blueprint;
				}
			}

			return array_values($blueprints);
		};

		$blueprint = $this->blueprint();

		// no caching for when collecting for specific section
		if ($inSection !== null) {
			return $toBlueprints([$blueprint->section($inSection)]);
		}

		return $this->blueprints ??= $toBlueprints($blueprint->sections());
	}

	/**
	 * Creates a new instance with the same
	 * initial properties
	 *
	 * @todo eventually refactor without need of propertyData
	 */
	public function clone(array $props = []): static
	{
		return new static(array_replace_recursive($this->propertyData, $props));
	}

	/**
	 * Executes any given model action
	 */
	abstract protected function commit(
		string $action,
		array $arguments,
		Closure $callback
	): mixed;

	/**
	 * Returns the content
	 *
	 * @throws \Kirby\Exception\InvalidArgumentException If the language for the given code does not exist
	 */
	public function content(string|null $languageCode = null): Content
	{
		return $this->version(VersionId::PUBLISHED)->content(Language::fromCode($languageCode));
	}

	/**
	 * Converts model to new blueprint
	 * incl. its content for all translations
	 */
	protected function convertTo(string $blueprint): static
	{
		// first close object with new blueprint as template
		$new = $this->clone(['template' => $blueprint]);

		// temporary compatibility change (TODO: also convert changes)
		$identifier = VersionId::PUBLISHED;

		// for multilang, we go through all translations and
		// covnert the content for each of them, remove the content file
		// to rewrite it with converted content afterwards
		if ($this->kirby()->multilang() === true) {
			$translations = [];

			foreach ($this->kirby()->languages()->codes() as $code) {
				if ($this->translation($code)?->exists() === true) {
					$content = $this->content($code)->convertTo($blueprint);

					// delete the old text file
					$this->storage()->delete(
						VersionId::PUBLISHED,
						$code
					);

					// save to re-create the translation content file
					// with the converted/updated content
					$new->save($content, $code);
				}

				$translations[] = [
					'code'    => $code,
					'content' => $content ?? null
				];
			}

			// cloning the object with the new translations content ensures
			// that `propertyData` prop does not hold any old translations
			// content that could surface on subsequent cloning
			return $new->clone(['translations' => $translations]);
		}

		// for single language setups, we do the same,
		// just once for the main content
		$content = $this->content()->convertTo($blueprint);

		// delete the old text file
		$this->storage()->delete($identifier, 'default');

		return $new->save($content);
	}

	/**
	 * Decrement a given field value
	 */
	public function decrement(
		string $field,
		int $by = 1,
		int $min = 0
	): static {
		$value = (int)$this->content()->get($field)->value() - $by;

		if ($value < $min) {
			$value = $min;
		}

		return $this->update([$field => $value]);
	}

	/**
	 * Returns all content validation errors
	 */
	public function errors(): array
	{
		$errors = [];

		foreach ($this->blueprint()->sections() as $section) {
			$errors = array_merge($errors, $section->errors());
		}

		return $errors;
	}

	/**
	 * Creates a clone and fetches all
	 * lazy-loaded getters to get a full copy
	 */
	public function hardcopy(): static
	{
		$clone = $this->clone();

		foreach (get_object_vars($clone) as $name => $default) {
			if (method_exists($clone, $name) === true) {
				$clone->$name();
			}
		}

		return $clone;
	}

	/**
	 * Each model must return a unique id
	 */
	public function id(): string|null
	{
		return null;
	}

	/**
	 * Increment a given field value
	 */
	public function increment(
		string $field,
		int $by = 1,
		int $max = null
	): static {
		$value = (int)$this->content()->get($field)->value() + $by;

		if ($max && $value > $max) {
			$value = $max;
		}

		return $this->update([$field => $value]);
	}

	/**
	 * Checks if the model is locked for the current user
	 */
	public function isLocked(): bool
	{
		$lock = $this->lock();
		return $lock && $lock->isLocked() === true;
	}

	/**
	 * Checks if the data has any errors
	 */
	public function isValid(): bool
	{
		return Form::for($this)->isValid() === true;
	}

	/**
	 * Returns the parent Kirby instance
	 */
	public function kirby(): App
	{
		return static::$kirby ??= App::instance();
	}

	/**
	 * Returns the lock object for this model
	 *
	 * Only if a content directory exists,
	 * virtual pages will need to overwrite this method
	 */
	public function lock(): ContentLock|null
	{
		$dir = $this->root();

		if ($this::CLASS_ALIAS === 'file') {
			$dir = dirname($dir);
		}

		if (
			$this->kirby()->option('content.locking', true) &&
			is_string($dir) === true &&
			file_exists($dir) === true
		) {
			return new ContentLock($this);
		}

		return null;
	}

	/**
	 * Returns the panel info of the model
	 * @since 3.6.0
	 */
	abstract public function panel(): Model;

	/**
	 * Must return the permissions object for the model
	 */
	abstract public function permissions(): ModelPermissions;

	/**
	 * Clean internal caches
	 *
	 * @return $this
	 */
	public function purge(): static
	{
		$this->blueprints = null;
		$this->content    = null;

		return $this;
	}

	/**
	 * Creates a string query, starting from the model
	 * @internal
	 */
	public function query(
		string $query = null,
		string $expect = null
	): mixed {
		if ($query === null) {
			return null;
		}

		try {
			$result = Str::query($query, [
				'kirby'             => $this->kirby(),
				'site'              => $this instanceof Site ? $this : $this->site(),
				'model'             => $this,
				static::CLASS_ALIAS => $this
			]);
		} catch (Throwable) {
			return null;
		}

		if ($expect !== null && $result instanceof $expect === false) {
			return null;
		}

		return $result;
	}

	/**
	 * Read the content from the content file
	 * @internal
	 */
	public function readContent(string $languageCode = null): array
	{
		try {
			return $this->storage()->read(
				VersionId::PUBLISHED,
				Language::fromCode($languageCode)
			);
		} catch (NotFoundException) {
			// only if the content really does not exist, it's ok
			// to return empty content. Otherwise this could lead to
			// content loss in case of storage reading issues
			return [];
		}
	}

	/**
	 * Returns the absolute path to the model
	 */
	abstract public function root(): string|null;

	/**
	 * Stores the content on disk
	 * @internal
	 */
	public function save(
		array|null $data = null,
		string|null $languageCode = null,
		bool $overwrite = false
	): static {
		// create a clone to avoid modifying the original
		$clone = $this->clone();

		// merge the new data with the existing content
		$clone->content()->update($data, $overwrite);

		// send the full content array to the writer
		$clone->writeContent($clone->content()->toArray(), $languageCode);

		return $clone;
	}

	/**
	 * Sets the Content object
	 *
	 * @return $this
	 */
	protected function setContent(array $content = null): static
	{
		// TODO: refactor
		// if ($content !== null) {
		// 	$content = new Content($content, $this);
		// }

		// $this->content = $content;
		return $this;
	}

	/**
	 * Create the translations collection from an array
	 *
	 * @return $this
	 */
	protected function setTranslations(array $translations = null): static
	{
		// TODO: refactor
		// if ($translations !== null) {
		// 	$this->translations = new Collection();

		// 	foreach ($translations as $props) {
		// 		$props['parent'] = $this;
		// 		$translation = new ContentTranslation($props);
		// 		$this->translations->data[$translation->code()] = $translation;
		// 	}
		// } else {
		// 	$this->translations = null;
		// }

		return $this;
	}

	/**
	 * Returns the parent Site instance
	 */
	public function site(): Site
	{
		return $this->site ??= $this->kirby()->site();
	}

	/**
	 * Returns the content storage handler
	 * @internal
	 */
	public function storage(): ContentStorageHandler
	{
		return $this->storage ??= new PlainTextContentStorageHandler(
			model: $this,
		);
	}

	/**
	 * Convert the model to a simple array
	 */
	public function toArray(): array
	{
		return [
			'content'      => $this->content()->toArray(),
			'translations' => $this->translations()->toArray()
		];
	}

	/**
	 * String template builder with automatic HTML escaping
	 * @since 3.6.0
	 *
	 * @param string|null $template Template string or `null` to use the model ID
	 * @param string|null $fallback Fallback for tokens in the template that cannot be replaced
	 *                              (`null` to keep the original token)
	 */
	public function toSafeString(
		string $template = null,
		array $data = [],
		string|null $fallback = ''
	): string {
		return $this->toString($template, $data, $fallback, 'safeTemplate');
	}

	/**
	 * String template builder
	 *
	 * @param string|null $template Template string or `null` to use the model ID
	 * @param string|null $fallback Fallback for tokens in the template that cannot be replaced
	 *                              (`null` to keep the original token)
	 * @param string $handler For internal use
	 */
	public function toString(
		string $template = null,
		array $data = [],
		string|null $fallback = '',
		string $handler = 'template'
	): string {
		if ($template === null) {
			return $this->id() ?? '';
		}

		if ($handler !== 'template' && $handler !== 'safeTemplate') {
			throw new InvalidArgumentException('Invalid toString handler'); // @codeCoverageIgnore
		}

		$result = Str::$handler($template, array_replace([
			'kirby'             => $this->kirby(),
			'site'              => $this instanceof Site ? $this : $this->site(),
			'model'             => $this,
			static::CLASS_ALIAS => $this,
		], $data), ['fallback' => $fallback]);

		return $result;
	}

	/**
	 * Makes it possible to convert the entire model
	 * to a string. Mostly useful for debugging
	 */
	public function __toString(): string
	{
		return $this->id();
	}

	/**
	 * Returns a single translation by language code
	 * If no code is specified the current translation is returned
	 *
	 * @see static::content
	 */
	public function translation(
		string $languageCode = null
	): Content {
		return $this->content($languageCode);
	}

	/**
	 * Returns the translations collection
	 */
	public function translations(): Collection
	{
		$translations = new Collection();

		// TODO: handle single-language
		foreach ($this->kirby()->languages() as $language) {
			$translations->data[$language->code()] = $this->translation($language);
		}

		return $translations;
	}

	/**
	 * Updates the model data
	 *
	 * @throws \Kirby\Exception\InvalidArgumentException If the input array contains invalid values
	 */
	public function update(
		array $input = null,
		string $languageCode = null,
		bool $validate = false
	): static {
		$form = Form::for($this, [
			'ignoreDisabled' => $validate === false,
			'input'          => $input,
			'language'       => $languageCode,
		]);

		// validate the input
		if ($validate === true && $form->isInvalid() === true) {
			throw new InvalidArgumentException([
				'fallback' => 'Invalid form with errors',
				'details'  => $form->errors()
			]);
		}

		return $this->commit(
			'update',
			[
				static::CLASS_ALIAS => $this,
				'values'            => $form->data(),
				'strings'           => $form->strings(),
				'languageCode'      => $languageCode
			],
			fn ($model, $values, $strings, $languageCode) =>
				$model->save($strings, $languageCode, true)
		);
	}

	/**
	 * Returns the model's UUID
	 * @since 3.8.0
	 */
	public function uuid(): Uuid|null
	{
		return Uuid::for($this);
	}

	public function version(VersionId $versionId = VersionId::PUBLISHED): Version
	{
		return new Version($this, $versionId);
	}

	/**
	 * Low level data writer method
	 * to store the given data on disk or anywhere else
	 * @internal
	 */
	public function writeContent(array $data, string $languageCode = null): bool
	{
		$language = Language::fromCode($languageCode);

		try {
			// we can only update if the version already exists
			$this->storage()->update(VersionId::PUBLISHED, $language, $data);
		} catch (NotFoundException) {
			// otherwise create a new version
			$this->storage()->create(VersionId::PUBLISHED, $language, $data);
		}

		return true;
	}
}
