<?php

namespace Kirby\Content;

use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;

/**
 * Interface for content storage handlers;
 * note that it is so far not viable to build custom
 * handlers because the CMS core relies on the filesystem
 * and cannot fully benefit from this abstraction yet
 * @internal
 * @since 4.0.0
 *
 * @package   Kirby Content
 * @author    Lukas Bestle <lukas@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
abstract class ContentStorageHandler
{
	abstract public function __construct(ModelWithContent $model);

	/**
	 * Creates a new version
	 *
	 * @param array<string, string> $fields Content fields
	 */
	abstract public function create(VersionId $versionId, Language $lang, array $fields): void;

	/**
	 * Deletes an existing version in an idempotent way if it was already deleted
	 */
	abstract public function delete(VersionId $versionId, Language $lang): void;

	/**
	 * Checks if a version exists
	 */
	abstract public function exists(VersionId $versionId, Language $lang): bool;

	/**
	 * Returns the modification timestamp of a version if it exists
	 */
	abstract public function modified(VersionId $versionId, Language $lang): int|null;

	/**
	 * Moves content from one version-language combination to another
	 */
	abstract public function move(
		VersionId $fromVersionId,
		Language $fromLang,
		VersionId $toVersionId,
		Language $toLang
	): void;

	/**
	 * Returns the stored content fields
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	abstract public function read(VersionId $versionId, Language $lang): array;

	/**
	 * Updates the modification timestamp of an existing version
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	abstract public function touch(VersionId $versionId, Language $lang): void;

	/**
	 * Updates the content fields of an existing version
	 *
	 * @param array<string, string> $fields Content fields
	 *
	 * @throws \Kirby\Exception\NotFoundException If the version does not exist
	 */
	abstract public function update(VersionId $versionID, Language $lang, array $fields): void;
}
