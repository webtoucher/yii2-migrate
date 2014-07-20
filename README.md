yii2-migrate
============

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

> Note: You must set the `minimum-stability` to `dev` in the **composer.json** file in your application root folder before installation of this extension.

Either run

```
$ php composer.phar require webtoucher/yii2-migrate "dev-master"
```

or add

```
"webtoucher/yii2-migrate": "dev-master"
```

to the ```require``` section of your `composer.json` file.

Add the following in your console config:

```php
    return [
        ...
        'controllerMap' => [
            'migrate' => [
                'class' => 'webtoucher\migrate\controllers\MigrateController',
                // additional aliases of migration directories
                // 'modulesPath' => '@app/modules',
                // additional aliases of migration directories
                // 'migrationLookup' => [],
            ],
        ],
        ...
    ];
```
