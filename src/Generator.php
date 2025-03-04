<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\FileSystem;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Mapper\Mapper;
use Nextras\Orm\Repository\Repository;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;


class Generator
{
	public const string MODE_ADD = 'add';
	public const string MODE_REMOVE = 'remove';


	public function __construct(
		protected string $appNamespace = 'App',
		protected string $appDir = 'app',
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
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Presenter";
		$lname = lcfirst($name);
		$this->createFile("$basePath/$name/{$name}Template.php", $generator->generateTemplate($baseTemplate));
		$this->createFile("$basePath/$name/{$name}Presenter.php", $generator->generatePresenter($basePresenter));
		$this->createFile("$basePath/$name/$lname.latte", $generator->generateLatte());
	}


	public function removePresenter(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Presenter/$name");
	}


	public function createComponent(
		string $name,
		?string $module = null,
		?string $entity = null,
		bool $withTemplateName = false,
		string $type = null,
		string $factory = null,
		string $baseControl = Control::class,
		string $baseTemplate = Template::class,
	) {
		$generator = new ComponentGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			entity: $entity,
			withTemplateName: $withTemplateName,
			type: $type,
			factory: $factory,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Control";
		$lname = lcfirst($name);
		$this->createFile("$basePath/$name/{$name}Template.php", $generator->generateTemplate($baseTemplate));
		$this->createFile("$basePath/$name/I{$name}Control.php", $generator->generateFactory());
		$this->createFile("$basePath/$name/{$name}Control.php", $generator->generateControl($baseControl));
		$this->createFile("$basePath/$name/$lname.latte", $generator->generateLatte());
		if ($type === ComponentGenerator::TYPE_DATASET) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->generateDatasetNeon());
		}
		if ($type === ComponentGenerator::TYPE_MENU) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->generateMenuNeon());
		}
	}


	public function removeComponent(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Control/$name");
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
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Model";
		$this->createFile("$basePath/$name/$name.php", $generator->generateEntity($baseEntity));
		$this->createFile("$basePath/$name/{$name}Mapper.php", $generator->generateMapper($baseMapper));
		$this->createFile("$basePath/$name/{$name}Repository.php", $generator->generateRepository($baseRepository));
		if ($withConventions) {
			$this->createFile("$basePath/$name/{$name}Conventions.php", $generator->generateConventions());
		}
		$modelPath = "$this->appDir/Model/Orm.php";
		$this->createFile($modelPath, $generator->generateUpdatedModel($modelPath));
	}


	public function removeModel(string $name, ?string $module)
	{
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			mode: Generator::MODE_REMOVE,
		);
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Model/$name");
		$modelPath = "$this->appDir/Model/Orm.php";
		$this->createFile($modelPath, $generator->generateUpdatedModel($modelPath));
	}


	public function getEntityComments(Table $table, ?string $module = null): ?string
	{
		$name = $table->getPhpName();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Model";
		$entityPath = "$basePath/$name/$name.php";
		return $generator->getEntityComments($entityPath, $table);
	}


	public function updateEntity(Table $table, ?string $module = null)
	{
		$name = $table->getPhpName();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->generateEntityProperties($entityPath, $table));
	}


	public function updateEntityManyHasMany(Foreign $from, Foreign $to, bool $isMain = false, ?string $module = null)
	{
		$name = $from->getPhpTable();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->generateEntityPropertyManyHasMany($entityPath, $from, $to, $isMain));
	}


	public function updateEntityOneHasMany(Table $table, Foreign $foreign, ?string $module = null)
	{
		$name = $foreign->getPhpTable();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->generateEntityPropertyOneHasMany($entityPath, $table, $foreign));
	}


	public function updateEntitySortComments(Table $table, ?string $module = null)
	{
		$name = $table->getPhpName();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->sortEntityProperties($entityPath));
	}


	public function createService(
		string $name,
		?string $module = null,
	) {
		$generator = new ServiceGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Lib";
		$this->createFile("$basePath/{$name}.php", $generator->generateService());
	}


	public function removeService(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Lib/$name.php");
	}


	public function createCommand(
		string $name,
		?string $module = null,
	) {
		$generator = new CommandGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Command";
		$this->createFile("$basePath/{$name}.php", $generator->generateCommand());
	}


	public function removeCommand(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Command/$name.php");
	}


	protected function createFile(string $path, PhpFile|string|null $file = null): void
	{
		FileSystem::write($path, $file instanceof PhpFile ? (new CustomPrinter())->printFile($file) : (string) $file, mode: null);
	}
}
