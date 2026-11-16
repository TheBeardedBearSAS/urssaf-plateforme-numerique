# ADR 0005 — Files: uploads and downloads

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md) and
[ADR 0002](0002-http-client-adaptations.md).

## Context

The uploads carry a compressed XML file (`DPID`, `DPDE`, `DPRE`) whose XSDs, naming convention and maximum size are not
published (§1.2, §5); three different naming patterns appear in the contract. The downloads return a binary of
unknown name and format (§2.4, §2.8). The Application layer may not depend on PSR-7.

## Decision

- The package sends a file it is given: it never generates, compresses, parses nor validates the XML (§4.3), and does
  not check the file name against a naming convention.
- Port `Application\Port\FileSource`: `name(): string` (non-empty) and `open(): resource` (a fresh readable stream at
  each call, closed by the caller). It is opened once for the fingerprint, once for the upload, and once more for the
  replay after a 401. Infrastructure adapters: `LocalFileSource::fromPath($path, $name = basename)` and
  `InMemoryFileSource::of($name, $contents)`.
- Port `Application\Port\Urssaf\BinaryStream` (`read(int)`, `eof()`, `copyTo(resource)`, `close()`) returned inside a
  `DownloadedFile` (`stream`, `?contentType`, `?contentDisposition`); the Infrastructure adapter wraps the PSR-7 body.
  Report files are never decoded, buffered, stored nor logged.

## Consequences

- Hosts produce and keep their own files; the package only transports them.
- A naming check or an XSD validation can be added once URSSAF publishes them, behind an opt-in.
