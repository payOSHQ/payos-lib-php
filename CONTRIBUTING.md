# Contributing

## Setting up the environment

Install [`composer`](https://getcomposer.org/) and [`php`](https://www.php.net/) (version 8.2 or higher), then run:

```bash
composer install
```

This will install all the required dependencies.

## Adding and running examples

You can run, modify and add new examples in `examples/` directory.

```bash
php examples/<example>.php
```

## Linting and formatting

This repository uses[PHP CS Fixer](https://cs.symfony.com/) to format and [PHPStan](https://phpstan.org/) to lint the code in the repository.

```bash
composer run lint
composer run format
```

## Publishing and release

Before create release, make sure update the version inside `composer.json`.

```bash
# Edit composer.json and change version
composer run sync-version
```

Then create new tag and push to remote. Packagist will automatically update the package when new tag is created.
