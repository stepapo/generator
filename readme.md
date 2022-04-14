# Generator

Tool for generating empty Nette Presenters, Components and Nextras ORM model files with basic structure.

## Usage

### Presenter

```php
$options = getopt(null, ['appNamespace:', 'appDir:', 'name:', 'module:']);

$generator = new Stepapo\Generator\Generator(
	appNamespace: $options['appNamespace'] ?? 'App',
	appDir: __DIR__ . '/../' . ($options['appDir'] ?? 'app'),
);

$generator->createPresenter(
	name: $options['name'],
	module: $options['module'] ?? null,
);
```

### Component

```php
$options = getopt(null, ['appNamespace:', 'appDir:', 'name:', 'module:', 'entityName:', 'withTemplateName:']);

$generator = new Stepapo\Generator\Generator(
	appNamespace: $options['appNamespace'] ?? 'App',
	appDir: __DIR__ . '/../' . ($options['appDir'] ?? 'app'),
);

$generator->createComponent(
	name: $options['name'], 
	module: $options['module'] ?? null, 
	entityName: $options['entityName'] ?? null, 
	withTemplateName: $options['withTemplateName'] ?? false,
);
```

### Model

```php
$options = getopt(null, ['appNamespace:', 'appDir:', 'name:', 'module:', 'withConventions:']);

$generator = new Stepapo\Generator\Generator(
	appNamespace: $options['appNamespace'] ?? 'App',
	appDir: __DIR__ . '/../' . ($options['appDir'] ?? 'app'),
);

$generator->createModel(
	name: $options['name'], 
	module: $options['module'] ?? null, 
	withConventions: $options['withConventions'] ?? false,
);
```

### Service

```php
$options = getopt(null, ['appNamespace:', 'appDir:', 'name:', 'module:']);

$generator = new Stepapo\Generator\Generator(
	appNamespace: $options['appNamespace'] ?? 'App',
	appDir: __DIR__ . '/../' . ($options['appDir'] ?? 'app'),
);

$generator->createService(
	name: $options['name'],
	module: $options['module'] ?? null,
);
```



