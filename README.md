yii2-migrate
============

Console Migration Command with multiple paths/aliases support.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
$ php composer.phar require webtoucher/yii2-migrate "*"
```

or add

```
"webtoucher/yii2-migrate": "*"
```

to the ```require``` section of your `composer.json` file.

Add the following in your console config:

```php
    return [
        ...
        'controllerMap' => [
            ...
            'migrate' => [
                'class' => 'webtoucher\migrate\controllers\MigrateController',
                // alias of modules directory
                // 'modulesPath' => '@app/modules',
                // additional aliases of migration directories
                // 'migrationLookup' => [],
            ],
            ...
        ],
        ...
    ];
```

## Usage

To create migration in common directory `@app/migration` use follow command:

```bash
$ php yii migrate/create comment_for_migration
```

To create migration in directory of the module `@app/modules/module_name/migration` use follow command:

```bash
$ php yii migrate/create comment_for_migration module_name
```

To run all migration from common directory and from directories of the modules use follow command:

```bash
$ php yii migrate
```
