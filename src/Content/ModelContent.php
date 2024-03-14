<?php

namespace Kirby\Content;

use Kirby\Cms\Blueprint;
use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Form\Form;

/**
 * @package   Kirby Content
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
class ModelContent extends Content
{
	/**
	 * Creates a new Content object
	 *
	 * @param bool $normalize Set to `false` if the input field keys are already lowercase
	 */
	public function __construct(
		protected ModelWithContent $model,
		protected Language $language,
		array $data = [],
		bool $normalize = true
	) {
		parent::__construct(
			data: $data,
			normalize: $normalize
		);
	}

	/**
	 * Converts the content to a new blueprint
	 */
	public function convertTo(string $to): array
	{
		// prepare data
		$data    = [];
		$content = $this;

		// blueprints
		$old       = $this->model->blueprint();
		$subfolder = dirname($old->name());
		$new       = Blueprint::factory(
			$subfolder . '/' . $to,
			$subfolder . '/default',
			$this->model
		);

		// forms
		$oldForm = new Form([
			'fields' => $old->fields(),
			'model'  => $this->model
		]);
		$newForm = new Form([
			'fields' => $new->fields(),
			'model'  => $this->model
		]);

		// fields
		$oldFields = $oldForm->fields();
		$newFields = $newForm->fields();

		// go through all fields of new template
		foreach ($newFields as $newField) {
			$name     = $newField->name();
			$oldField = $oldFields->get($name);

			// field name and type matches with old template
			if ($oldField?->type() === $newField->type()) {
				$data[$name] = $content->get($name)->value();
			} else {
				$data[$name] = $newField->default();
			}
		}

		// preserve existing fields
		return array_merge($this->data, $data);
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
			$this->model,
			$key,
			$this->data()[$key] ?? null
		);
	}

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
	 * Set the parent model
	 *
	 * @return $this
	 */
	public function setModel(ModelWithContent $model): static
	{
		$this->model = $model;
		return $this;
	}
}
