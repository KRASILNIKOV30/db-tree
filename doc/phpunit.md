# Установка phpunit

Установка через composer:

```bash
composer require --dev phpunit/phpunit=8.5.23
```

# Конфигурирование phpunit

Добавьте в корне проекта файл phpunit.xml:

```xml
<?xml version="1.0" encoding="UTF-8"?>

<!-- https://phpunit.readthedocs.io/en/latest/configuration.html -->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         backupGlobals="false"
         colors="true"
         bootstrap="tests/bootstrap_phpunit.php"
>
    <php>
        <ini name="error_reporting" value="-1"/>
    </php>

    <testsuites>
        <testsuite name="Project Test Suite">
            <directory suffix=".php">tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Создайте каталог `tests/` и в нём файл `bootstrap_phpunit.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
```

# Запуск тестов

Написать первый тест можно по документации: https://docs.phpunit.de/en/8.5/writing-tests-for-phpunit.html

Для запуска тестов используйте команду:

```bash
vendor/bin/phpunit
```

# Отладка тестов в XDebug

Для активации XDebug при выполнении консольных команд можно установить переменные окружения.

В консоли Windows:
```bash
set XDEBUG_CONFIG="idekey=123"
set PHP_IDE_CONFIG=serverName=localhost
```

В консоли Linux:
```bash
export XDEBUG_CONFIG="idekey=123"
export PHP_IDE_CONFIG=serverName=localhost
```

После чего запустить тесты:

```bash
vendor/bin/phpunit

# Запуск только тест-кейсов из классов, содержащих в названии AdjacencyList
vendor/bin/phpunit --filter AdjacencyList

# Запуск только тест-кейсов из классов, содержащих в названии NestedSet
vendor/bin/phpunit --filter NestedSet
```

