# URSSAF "API portail Plateforme numérique" for PHP

<!-- the-bearded-bear:badges:start -->
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
<!-- the-bearded-bear:badges:end -->

`thebeardedbear/urssaf-plateforme-numerique` — client, domain model and Symfony integration for the URSSAF "portail
Plateforme numérique" APIs, used by digital platforms for the withholding at source of the social contributions of
auto-entrepreneurs: monthly identification files, declaration / regularisation files, processing and payment status,
report downloads and notification feed. One package covers two catalogue entries sharing the same resources:
**Échanges réels** and **Bac à sable** (technical test API).

Namespace: `TheBeardedBear\UrssafPlateformeNumerique`.

Developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

> 🚧 **Skeleton — not implemented yet.** This repository currently contains the tooling and the specification only.
> The implementation follows [docs/roadmap.md](docs/roadmap.md).

## Scope

| Operation | Endpoint | Échanges réels | Bac à sable |
|---|---|---|---|
| Upload an identification file | `POST /platforms/{platformId}/identifications/upload` | yes | yes |
| Get an identification submission | `GET /platforms/{platformId}/identifications/{urssafRef}` | yes | yes |
| Search identification submissions | `GET /platforms/{platformId}/identifications` | yes | yes |
| Download an identification result file | `GET /platforms/{platformId}/identifications/{urssafRef}/file/{fileRef}` | yes | yes |
| Upload a declaration / regularisation file | `POST /platforms/{platformId}/declarations/upload?type=…` | yes | yes |
| Get a declaration submission | `GET /platforms/{platformId}/declarations/{urssafRef}` | yes | yes |
| Search declaration submissions | `GET /platforms/{platformId}/declarations` | yes | yes |
| Download a declaration report file | `GET /platforms/{platformId}/declarations/{urssafRef}/file/{fileRef}` | yes | yes |
| Notification feed | `GET /platforms/{platformId}/news-feed` | yes | no |

OAuth2 client credentials (prior registration on the "portail Plateforme numérique" required):

| Target | Base URL | Scope |
|---|---|---|
| Échanges réels, production | `https://api.urssaf.fr/api-odspn-be-platforms/v1` | `ods.pn.platforms` |
| Échanges réels, sandbox | `https://api-edi.urssaf.fr/api-odspn-be-platforms/v1` | `ods.pn.platforms` |
| Bac à sable (test API) | `https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1` | `ods.pn.platforms.sandbox` |

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/roadmap.md](docs/roadmap.md) — planned architecture and order of work.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal.
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue pages: https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-echanges-reels and
  https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-bac-a-sable

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, hadolint, guard, coverage, infection
make test        # unit + functional tests
make bench       # benchmarks (PHPBench, tests/Benchmark), added with the implementation
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

Once the repository is public, `main` publishes its quality proofs, linked from the badges at the top of this file: the
coverage report on Codecov, the full mutation report on the Stryker Dashboard, the SonarQube Cloud quality gate and the
OpenSSF Scorecard. The benchmarks (`tests/Benchmark`, PHPBench in `tools/phpbench`, out of the package dependencies)
run on every pull request against the base commit, and the job fails on a regression of more than 20 %.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

<!-- the-bearded-bear:start -->
## Other URSSAF packages

The same architecture, quality gate and conventions, one package per URSSAF API:

| API | Package | Status |
|---|---|---|
| Tiers de prestations (immediate tax-credit advance) | [`thebeardedbear/urssaf-tiers-prestations`](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations) | Released (Packagist) |
| Attestations Vérification Code Sécurité (certificate verification) | [`thebeardedbear/urssaf-attestations-verification-code-securite`](https://github.com/TheBeardedBearSAS/urssaf-attestations-verification-code-securite) | Released (Packagist) |
| DPAE (pre-employment declaration) | `thebeardedbear/urssaf-dpae` | Coming soon |
| Mobilité internationale (posting of workers abroad) | `thebeardedbear/urssaf-mobilite-internationale` | Coming soon |
| Tierce Déclaration auto-entrepreneur | `thebeardedbear/urssaf-tierce-declaration-auto-entrepreneur` | Coming soon |
| Tierce Déclaration CESU | `thebeardedbear/urssaf-tierce-declaration-cesu` | Coming soon |
| Tierce Déclaration PAJE | `thebeardedbear/urssaf-tierce-declaration-paje` | Coming soon |

## Professional support

This package is developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).
If you need help with an URSSAF integration, The Bearded Bear can help with:

- the URSSAF habilitation and homologation process (subscription, sandbox tests, move to production);
- integrating the package into your application (Symfony, Doctrine or framework-free);
- support with a guaranteed response time, including the follow-up of URSSAF API changes;
- custom developments on top of the package.

Contact: [the-bearded-bear.com](https://the-bearded-bear.com).
<!-- the-bearded-bear:end -->

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
