<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Strings;


class PresenterGenerator
{
	private bool $isDetail;
	private string $lname;
	private string $namespace;
	private string $entityName;
	private string $lentityName;
	private string $entity;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
	) {
		$this->isDetail = str_contains($this->name, 'Detail');
		if ($this->isDetail) {
			$this->entityName = Strings::before($this->name, 'Detail');
			$this->lentityName = $this->entityName ? lcfirst($this->entityName) : null;
			$this->entity = "{$this->appNamespace}\Model\\{$this->entityName}\\{$this->entityName}";
		}
		$this->lname = lcfirst($name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Presenter\\{$this->name}";
	}


	public function generateTemplate(string $base): PhpFile
	{
		$class = (new ClassType("{$this->name}Template"))
			->setExtends($base);

		$namespace = (new PhpNamespace("{$this->namespace}"))
			->addUse($base)
			->add($class);

		if ($this->isDetail) {
			$class->addProperty($this->lentityName)
				->setPublic()
				->setType($this->entity);
			$namespace->addUse($this->entity);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generatePresenter(string $base): PhpFile
	{
		$actionMethod = (new Method('actionDefault'))
			->setPublic()
			->setReturnType('void');
		$renderMethod = (new Method('renderDefault'))
			->setPublic()
			->setReturnType('void');

		$class = (new ClassType("{$this->name}Presenter"))
			->setExtends($base)
			->addComment("@property {$this->name}Template \$template")
			->addMember((new Method('__construct'))->setPublic()->addBody("parent::__construct();"))
			->addMember($actionMethod)
			->addMember($renderMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($base)
			->add($class);

		if ($this->isDetail) {
			$actionMethod
				->addBody("\$this->{$this->lentityName} = \$this->orm->{$this->lentityName}Repository->getById(\$id);")
				->addBody("if (!\$this->{$this->lentityName}) {\n\t\$this->error();\n}")
				->addBody("\$this->title = \$this->{$this->lentityName}->title;")
				->addParameter('id')->setType('int');

			$renderMethod
				->addBody("\$this->template->{$this->lentityName} = \$this->{$this->lentityName};")
				->addParameter('id')->setType('int');

			$class->addProperty($this->lentityName)
				->setPrivate()
				->setType($this->entity)
				->setNullable();

			$namespace->addUse($this->entity);
		} else {
			$actionMethod->addBody("\$this->title = '';");
		}

		$renderMethod->addBody("\$this->template->setFile(__DIR__ . '/{$this->lname}.latte');");

		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateLatte(): string
	{
		return <<<EOT
{templateType {$this->namespace}\\{$this->name}Template}

{block content}

EOT;
	}
}
