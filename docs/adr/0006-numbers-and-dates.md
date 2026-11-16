# ADR 0006 — Numbers, periods and dates

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference stores money in integer cents and dates as strict `CalendarDate` / UTC `Instant`. Here the counters and
amounts of `details` / `statistics` are `number` with no unit or currency (§2, §5); `period` is `YYYY-MM` only by
example; `lastUpdateDate` is a `date-time` whose example has no zone; `uploadDate` and `treatmentDate` have no format;
`newsDate` is RFC 3339 with microseconds (§4.7).

## Decision

- `Domain\Shared\DecimalNumber`: the decoded JSON number kept as its canonical decimal string (an integer as is, a float
  with PHP's shortest round-trip representation), with no unit and no arithmetic; `toInt(): ?int` when integral. No
  float is ever stored in a domain value.
- `Domain\Submission\WithholdingPeriod` (`YYYY-MM`, month 01–12) for inputs. On the wire, `ReportedPeriod` keeps the
  raw string and the parsed `?WithholdingPeriod`, like the raw enum fallback: an unexpected format never breaks a read.
- `lastUpdateDate`: ISO 8601 date-time with optional fraction; an explicit offset is honoured, a missing one is
  interpreted in `ApiConfiguration::apiTimeZone()` (default `Europe/Paris`, open point §5); stored as a UTC `Instant`.
  A value that cannot be parsed throws `UnexpectedUrssafResponse`.
- `uploadDate`, `treatmentDate`: `ApiTimestamp` (raw string + best-effort `?Instant`, same parser).
- `newsDate`: strict RFC 3339 with offset.
- `start_date` / `end_date`: `CalendarDate` sent as `Y-m-d` (the YAML examples; the JSON examples with a time are
  listed in §5).

## Consequences

- Hosts that need amounts in cents convert them once the unit is published.
