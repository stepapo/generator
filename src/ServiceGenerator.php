<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Strings;


class ServiceGenerator
{
	private string $namespace;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
	) {
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Lib";
	}


	public function generateService(): PhpFile
	{
		$class = (new ClassType("{$this->name}"))
			->addMember((new Method('__construct'))->setPublic());

		$namespace = (new PhpNamespace("{$this->namespace}"))
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}
}
