# Security review, November 2026

| | |
|---|---|
| Date | 2026-11-17 |
| Reviewer | The project maintainer, assisted by an AI code assistant (Claude) |
| Scope | `thebeardedbear/urssaf-plateforme-numerique`, commit `339b3b368babec846ca2ba925fca8905d8758ee1` (`main`, after release v0.1.0): everything under `src/`, the Composer configuration, the GitHub workflows, composite action and Dependabot configuration, and the repository protection settings |
| Reference | [Assurance case](assurance-case.md): security requirements SR1 to SR7, threat model and trust boundaries |
| Next review | At the latest in November 2027, or earlier after a change of a trust boundary |

## Method

1. **Design review against the security requirements and the trust boundaries** of the assurance case: for each
   boundary (host to package, package to URSSAF, package to database, package to cache, package to logs, pipeline to
   consumers) the data crossing it was identified and the control that protects it was located in the code.
2. **Manual code review** of the security-relevant code: credentials and token handling (`Application/Port/Credentials`,
   `AccessToken`, `Infrastructure/Urssaf/Http/OAuth2TokenProvider`, `Infrastructure/TokenStore/*`), the HTTP client,
   multipart upload and URL building (`JsonApiClient`, `MultipartBody`, `ResponseInterpreter`, `PlatformPaths`,
   `QueryEncoder`, `ApiConfiguration`, `Environment`), the decoding of untrusted payloads (`Infrastructure/Urssaf/Wire/*`),
   validation of identifiers and file names (`src/Domain/**`), file handling (`LocalFileSource`, `FileFingerprinter`,
   the report download command), logging and exception messages (every `logger->` call and exception factory),
   randomness (`Infrastructure/Id/*`), the submission workflow (`SubmissionUploader`, `UploadClaim`, reconciliation),
   the Doctrine repositories, types, serializers and outbox, the integration event codec, the Symfony bundle
   configuration and registrars, and the diagnostics.
3. **Targeted searches** for dangerous constructs in `src/`: `unserialize`, `serialize`, `eval`, `exec`, weak hashes
   and random functions, TLS options, `http://` literals, raw SQL concatenation, `var_dump`/`print_r`, direct reads
   of the credentials, every use of `getMessage()`.
4. **CI and supply-chain review**: the four workflows, the composite setup action, Dependabot configuration, release
   signing, and the settings of the GitHub repository (ruleset of `main`, secret scanning, workflow token defaults),
   read through the GitHub API.
5. **Tool support** (complements, does not replace the human review): `composer audit` run during this review (no
   advisory found); the standing gates PHPStan level max, Mago, Deptrac, 100% coverage, Infection at 100% MSI,
   PHP-CS-Fixer and Rector run on every pull request; branch coverage on `main`; CodeQL on the workflows, OpenSSF
   Scorecard and SonarQube Cloud (when enabled) as described in the assurance case.

Not done: no dynamic testing (fuzzing, penetration test) and no review of the third-party development tools beyond
`composer audit`. The live URSSAF sandbox was not exercised (the contract run is still to do, see
[ROADMAP.md](../../ROADMAP.md)).

## Result

No vulnerability exploitable in the default or the documented configuration was found. The review recorded twelve
observations, all low or informational, listed below. The main design properties were confirmed: no third-party runtime
code, secrets never logged nor serialised, no personal data persisted nor logged, strict validation at construction,
strict typed decoding of responses, encoded path segments, no automatic resend of an upload, consistent tenant scoping
in persistence, pinned and least-privilege CI.

## Findings

Severity scale: Critical, High, Medium, Low, Informational. Status: Open (accepted for a later hardening change),
Accepted (residual risk assumed by design or by the host), Mitigated.

| ID | Severity | Description | Status |
|---|---|---|---|
| SR-01 | Low | The base URI override (`ApiConfiguration`, bundle option `base_uri`) accepts any string: an `http://` URL would send the client secret, the token and the files in clear. The default environments are HTTPS and the override is an operator setting, but a guard (refuse non-HTTPS outside a loopback host) is cheap. Redirect handling and TLS verification are those of the PSR-18 client provided by the host; the package neither enables nor disables them. | Open |
| SR-02 | Low | JSON responses are read in full (`(string) $response->getBody()` in `ResponseInterpreter` and `OAuth2TokenProvider`) and decoded with a depth limit of 512, without a size cap. A malicious or compromised endpoint could force a large allocation. Mitigated by the client timeout and PHP `memory_limit`; the report downloads are streamed and not concerned. | Open |
| SR-03 | Low | The outbox stores integration events with PHP `serialize()` and reads them with `unserialize()` restricted by `allowed_classes` to the seven scalar event classes (`IntegrationEventCodec`). Not exploitable through URSSAF input, and an attacker able to write the outbox table already controls the database, but a plain JSON codec would remove the class of risk and the coupling of stored data to class layout. | Open |
| SR-04 | Low | `PsrLoggerFailureReporter` logs `Throwable::getMessage()` and `RelayOutboxHandler` stores it in the outbox `last_error` column when a host listener or sink fails after commit. The package's own messages are free of secrets and personal data, but a host exception message is outside its control. Logging the exception class only (as elsewhere in the package) would be consistent. | Open |
| SR-05 | Informational | URSSAF error `code`, `message` and `description` are stored in the database (`SubmissionError`, upload rejections) and exposed in views and by the console commands. They are never logged, but URSSAF could echo submitted values in them. Covered by the host retention policy. | Accepted |
| SR-06 | Informational | The access token is stored in clear in the PSR-6 / PSR-16 pool for its lifetime (default pool `cache.app`, often a filesystem directory). Short lived and bound to one client id, but the host must keep the pool private. Listed in the assumptions of the assurance case. | Accepted |
| SR-07 | Informational | `client_secret` handling depends on the host: `%env()%` placeholders are documented, but a literal value in committed configuration is accepted by the bundle and would end in the compiled container. A host-side secret store is assumed. | Accepted |
| SR-08 | Low | `MultipartBody` copies the whole file into a `php://temp` stream (memory, then a temporary file on disk above PHP's threshold) before sending it, and the PSR-18 client may buffer it again. The identification and declaration files hold personal data of the platform's auto-entrepreneurs, so a copy lives in the system temporary directory for the duration of the call, and no maximum size is enforced locally (URSSAF does not publish one). Streaming the body from the source would avoid the copy; the host must protect the temporary directory. | Open |
| SR-09 | Informational | `urssaf:pn:report:download` writes a report (personal data of the platform's users) to the operator-chosen path with the default permissions of the process umask. It never overwrites an existing file and removes a partial one, but it does not restrict the mode of the new file. Documented as a host responsibility. | Accepted |
| SR-10 | Informational | The default rate limiter is per process (`SlidingWindowRateLimiter`), and off unless enabled: several workers can together exceed the URSSAF quotas. A shared limiter can be injected; this is an availability concern, not a confidentiality or integrity one. | Accepted |
| SR-11 | Low | Repository settings: the `main` ruleset requires pull requests with resolved review threads and the 19 status checks, forbids force pushes and deletion and has no bypass actor, but requires 0 approving reviews (single maintainer), does not require signed commits, and does not yet list the `dco` job among the required checks. Dependabot security updates are disabled (version updates for Composer and GitHub Actions are configured, and `composer audit` runs in CI). The default workflow token is read-only, but workflows are allowed to approve pull requests. | Open |
| SR-12 | Low | Hardening of the CI: the checkouts of the `ci.yml` jobs keep the default `persist-credentials: true` (the token is read-only, but the CodeQL, Scorecard and release checkouts already disable it). The SonarQube Cloud analysis only runs when the `SONARCLOUD` repository variable is enabled, so it is not a guaranteed gate. CodeQL does not analyse PHP: PHP code security relies on PHPStan, Mago and SonarQube Cloud. | Open |

No finding of severity Medium or higher.

## Confirmed controls (positive results)

- `composer.json` requires only PSR interfaces and PHP extensions at runtime.
- Credentials: private constructor and validation, masked `__debugInfo()`, `#[SensitiveParameter]`, serialisation
  refused (`CredentialsNotSerializable`); the secret is read only to build the token request.
- Logging: only method, path, status, duration, request id and exception class are logged by the package; bodies,
  tokens, file names, search keywords and personal data are not.
- Personal data: `userId` and `userEmail` exist only on the live read models and are masked in dumps; the Doctrine
  mapping has no column for them (asserted by a test); file contents and reports are never stored by the package.
- Paths and queries: every segment is `rawurlencode`d, including values read from URSSAF answers (`urssafRef`,
  `fileId`); `PlatformId` and `UrssafReference` refuse `/`; `FileName` refuses control characters and double quotes
  before it reaches the multipart headers.
- Exception messages carry codes, statuses and field names, never the rejected value.
- Responses are decoded with `json_decode` into arrays and read through typed accessors; an unknown status or invalid
  JSON raises `UnexpectedUrssafResponse`; an unreadable answer to an upload raises `OutcomeUnknown`, never a resend.
- Randomness comes from `random_bytes()`; weak generators and hashes are absent from `src`.
- Persistence uses named parameters; interpolated identifiers are constants or Doctrine metadata.
- Tenant scoping is applied in repository queries; locks and token cache keys are per tenant or per client.
- The simulated gateway is refused in the production environment at configuration time.
- Report downloads are streamed, opened with mode `x` (never overwritten) and removed when the copy fails.
- Workflows: pinned action SHAs, read-only default permissions, scoped elevation, no `pull_request_target`, no
  interpolation of untrusted input in `run` scripts, signed release assets with provenance.

## Residual risks and assumptions on the host

See section 7 of the [assurance case](assurance-case.md#7-assumptions-on-the-host-application): secure storage of the
`client_secret`, a private token cache, TLS and redirect behaviour of the PSR-18 client, protection of the files, the
temporary directory and the downloaded reports, database protection and backup retention, retention and erasure policy
for the link between a submission and its author, and absence of body logging in the host.

## Follow-up

The open findings are tracked as hardening work for the next releases. Vulnerabilities are reported privately, see
[SECURITY.md](../../SECURITY.md).
