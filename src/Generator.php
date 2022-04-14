<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\FileSystem;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Mapper\Mapper;
use Nextras\Orm\Repository\Repository;


class Generator
{
	public function __construct(
		private string $appNamespace = 'App',
		private string $appDir = 'app',
	) {}


	public function createPresenter(
		string $name,
		?string $module = null,
		string $basePresenter = Presenter::class,
		string $baseTemplate = Template::class,
	) {
		$generator = new PresenterGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
		);
		$basePath = "{$this->appDir}/" . ($module ? "Module/{$module}/" : '') . "Presenter";

		FileSystem::write(
			$basePath . "/$name/{$name}Template.php",
			(new CustomPrinter())->printFile($generator->generateTemplate($baseTemplate)),
		);

		FileSystem::write(
			$basePath . "/$name/{$name}Presenter.php",
			(new CustomPrinter())->printFile($generator->generatePresenter($basePresenter)),
		);

		$lname = lcfirst($name);

		FileSystem::write(
			$basePath . "/$name/{$lname}.latte",
			$generator->generateLatte(),
		);
	}


	public function createComponent(
		string $name,
		?string $module = null,
		?string $entityName = null,
		bool $withTemplateName = false,
		string $baseControl = Control::class,
		string $baseTemplate = Template::class,
	) {
		$generator = new ComponentGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			entityName: $entityName,
			withTemplateName: $withTemplateName,
		);
		$basePath = "{$this->appDir}/" . ($module ? "Module/{$module}/" : '') . "Control";

		FileSystem::write(
			$basePath . "/$name/{$name}Template.php",
			(new CustomPrinter())->printFile($generator->generateTemplate($baseTemplate)),
		);

		FileSystem::write(
			$basePath . "/$name/I{$name}Control.php",
			(new CustomPrinter())->printFile($generator->generateFactory()),
		);

		FileSystem::write(
			$basePath . "/$name/{$name}Control.php",
			(new CustomPrinter())->printFile($generator->generateControl($baseControl)),
		);

		$lname = lcfirst($name);

		FileSystem::write(
			$basePath . "/$name/{$lname}.latte",
			$generator->generateLatte(),
		);
	}


	public function createModel(
		string $name,
		?string $module = null,
		bool $withConventions = false,
		string $baseEntity = Entity::class,
		string $baseMapper = Mapper::class,
		string $baseRepository = Repository::class,
	) {
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			withConventions: $withConventions
		);
		$basePath = "{$this->appDir}/" . ($module ? "Module/{$module}/" : '') . "Model";

		FileSystem::write(
			$basePath . "/$name/{$name}.php",
			(new CustomPrinter())->printFile($generator->generateEntity($baseEntity)),
		);

		FileSystem::write(
			$basePath . "/$name/{$name}Mapper.php",
			(new CustomPrinter())->printFile($generator->generateMapper($baseMapper)),
		);

		FileSystem::write(
			$basePath . "/$name/{$name}Repository.php",
			(new CustomPrinter())->printFile($generator->generateRepository($baseRepository)),
		);

		if ($withConventions) {
			FileSystem::write(
				$basePath . "/$name/{$name}Conventions.php",
				(new CustomPrinter())->printFile($generator->generateConventions()),
			);
		}

		$modelPath = $basePath . "/Orm.php";

		FileSystem::write(
			$modelPath,
			(new CustomPrinter())->printFile($generator->generateUpdatedModel($modelPath)),
		);
	}


	public function createService(
		string $name,
		?string $module = null,
	) {
		$generator = new ServiceGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
		);
		$basePath = "{$this->appDir}/" . ($module ? "Module/{$module}/" : '') . "Lib";

		FileSystem::write(
			$basePath . "/{$name}.php",
			(new CustomPrinter())->printFile($generator->generateService()),
		);
	}
}
