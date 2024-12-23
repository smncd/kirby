<?php

namespace Kirby\Cms;

use Kirby\Toolkit\A;

/**
 * ModelPermissions
 *
 * @package   Kirby Cms
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
abstract class ModelPermissions
{
	protected string $category;
	protected array $options;

	protected static array $cache = [];

	public function __construct(protected ModelWithContent|Language $model)
	{
		$this->options = match (true) {
			$model instanceof ModelWithContent => $model->blueprint()->options(),
			default                            => []
		};
	}

	public function __call(string $method, array $arguments = []): bool
	{
		return $this->can($method);
	}

	/**
	 * Improved `var_dump` output
	 * @codeCoverageIgnore
	 */
	public function __debugInfo(): array
	{
		return $this->toArray();
	}

	/**
	 * Can be overridden by specific child classes
	 * to return a model-specific value used to
	 * cache a once determined permission in memory
	 * @codeCoverageIgnore
	 */
	protected function cacheKey(): string
	{
		return '';
	}

	/**
	 * Returns whether the current user is allowed to do
	 * a certain action on the model
	 *
	 * @param bool $default Will be returned if $action does not exist
	 */
	public function can(
		string $action,
		bool $default = false
	): bool {
		$user   = $this->user();
		$userId = $user->id();
		$role   = $user->role()->id();

		// users with the `nobody` role can do nothing
		// that needs a permission check
		if ($role === 'nobody') {
			return false;
		}

		// check for a custom `can` method
		// which would take priority over any other
		// role-based permission rules
		if (
			method_exists($this, 'can' . $action) === true &&
			$this->{'can' . $action}() === false
		) {
			return false;
		}

		// the almighty `kirby` user can do anything
		if ($userId === 'kirby' && $role === 'admin') {
			return true;
		}

		// cache the often-used read permissions;
		// note that the custom `can` method check happens before,
		// so dynamic code still has the chance to be run each time
		if ($action === 'access' || $action === 'list') {
			$category = $this->category();
			$cacheKey = $category . '.' . $action . '/' . $this->cacheKey() . '/' . $role;

			if (isset(static::$cache[$cacheKey]) === true) {
				return static::$cache[$cacheKey];
			}

			return static::$cache[$cacheKey] = $this->canFromOptionsAndRole($action, $role, $default);
		}

		// determine all other permissions dynamically
		// TODO: caching these is generally possible, but currently makes many unit tests break
		return $this->canFromOptionsAndRole($action, $role, $default);
	}

	/**
	 * Main logic for `can()` that can be cached
	 */
	protected function canFromOptionsAndRole(
		string $action,
		string $role,
		bool $default = false
	): bool {
		// evaluate the blueprint options block
		if (isset($this->options[$action]) === true) {
			$options = $this->options[$action];

			if ($options === false) {
				return false;
			}

			if ($options === true) {
				return true;
			}

			if (
				is_array($options) === true &&
				A::isAssociative($options) === true
			) {
				if (isset($options[$role]) === true) {
					return $options[$role];
				}

				if (isset($options['*']) === true) {
					return $options['*'];
				}
			}
		}

		$permissions = $this->user()->role()->permissions();
		return $permissions->for($this->category(), $action, $default);
	}

	/**
	 * Returns whether the current user is not allowed to do
	 * a certain action on the model
	 *
	 * @param bool $default Will be returned if $action does not exist
	 */
	public function cannot(
		string $action,
		bool $default = true
	): bool {
		return $this->can($action, !$default) === false;
	}

	/**
	 * Can be overridden by specific child classes
	 * if the permission category needs to be dynamic
	 */
	protected function category(): string
	{
		return $this->category;
	}

	public function toArray(): array
	{
		$array = [];

		foreach ($this->options as $key => $value) {
			$array[$key] = $this->can($key);
		}

		return $array;
	}

	/**
	 * Returns the currently logged in user
	 */
	protected function user(): User
	{
		return $this->model->kirby()->user() ?? User::nobody();
	}
}
