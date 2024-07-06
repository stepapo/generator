<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use App\Model\Activity\Activity;
use Nette\Application\UI\Form;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use Stepapo\Dataset\Control\Dataset\DatasetControl;
use Stepapo\Menu\UI\Menu;


class ComponentGenerator
{
	public const TYPE_FORM = 'form';
	public const TYPE_DATASET = 'dataset';
	public const TYPE_MENU = 'menu';

	private string $lname;
	private string $namespace;
	private string $lentityName;
	private string $entity;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
		private ?string $entityName = null,
		private bool $withTemplateName = false,
		private ?string $type = null,
		private ?string $factory = null,
	) {
		if ($this->entityName) {
			$this->lentityName = lcfirst($this->entityName);
			$this->entity = "{$this->appNamespace}\Model\\{$this->entityName}\\{$this->entityName}";
		}
		$this->lname = lcfirst($name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Control\\{$this->name}";
	}


	public function generateTemplate(string $base): PhpFile
	{
		$class = (new ClassType("{$this->name}Template"))
			->setExtends($base);

		$namespace = (new PhpNamespace("{$this->namespace}"))
			->addUse($base)
			->add($class);

		if ($this->entityName) {
			$class->addProperty($this->lentityName)
				->setPublic()
				->setType($this->entity);
			$namespace->addUse($this->entity);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateControl(string $base): PhpFile
	{
		$constructMethod = (new Method('__construct'))
			->setPublic();
		$renderMethod = (new Method('render'))
			->setPublic()
			->setReturnType('void');

		$class = (new ClassType("{$this->name}Control"))
			->setExtends($base)
			->addComment("@property {$this->name}Template \$template")
			->addMember($constructMethod)
			->addMember($renderMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($base)
			->add($class);

		if ($this->entityName) {
			$constructMethod
				->addPromotedParameter($this->lentityName)
				->setPrivate()
				->setType($this->entity);
			$renderMethod->addBody("\$this->template->{$this->lentityName} = \$this->{$this->lentityName};");
			$namespace->addUse($this->entity);
		}

		if ($this->withTemplateName) {
			$constructMethod
				->addPromotedParameter('templateName')
				->setType('string');
		}

		if ($this->type) {
			if ($this->factory) {
				$constructMethod
					->addPromotedParameter($this->type . 'Factory')
					->setPrivate()
					->setType($this->factory);
				$namespace->addUse($this->factory);
			}
			switch ($this->type) {
				case self::TYPE_FORM:
					$this->createFormMethods($namespace, $class);
					break;
				case self::TYPE_DATASET:
					$this->createDatasetMethods($namespace, $class);
					break;
				case self::TYPE_MENU:
					$this->createMenuMethods($namespace, $class);
					break;
				default:
					$this->createComponentMethods($namespace, $class);
					break;
			}
		}

		$renderMethod->addBody("\$this->template->render(__DIR__ . '/" . ($this->withTemplateName ? "' . \$this->templateName . '" : $this->lname) . ".latte');");

		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateFactory(): PhpFile
	{
		$createMethod = (new Method('create'))
			->setReturnType("{$this->namespace}\\{$this->name}Control");

		$class = (new InterfaceType("I{$this->name}Control"))
			->addMember($createMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		if ($this->entityName) {
			$createMethod
				->addParameter($this->lentityName)
				->setType($this->entity);
			$namespace->addUse($this->entity);
		}

		if ($this->withTemplateName) {
			$createMethod
				->addParameter('templateName')
				->setType('string');
		}

		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateLatte(): string
	{
		$latte = <<<EOT
{templateType {$this->namespace}\\{$this->name}Template}


EOT;
		if ($this->type) {
			$latte .= <<<EOT
{control {$this->type}}

EOT;
		}

		return $latte;
	}


	public function generateDatasetNeon(): string
	{
		return <<<EOT
collection: %collection%
repository: %repository%
columns:

EOT;
	}


	public function generateMenuNeon(): string
	{
		return <<<EOT
buttons:

EOT;
	}


	private function createFormMethods(PhpNamespace $namespace, ClassType $class): void
	{
		$createComponentMethod = (new Method('createComponentForm'))
			->setPublic()
			->setReturnType(Form::class)
			->addBody(
				$this->factory
					? "\$form = \$this->{$this->type}Factory->create();"
					: "\$form = new Form;"
			)
			->addBody("\$form->onSuccess[] = [\$this, 'formSucceeded'];")
			->addBody("return \$form;");

		$formSucceededMethod = (new Method('formSucceeded'))
			->setPublic()
			->setReturnType('void');
		$formSucceededMethod->addParameter('form')->setType(Form::class);
		$formSucceededMethod->addParameter('values')->setType(ArrayHash::class);

		$class
			->addMember($createComponentMethod)
			->addMember($formSucceededMethod);
		$namespace
			->addUse(Form::class)
			->addUse(ArrayHash::class);
	}


	private function createDatasetMethods(PhpNamespace $namespace, ClassType $class): void
	{
		$factoryBody = <<<EOT
	__DIR__ . '/{$this->lname}.neon',
	[
		'collection' => '',
		'repository' => '',
	],
EOT;

		$createComponentMethod = (new Method('createComponentDataset'))
			->setPublic()
			->setReturnType(DatasetControl::class)
			->addBody(
				$this->factory
					? "\$dataset = \$this->{$this->type}Factory->create(\n{$factoryBody}\n);"
					: "\$dataset = Dataset::createFromNeon(\n{$factoryBody}\n);"
			)
			->addBody("return \$dataset;");

		$class
			->addMember($createComponentMethod);
		$namespace->addUse(DatasetControl::class);
	}


	private function createMenuMethods(PhpNamespace $namespace, ClassType $class): void
	{
		$factoryBody = "__DIR__ . '/{$this->lname}.neon'";
		$createComponentMethod = (new Method('createComponentMenu'))
			->setPublic()
			->setReturnType(Menu::class)
			->addBody(
				$this->factory
					? "\$menu = \$this->{$this->type}Factory->create({$factoryBody});"
					: "\$menu = Menu::createFromNeon({$factoryBody});"
			)
			->addBody("return \$menu;");

		$class
			->addMember($createComponentMethod);
		$namespace->addUse(Menu::class);
	}


	private function createComponentMethods(PhpNamespace $namespace, ClassType $class): void
	{
		$createComponentMethod = (new Method('createComponent' . ucfirst($this->type)))
			->setPublic()
			->addBody(
				$this->factory
					? "\${$this->type} = \$this->{$this->type}Factory->create();"
					: "\${$this->type} = new " . ucfirst($this->type) . ";"
			)
			->addBody("return \${$this->type};");

		$class
			->addMember($createComponentMethod);
	}
}
