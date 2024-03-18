<?php

namespace Kirby\Content;

use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Form\Form;

class Version
{
	public function __construct(
		protected ModelWithContent $model,
		protected VersionId $id
	) {
	}

	public function content(Language $language): Content
	{
		return new Content(
			model:    $this->model,
			language: $language,
			data:     $this->model->storage()->read($this->id, $language),
		);
	}

	public function create(Language $language, array $fields): void
	{
		$form = Form::for($this->model, [
			'input'    => $fields,
			'language' => $language->code(),
		]);

		$this->model->storage()->create($this->id, $language, $form->strings());
	}

	public function delete(Language $language): void
	{
		$this->model->storage()->delete($this->id, $language);
	}

	public function exists(Language $language): bool
	{
		return $this->model->storage()->exists($this->id, $language);
	}

	public function id(): VersionId
	{
		return $this->id;
	}

	public function model(): ModelWithContent
	{
		return $this->model;
	}

	public function move(Language $fromLanguage, VersionId $toVersionId, Language $toLanguage): void
	{
		$this->model->storage()->move($this->id, $fromLanguage, $toVersionId, $toLanguage);
	}

	public function read(Language $language): array
	{
		return $this->model->storage()->read($this->id, $language);
	}

	public function touch(Language $language): void
	{
		$this->model->storage()->touch($this->id, $language);
	}

	public function update(Language $language, array $fields): void
	{
		$form = Form::for($this->model, [
			'input'    => $fields,
			'language' => $language->code(),
		]);

		$content = $this->content($language);

		// merge the new data with the existing content
		$content->update($form->strings());

		$this->model->storage()->update($this->id, $language, $content->toArray());
	}
}
