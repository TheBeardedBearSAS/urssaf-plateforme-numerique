# Integration events (outbox)

The host application usually needs to react to the URSSAF workflow: mark an invoice as processed, show a rejected file
to an operator, reconcile an upload whose outcome is unknown. The package publishes these facts as **integration
events**: public, scalar classes (`TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\*`) versioned independently
of the domain model, written to an **outbox** in the same transaction as the state change, then delivered by a
**relay** (see [ADR 0010](adr/0010-integration-events.md)).

Guarantees:

- **Durable**: an event exists if and only if its state change was committed (a rolled back use case writes none).
- **At least once**: an event is marked as dispatched only once the sink accepted it; a crash between the delivery and
  the mark delivers it again. Deduplicate on `eventId`: the same fact always has the same id.
- **Ordered per submission**: the events of one submission are delivered in recording order; when one fails, the next
  events of the same submission wait for the next run (the other submissions go on).

The domain events (`Domain\...\Event\*`) are an internal mechanism and may change with the domain model. Integrate with
the integration events.

## Catalogue

Common properties of every event:

| Property | Type | Meaning |
|---|---|---|
| `eventId` | string | stable identifier of the fact: deduplicate on it |
| `occurredAt` | string | ISO 8601 instant, UTC |
| `tenantId` | string | tenant of the submission |
| `aggregateId` | string | local submission id |
| `externalReference` | string | your reference of the record |
| `kind` | string | `identification` or `declaration` |
| `declarationType` | ?string | `DECLARATION` or `REGULARISATION`; null for an identification |
| `fileName` | string | name of the uploaded file |
| `urssafRef` | ?string | URSSAF reference of the submission, when known |

`type()` and `version()` (1 for every type today) are available on every event.

| Class | `type()` | When | Specific properties |
|---|---|---|---|
| `SubmissionUploaded` | `urssaf.pn.submission.uploaded` | URSSAF accepted the upload and gave an `urssafRef` | none |
| `SubmissionUploadRejected` | `urssaf.pn.submission.upload_rejected` | URSSAF refused the upload (final) | `errorCodes` (list of strings) |
| `SubmissionOutcomeUnknown` | `urssaf.pn.submission.outcome_unknown` | the upload may have reached URSSAF, outcome unknown | none |
| `SubmissionNotReceived` | `urssaf.pn.submission.not_received` | reconciliation proved URSSAF never received the upload | none |
| `SubmissionAccepted` | `urssaf.pn.submission.accepted` | URSSAF processed the submission successfully (final) | `status` (raw), `reports` |
| `SubmissionRejected` | `urssaf.pn.submission.rejected` | URSSAF rejected the submission after processing (final) | `status` (raw), `reports` |
| `DeclarationPaymentStatusChanged` | `urssaf.pn.declaration.payment_status_changed` | a poll observed a new payment status | `previousPaymentStatus` (?string), `paymentStatus` (?string) |

`reports` is a list of `{field, fileId, name}`: `field` is the wire key of the report reference (for example
`globalReportRef`), `fileId` the value to pass as `fileRef` to the download operations, `name` the optional file name.

Technical failures (`error`, `invalid_credentials`) have no integration event: the host gets the result synchronously.
Events never carry the uploader identity (`userId`, `userEmail`) nor file contents.

## Versioning

Every type has a `VERSION` constant (1 today). Any change of the payload of a type (a property added,
removed, renamed or retyped, or a change of meaning) increments its `VERSION` (ADR 0010). Decoding refuses a class that is
not an integration event.

## Delivery

Relay the events from a periodic job; the `IntegrationEventSink` port delivers each event to your application
(message bus, event dispatcher...). Handlers must be idempotent: the same event may be delivered twice.

```php
public function __invoke(SubmissionAccepted $event): void
{
    // Idempotent: the same event may be delivered twice.
    $this->records->byReference($event->externalReference)->markProcessedByUrssaf($event->eventId);
}
```
