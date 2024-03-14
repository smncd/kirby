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
}
