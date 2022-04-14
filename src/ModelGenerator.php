<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Arrays;
use Nextras\Orm\StorageReflection\StringHelper;


class ModelGenerator
{
	private string $lname;
	private string $uname;
	private string $namespace;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
		private bool $withConventions = false,
	) {
		$this->lname = lcfirst($this->name);
		$this->uname = StringHelper::underscore($name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$this->name}";
	}


	public function generateEntity(string $base): PhpFile
	{
		$class = (new ClassType("{$this->name}"))
			->setExtends($base)
			->addComment("@property int \$id {primary}");

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($base)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateMapper(string $base): PhpFile
	{
		$getTableNameMethod = (new Method('getTableName'))
			->setPublic()
			->setReturnType('string')
			->setBody("return '{$this->uname}';");

		$class = (new ClassType("{$this->name}Mapper"))
			->setExtends($base)
			->addMember($getTableNameMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($base)
			->add($class);

		if ($this->withConventions) {
			$conventions = 'Nextras\Orm\Mapper\Dbal\Conventions\IConventions';
			$createConventionsMethod = (new Method("{$this->name}Conventions"))
				->setProtected()
				->setReturnType($conventions)
				->setBody(<<<EOT
return new {$this->name}Conventions(
	\$this->createInflector(),
	\$this->connection,
	\$this->getTableName(),
	\$this->getRepository()->getEntityMetadata(),
	\$this->cache,
);
EOT
				);
			$class->addMember($createConventionsMethod);
			$namespace->addUse($conventions);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateRepository(string $base): PhpFile
	{
		$getEntityClassNamesMethod = (new Method('getEntityClassNames'))
			->setPublic()
			->setStatic()
			->setReturnType('array')
			->setBody("return [{$this->name}::class];");

		$class = (new ClassType("{$this->name}Repository"))
			->setExtends($base)
			->addMember($getEntityClassNamesMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($base)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateConventions(): PhpFile
	{
		$conventions = 'Nextras\Orm\Mapper\Dbal\Conventions\Conventions';
		$getStoragePrimaryKeyMethod = (new Method('getStoragePrimaryKey'))
			->setPublic()
			->setReturnType('array')
			->setBody("return [];");

		$getDefaultMappingsMethod = (new Method('getDefaultMappings'))
			->setPublic()
			->setReturnType('array')
			->setBody(<<<EOT
return [
	[
	
	],
	[
	
	],
	[]
];
EOT
			);

		$class = (new ClassType("{$this->name}Conventions"))
			->setExtends($conventions)
			->addMember($getStoragePrimaryKeyMethod)
			->addMember($getDefaultMappingsMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($conventions)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedModel(string $path)
	{
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		$namespace->addUse("{$this->namespace}\\{$this->name}Repository");

		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comment = "@property-read {$this->name}Repository \${$this->lname}Repository";
		$comments = explode("\n", $class->getComment());
		if (!in_array($comment, $comments, true)) {
			$comments[] = $comment;
		}
		sort($comments);
		$class->setComment(implode("\n", $comments));

		return $file;
	}
}
