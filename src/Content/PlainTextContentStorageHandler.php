<?php

namespace Kirby\Content;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Data\Data;
use Kirby\Exception\Exception;
use Kirby\Exception\LogicException;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuids;

/**
 * Content storage handler using plain text files
 * stored in the content folder
 * @internal
 * @since 4.0.0
 *
 * @package   Kirby Content
 * @author    Lukas Bestle <lukas@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
class PlainTextContentStorageHandler extends ContentStorageHandler
{
	public function __construct(protected ModelWithContent $model)
	{
	}

	/**
	 * Creates a new version
	 *
	 * @param array<string, string> $fields Content fields
	 */
	public function create(VersionId $versionId, Language $lang, array $fields): void
	{
		$this->write($versionId, $lang, $fields);
	}

	/**
	 * Deletes an existing version in an idempotent way if it was already deleted
	 */
	public function delete(VersionId $versionId, Language $lang): void
	{
		$file    = $this->file($versionId, $lang);
		$success = F::unlink($file);

		// @codeCoverageIgnoreStart
		if ($success !== true) {
			throw new Exception('Could not delete content file');
		}
		// @codeCoverageIgnoreEnd

		// clean up empty directories
		$dir = dirname($file);

		if (
			Dir::exists($dir) === true &&
			Dir::isEmpty($dir) === true
		) {
			$success = rmdir($dir);

			// @codeCoverageIgnoreStart
			if ($success !== true) {
				throw new Exception('Could not delete empty content directory');
			}
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Creates the absolute directory path for the model
	 */
	public static function directory(ModelWithContent $model, VersionId $versionId): string
	{
		$directory = match (true) {
			$model instanceof File
				=> dirname($model->root()),
			default
				=> $model->root()
		};

		if ($versionId === VersionId::CHANGES) {
			$directory .= '/_changes';
		}

		return $directory;
	}

	/**
	 * Checks if a version exists
	 */
	public function exists(VersionId $versionId, Language $lang): bool
	{
		return is_file($this->file($versionId, $lang)) === true;
	}

	/**
	 * Returns the absolute path to the content file
	 *
	 * @throws \Kirby\Exception\LogicException If the model type doesn't have a known content filename
	 */
	public function file(VersionId $versionId, Language $lang): string
	{
		// get the filename without extension and language code
		return match (true) {
			$this->model instanceof File => $this->fileForFile($this->model, $versionId, $lang),
			$this->model instanceof Page => $this->fileForPage($this->model, $versionId, $lang),
			$this->model instanceof Site => $this->fileForSite($this->model, $versionId, $lang),
			$this->model instanceof User => $this->fileForUser($this->model, $versionId, $lang),
			// @codeCoverageIgnoreStart
			default => throw new LogicException('Cannot determine content file for model type "' . $this->model::CLASS_ALIAS . '"')
			// @codeCoverageIgnoreEnd
		};
	}

	public static function fileForFile(File $model, VersionId $versionId, Language $language): string
	{
		return static::directory($model, $versionId) . '/' . static::filename($model->filename(), $language);
	}

	public static function fileForPage(Page $model, VersionId $versionId, Language $language): string
	{
		if ($model->isDraft() === true && $versionId === VersionId::CHANGES) {
			throw new LogicException('Drafts cannot have a changes file');
		}

		return static::directory($model, $versionId) . '/' . static::filename($model->intendedTemplate()->name(), $language);
	}

	public static function fileForSite(Site $model, VersionId $versionId, Language $language): string
	{
		return static::directory($model, $versionId) . '/' . static::filename('site', $language);
	}

	public static function fileForUser(User $model, VersionId $versionId, Language $language): string
	{
		return static::directory($model, $versionId) . '/' . static::filename('user', $language);
	}

	public static function filename(string $name, Language $language): string
	{
		$kirby     = App::instance();
		$extension = $kirby->contentExtension();

		if ($kirby->multilang() === true) {
			return $name . '.' . $language->code() . '.' . $extension;
		}

		return $name . '.' . $extension;
	}

	/**
	 * Returns the modification timestamp of a version
	 * if it exists
	 */
	public function modified(VersionId $versionId, Language $lang): int|null
	{
		$modified = F::modified($this->file($versionId, $lang));

		if (is_int($modified) === true) {
			return $modified;
		}

		return null;
	}

	/**
	 * Returns the stored content fields
	 *
	 * @return array<string, string>
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	public function read(VersionId $versionId, Language $lang): array
	{
		return Data::read($this->file($versionId, $lang));
	}

	/**
	 * Updates the modification timestamp of an existing version
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	public function touch(VersionId $versionId, Language $lang): void
	{
		$success = touch($this->file($versionId, $lang));

		// @codeCoverageIgnoreStart
		if ($success !== true) {
			throw new Exception('Could not touch existing content file');
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Moves content from one version-language combination to another
	 */
	public function move(
		VersionId $fromVersionId,
		Language $fromLang,
		VersionId $toVersionId,
		Language $toLang
	): void {
		F::move(
			$this->file($fromVersionId, $fromLang),
			$this->file($toVersionId, $toLang)
		);
	}

	/**
	 * Normalize all fields before they get saved.
	 * Remove those that should not be stored
	 */
	protected function normalize(VersionId $versionId, Language $lang, array $fields): array
	{
		if ($lang->isDefault() === false) {
			// remove all untranslatable fields
			foreach ($this->model->blueprint()->fields() as $field) {
				if (($field['translate'] ?? true) === false) {
					$fields[strtolower($field['name'])] = null;
				}
			}

			// remove UUID for non-default languages
			if (Uuids::enabled() === true && isset($fields['uuid']) === true) {
				$fields['uuid'] = null;
			}
		}

		$fields = match(true) {
			$this->model instanceof File
				=> $this->normalizeFileFields($this->model, $fields),
			$this->model instanceof Page
				=> $this->normalizePageFields($this->model, $fields),
			$this->model instanceof Site
				=> $this->normalizeSiteFields($this->model, $fields),
			$this->model instanceof User
				=> $this->normalizeUserFields($this->model, $fields),
		};

		return $fields;
	}

	public static function normalizeFileFields(File $model, array $fields): array
	{
		// only add the template in, if the $data array
		// doesn't explicitly unsets it
		if (
			array_key_exists('template', $fields) === false &&
			$template = $model->template()
		) {
			$fields['template'] = $template;
		}

		return $fields;
	}

	public static function normalizePageFields(Page $model, array $fields): array
	{
		return A::prepend($fields, [
			'title' => $fields['title'] ?? null,
			'slug'  => $fields['slug']  ?? null
		]);
	}

	public static function normalizeSiteFields(Site $model, array $fields): array
	{
		// always put the title first
		return A::prepend($fields, [
			'title' => $fields['title'] ?? null
		]);
	}

	public static function normalizeUserFields(User $model, array $fields): array
	{
		// remove stuff that has nothing to do in the text files
		unset(
			$fields['email'],
			$fields['language'],
			$fields['name'],
			$fields['password'],
			$fields['role']
		);

		return $fields;
	}

	/**
	 * Updates the content fields of an existing version
	 *
	 * @param array<string, string> $fields Content fields
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	public function update(VersionId $versionId, Language $lang, array $fields): void
	{
		$this->write($versionId, $lang, $fields);
	}

	/**
	 * Writes the content fields of an existing version
	 *
	 * @param array<string, string> $fields Content fields
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	protected function write(VersionId $versionId, Language $lang, array $fields): void
	{
		$success = Data::write($this->file($versionId, $lang), $this->normalize($versionId, $lang, $fields));

		// @codeCoverageIgnoreStart
		if ($success !== true) {
			throw new Exception('Could not write the content file');
		}
		// @codeCoverageIgnoreEnd
	}

}
