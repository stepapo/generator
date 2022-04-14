<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Strings;


class ComponentGenerator
{
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

		$renderMethod->addBody("\$this->template->setFile(__DIR__ . '/" . ($this->withTemplateName ? "' . \$this->templateName . '" : $this->lname) . ".latte');");

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
		return <<<EOT
{templateType {$this->namespace}\\{$this->name}Template}

EOT;
	}
}
