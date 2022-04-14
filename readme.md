# Generator

Tool for generating empty Nette Presenters, Components and Services with basic structure and Nextras ORM model files.

## Usage

### Presenter

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Stepapo\Generator\Generator;
use Nette\Bridges\ApplicationLatte\Template;

$options = getopt(null, ['appNamespace:', 'appDir:', 'name:', 'module:']);

$generator = new Generator(
	appNamespace: $options['rootNamespace'] ?? 'App',
	appDir: __DIR__ . '/../' . ($options['rootPath'] ?? 'app'),
);

$generator->createPresenter(
	name: $options['name'],
	module: $options['module'] ?? null,
);
```

### Component

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Stepapo\Generator\Generator;

$options = getopt(null, [
	'appNamespace:',
	'appDir:',
	'name:',
	'module:',
	'entityName:',
	'withTemplateName:'
]);

$appNamespace = $options['rootNamespace'] ?? 'App';
$appDir = $options['rootPath'] ?? 'app';
$name = $options['name'];
$module = $options['module'] ?? null;
$entityName = $options['entityName'] ?? null;
$withTemplateName = $options['withTemplateName'] ?? false;

(new Generator($appNamespace, __DIR__ . '/../' . $appDir))
	->createComponent($name, $module, $entityName, $withTemplateName);
```

### Model

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Stepapo\Generator\Generator;

$options = getopt(null, [
	'appNamespace:',
	'appDir:',
	'name:',
	'module:',
	'withConventions:',
]);

$appNamespace = $options['rootNamespace'] ?? 'App';
$appDir = $options['rootPath'] ?? 'app';
$name = $options['name'];
$module = $options['module'] ?? null;
$withConventions = $options['withConventions'] ?? false;

(new Generator($appNamespace, __DIR__ . '/../' . $appDir))
	->createModel($name, $module, $withConventions);
```

### Service

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Stepapo\Generator\Generator;

$options = getopt(null, [
	'appNamespace:',
	'appDir:',
	'name:',
	'module:'
]);

$appNamespace = $options['rootNamespace'] ?? 'App';
$appDir = $options['rootPath'] ?? 'app';
$name = $options['name'];
$module = $options['module'] ?? null;

(new Generator($appNamespace, __DIR__ . '/../' . $appDir))
	->createService($name, $module);
```



