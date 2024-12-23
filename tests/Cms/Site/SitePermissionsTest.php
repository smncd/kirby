<?php

namespace Kirby\Cms;

use Kirby\TestCase;

class SitePermissionsTest extends TestCase
{
	public static function actionProvider(): array
	{
		return [
			['access'],
			['changeTitle'],
			['update'],
		];
	}

	/**
	 * @dataProvider actionProvider
	 */
	public function testWithAdmin($action)
	{
		$kirby = new App([
			'roots' => [
				'index' => '/dev/null'
			]
		]);

		$kirby->impersonate('kirby');

		$site  = new Site();
		$perms = $site->permissions();

		$this->assertTrue($perms->can($action));
	}

	/**
	 * @dataProvider actionProvider
	 */
	public function testWithNobody($action)
	{
		$kirby = new App([
			'roots' => [
				'index' => '/dev/null'
			]
		]);

		$site  = new Site();
		$perms = $site->permissions();

		$this->assertFalse($perms->can($action));
	}

	/**
	 * @covers \Kirby\Cms\ModelPermissions::can
	 */
	public function testCaching()
	{
		$app = new App([
			'roles' => [
				[
					'name' => 'editor',
					'permissions' => [
						'site' => [
							'access' => false
						],
					]
				]
			],
			'roots' => [
				'index' => '/dev/null'
			],
			'users' => [
				['id' => 'bastian', 'role' => 'editor'],

			]
		]);

		$app->impersonate('bastian');

		$site = $app->site();

		$this->assertFalse($site->permissions()->can('access'));
		$this->assertFalse($site->permissions()->can('access'));
	}
}
