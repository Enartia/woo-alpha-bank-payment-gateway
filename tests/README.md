# Tests

Development tooling for the plugin. None of it ships in the release package (see `.distignore`).

## Setup

```sh
composer install
```

This installs the tools in `vendor/`, and WordPress and WooCommerce in `.wp-tests/`. Both directories are ignored by git. The dependency versions are resolved for PHP 7.4, the lowest version the plugin header allows.

The integration tests need a MySQL server on `127.0.0.1` with user `root` and no password. They create the database `enartia_tests_alphabank` themselves. Set `WP_TESTS_DB_HOST`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD` or `WP_TESTS_DB_NAME` to use something else. The test run drops every table in that database, so the name must contain `test`.

## Commands

| Command | What it runs |
|---|---|
| `composer check` | Everything below, as the CI workflow does |
| `composer lint` | `php -l` on every PHP file |
| `composer phpcs` | Security, correctness and PHP 7.4 compatibility sniffs (`phpcs.xml.dist`) |
| `composer phpstan` | Static analysis at level 5 (`phpstan.neon.dist`) |
| `composer test:unit` | Tests in `tests/Unit`, without WordPress |
| `composer test:integration` | Tests in `tests/Integration`, with WordPress and WooCommerce loaded |

## What goes where

- `tests/Unit` holds tests for logic that needs no WordPress: the digest and the encryption of the shared secret. `tests/Unit/stubs` has a stand-in for `WC_Payment_Gateway`, so the gateway classes load without WooCommerce.
- `tests/Integration` holds tests that need WordPress, WooCommerce or the database. They extend `WP_UnitTestCase`.

## Deprecations and notices fail the tests

Both suites turn PHP warnings, notices and deprecations into failures. In the integration suite, `WP_UnitTestCase` also fails a test when the code under test calls a deprecated WordPress or WooCommerce function. A test that covers such a call has to declare it with `$this->setExpectedDeprecated( 'function_name' )`.

## PHPStan baseline

`phpstan-baseline.neon` lists the 20 findings that existed when the analysis was introduced. New code must not add to it. When you fix one, regenerate the file:

```sh
vendor/bin/phpstan analyse --memory-limit=1G --generate-baseline phpstan-baseline.neon
```

## PHPCS

Formatting sniffs are off, because the code base uses four-space indentation. Warnings are printed but only errors fail the run.
