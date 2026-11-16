# Wire fixtures

Official examples, copied verbatim from the URSSAF YAML contracts (see `docs/api-reference.md` section 2.10):

- `news_feed_response.json`
- `upload_identification_request.json`
- `upload_declaration_request_declaration.json`
- `upload_declaration_request_regularisation.json`
- `upload_response.json` (assembled from the property-level example)

Hand-built fixtures (names end with `_handbuilt.json`): the contracts publish no object-level example of an
identification, a declaration, a search result or an error. These files are **hand-built** by the maintainers from the
property-level examples of the contract, with fictitious values (no real platform, user, file or reference). They
check the mappers against the documented field names and types, not against real answers of the API.

- `identification_response_handbuilt.json`: an accepted `Identification` with every report and `details`.
- `declaration_response_handbuilt.json`: an accepted `Declaration` (regularisation over two periods) with
  `statistics` and `concernedRegularisation`.
- `search_identifications_response_handbuilt.json`: two `Identification` items (one accepted, one in process).
- `search_declarations_response_handbuilt.json`: two `Declaration` items (one accepted, one in process).
- `error_response_handbuilt.json`: the documented error array (`code`, `message`, `description`), with invented codes
  since the real ones are not published.
