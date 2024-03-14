<?php

namespace Kirby\Content;

use Kirby\Cms\File;
use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\SingleLanguage;
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
		$contentFile = $this->file($versionId, $lang);
		$success = F::unlink($contentFile);

		// @codeCoverageIgnoreStart
		if ($success !== true) {
			throw new Exception('Could not delete content file');
		}
		// @codeCoverageIgnoreEnd

		// clean up empty directories
		$contentDir = dirname($contentFile);
		if (
			Dir::exists($contentDir) === true &&
			Dir::isEmpty($contentDir) === true
		) {
			$success = rmdir($contentDir);

			// @codeCoverageIgnoreStart
			if ($success !== true) {
				throw new Exception('Could not delete empty content directory');
			}
			// @codeCoverageIgnoreEnd
		}
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
	protected function file(VersionId $versionId, Language $lang): string
	{
		$extension = $this->model->kirby()->contentExtension();
		$directory = $this->model->root();

		$directory = match ($this->model::CLASS_ALIAS) {
			'file'  => dirname($this->model->root()),
			default => $this->model->root()
		};

		$filename = match ($this->model::CLASS_ALIAS) {
			'file'  => $this->model->filename(),
			'page'  => $this->model->intendedTemplate()->name(),
			'site',
			'user'  => $this->model::CLASS_ALIAS,
			// @codeCoverageIgnoreStart
			default => throw new LogicException('Cannot determine content filename for model type "' . $this->model::CLASS_ALIAS . '"')
			// @codeCoverageIgnoreEnd
		};

		if ($this->model::CLASS_ALIAS === 'page' && $this->model->isDraft() === true) {
			// changes versions don't need anything extra
			// (drafts already have the `_drafts` prefix in their root),
			// but a published version is not possible
			if ($versionId === VersionId::PUBLISHED) {
				throw new LogicException('Drafts cannot have a published content file');
			}
		} elseif ($versionId === VersionId::CHANGES) {
			// other model type or published page that has a changes subfolder
			$directory .= '/_changes';
		}

		if ($lang instanceof SingleLanguage) {
			return $directory . '/' . $filename . '.' . $extension;
		}

		return $directory . '/' . $filename . '.' . $lang->code() . '.' . $extension;
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
				=> $this->normalizeFileFields($this->model, $field),
			$this->model instanceof Page
				=> $this->normalizePageFields($this->model, $field),
			$this->model instanceof Site
				=> $this->normalizeSiteFields($this->model, $field),
			$this->model instanceof User
				=> $this->normalizeUserFields($this->model, $field),
		};

		return $fields;
	}

	protected function normalizeFileFields(File $model, array $fields): array
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

	protected function normalizePageFields(Page $model, array $fields): array
	{
		return A::prepend($fields, [
			'title' => $fields['title'] ?? null,
			'slug'  => $fields['slug']  ?? null
		]);
	}

	protected function normalizeSiteFields(Site $model, array $fields): array
	{
		// always put the title first
		return A::prepend($fields, [
			'title' => $fields['title'] ?? null
		]);
	}

	protected function normalizeUserFields(User $model, array $fields): array
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
