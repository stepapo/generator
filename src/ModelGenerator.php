<?php

declare(strict_types=1);

namespace Stepapo\Generator;

use Nette\InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Arrays;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Tracy\Dumper;
use Webovac\Generator\CmsGenerator;


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
		private string $mode = CmsGenerator::MODE_ADD,
	) {
		$this->lname = lcfirst($this->name);
		$this->uname = StringHelper::underscore($name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$this->name}";
	}


	public function generateEntity(string $base, ?Table $table = null): PhpFile
	{
		$class = (new ClassType("{$this->name}"))
			->setExtends($base);
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


	public function generateUpdatedModel(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		$type = "{$this->namespace}\\{$this->name}Repository";
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comment = "@property-read {$this->name}Repository \${$this->lname}Repository";
		$comments = explode("\n", $class->getComment());
		if (!in_array($comment, $comments, true)) {
			$comments[] = $comment;
		}
		if ($this->mode === CmsGenerator::MODE_ADD && !in_array($comment, $comments, true)) {
			$namespace->addUse($type);
			$comments[] = $comment;
		} elseif ($this->mode === CmsGenerator::MODE_REMOVE && in_array($comment, $comments, true)) {
			$namespace->removeUse($type);
			$comments = array_diff($comments, [$comment]);
		}
		sort($comments);
		$class->setComment(implode("\n", $comments));

		return $file;
	}


	public function generateEntityProperties(string $path, Table $table): PhpFile
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("Model with name '$this->name' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		foreach ($comments as $key => $comment) {
			if (str_contains($comment, '@property')) {
				unset($comments[$key]);
			}
		}
		foreach ($table->columns as $column) {
			$foreign = $table->foreignKeys[$column->name] ?? null;
			$c = [];
			$c['property'] = '@property';
			$c['type'] = $column->getPhpType($foreign);
			if ($column->type === 'datetime') {
				$namespace->addUse(DateTimeImmutable::class);
			}
			$c['name'] = "\${$column->getPhpName($foreign)}";
			if (($default = $column->getPhpDefault()) !== null) {
				$c['default'] = "{default $default}";
			}
			$isPrimary = $table->primaryKey && in_array($column->name, $table->primaryKey->columns, true);
			if ($isPrimary) {
				$c['primary'] = "{primary}";
			}
			if ($foreign) {
				$c['foreign'] = "{m:1 {$foreign->getPhpTable()}" . ($foreign->reverseName ? "::\$$foreign->reverseName" : ", oneSided=true") . "}";
				if ($table->name !== $foreign->table) {
					$namespace->addUse($this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$foreign->getPhpTable()}\\{$foreign->getPhpTable()}");
				}
			}
			$comment = implode(' ', $c);
			if (!in_array($c, $comments, true)) {
				$comments[] = $comment;
			}
		}
		$class->setComment(implode("\n", $comments));
		return $file;
	}


	public function generateEntityPropertyManyHasMany(string $path, Foreign $from, Foreign $to, bool $isMain = false): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		$namespace->addUse($this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$to->getPhpTable()}\\{$to->getPhpTable()}");
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		$c['property'] = '@property';
		$c['type'] = "ManyHasMany|{$to->getPhpTable()}[]";
		$c['name'] = "\$" . ($from->reverseName ? "$from->reverseName" : (StringHelper::camelize($to->table) . "s"));
		$c['foreign'] = "{m:m {$to->getPhpTable()}" . ($to->reverseName ? "::\$$to->reverseName" : "") . ($to->reverseOrder ? ", orderBy=$to->reverseOrder" : "") . ($isMain ? ", isMain=true" : "") . ($to->reverseName ? "" : ", oneSided=true") ."}";
		if ($from->table !== $to->table) {
			$namespace->addUse($this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$to->getPhpTable()}\\{$to->getPhpTable()}");
		}
		$comment = implode(' ', $c);
		if (!in_array($c, $comments, true)) {
			$comments[] = $comment;
		}
		$class->setComment(implode("\n", $comments));
		$namespace->addUse(ManyHasMany::class);
		return $file;
	}


	public function generateEntityPropertyOneHasMany(string $path, Table $table, Foreign $foreign): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		$namespace->addUse($this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$table->getPhpName()}\\{$table->getPhpName()}");
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		$c['property'] = '@property';
		$c['type'] = "OneHasMany|{$table->getPhpName()}[]";
		$c['name'] = "\$" . ($foreign->reverseName ? "$foreign->reverseName" : (StringHelper::camelize($table->name) . "s"));
		$c['foreign'] = "{1:m {$table->getPhpName()}::$" . StringHelper::camelize(str_replace('_id', '', $foreign->keyColumn)) . ($foreign->reverseOrder ? ", orderBy=$foreign->reverseOrder" : "") . "}";
		if ($table->name !== $foreign->table) {
			$namespace->addUse($this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Model\\{$table->getPhpName()}\\{$table->getPhpName()}");
		}
		$comment = implode(' ', $c);
		if (!in_array($c, $comments, true)) {
			$comments[] = $comment;
		}
		$class->setComment(implode("\n", $comments));
		$namespace->addUse(OneHasMany::class);
		return $file;
	}


	public function sortEntityProperties(string $path): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		foreach ($comments as $comment) {
			if (str_contains($comment, '{primary}')) {
				$c['primary'][] = $comment;
			} elseif (str_contains($comment, 'DateTimeImmutable')) {
				$c['date'][] = $comment;
			} elseif (str_contains($comment, 'm:1')) {
				$c['m:1'][] = $comment;
			} elseif (str_contains($comment, '1:m')) {
				$c['1:m'][] = $comment;
			} elseif (str_contains($comment, 'm:m')) {
				$c['m:m'][] = $comment;
			} elseif (str_contains($comment, '@property')) {
				$c['simple'][] = $comment;
			} else {
				$c['other'][] = $comment;
			}
		}
		$comments = [];
		foreach (['primary', 'simple', 'date', 'm:1', '1:m', 'm:m', 'other'] as $type) {
			if (isset($c[$type])) {
				sort($c[$type]);
				$comments = $comments ? array_merge($comments, [''], $c[$type]) : $c[$type];
			}
		}
		$class->setComment(implode("\n", $comments));
		return $file;
	}
}
