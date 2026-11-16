# ADR 0013 — Static analysis on the minimum supported PHP version

Status: Accepted (2026-11-16). Departure from the reference Makefile; same decision as
urssaf-attestations-verification-code-securite ADR 0007.

## Context

PHPStan includes `tomasvotruba/type-coverage` with `constant: 100`. The rule reads the PHP version running PHPStan: on
PHP 8.3+ it requires typed class constants, a parse error on PHP 8.2, the supported floor. On the untouched skeleton,
`make ci PHP_VERSION=8.4` fails with six `typeCoverage.constantTypeCoverage` errors although `make ci` is green.

## Decision

- The Makefile `phpstan` target always runs in the PHP 8.2 image (`PHP_ANALYSIS`), whatever `PHP_VERSION` is, like the
  CI `quality` jobs. Every other step of `make ci PHP_VERSION=x` runs on the requested version.
- No PHPStan threshold or rule is changed.

## Consequences

- When PHP 8.2 support is dropped, constants get native types and the pin is removed.
