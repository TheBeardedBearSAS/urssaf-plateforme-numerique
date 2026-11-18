# Roadmap

Intended direction for the next twelve months (November 2026 to November 2027). It is a statement of intent, not a
commitment; it is reviewed at each minor release. Proposals are welcome in GitHub issues. The implementation status of
the first release is recorded in [docs/roadmap.md](docs/roadmap.md).

## Planned

- **Run the contract tests against the real sandbox.** Execute the opt-in contract tests against the URSSAF sandbox
  and the test API, settle the open points of the [API reference](docs/api-reference.md) (section 5) as URSSAF
  confirms them, and adjust the affected ADRs.
- **Follow the URSSAF API.** Track each new version of the URSSAF *API portail Plateforme numérique* (OpenAPI contract
  and technical documentation, *Échanges réels* and *Bac à sable*) and release the matching changes, with upgrade
  notes in the [changelog](CHANGELOG.md).
- **Release 1.0.** Stabilise the public API (domain model, client, submission workflow, integration events, Symfony
  bundle, Doctrine bridge), then release `1.0.0`. From 1.0 on, breaking changes only happen in major versions, as
  required by [Semantic Versioning](https://semver.org/).
- **Keep up with the platform.** Support new PHP versions (8.5 and later) and the new Symfony and Doctrine versions
  as they are released, and drop the versions that reach their end of life in a minor release, announced in the
  changelog.
- **Keep the quality and security bar.** 100 % coverage and 100 % MSI, branch coverage of at least 96 %, PHPStan at
  level max, signed releases, and the OpenSSF Best Practices criteria.

## Not planned

- Other URSSAF APIs: each one gets its own package (see *Other URSSAF packages* in the [README](README.md)).
- Generating, compressing or validating the declaration and identification files: the package transports the files
  the host application produces ([ADR 0005](docs/adr/0005-files.md)). A check can be added, behind an opt-in, once
  URSSAF publishes the XSDs and the naming convention.
- Official bridges for other frameworks than Symfony: the core works without a framework (see
  [docs/standalone.md](docs/standalone.md)).
