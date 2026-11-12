# URSSAF "API portail Plateforme numérique" for PHP

`thebeardedbear/urssaf-plateforme-numerique` — client, domain model and Symfony integration for the URSSAF "portail
Plateforme numérique" APIs, used by digital platforms for the withholding at source of the social contributions of
auto-entrepreneurs: monthly identification files, declaration / regularisation files, processing and payment status,
report downloads and notification feed. One package covers two catalogue entries sharing the same resources:
**Échanges réels** and **Bac à sable** (technical test API).

Namespace: `TheBeardedBear\UrssafPlateformeNumerique`.

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
- [docs/reference/](docs/reference/) — official URSSAF documentation.
- Catalogue pages: https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-echanges-reels and
  https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-bac-a-sable

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, guard, coverage, infection
make test        # unit + functional tests
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

## License

Proprietary — see [LICENSE](LICENSE).
