# FleetPilot architecture

FleetPilot is a modular monolith for one carrier organization at a time. The requested stack is the stack in this repository: Laravel, Inertia.js, React, TypeScript, MySQL, and Tailwind CSS. There is no separate API service, no Postgres application database, and no ThemeForest template in the build.

## Boundaries

- `app/Domain` holds the operations: identity, shipments, dispatch, tracking, notifications, billing, and reporting.
- HTTP controllers authorize, then call that domain. They do not reimplement status rules.
- React pages in `resources/js/pages` are the only UI. Inertia is the only bridge. There is no second JSON API for the same actions.
- MySQL enforces foreign keys, unique shipment and trip numbers, one active assignment per shipment or trip, and a check that an assignment targets a shipment or a trip.

Organization membership lives on `organization_user`. `EnsureOrganization` loads that membership into `OrganizationContext` for the request and forgets it afterward, so a stale session organization cannot be reused for a different user. Owners always have every permission. Other roles read `permission_grants`. Drivers see shipments they are assigned to now or were assigned to before. Customers see only their customer record. A miss is a 404.

## Shipment status

Assignment is not a generic transition. Dispatch sets `assigned` under row locks. The generic machine is:

| From                           | To                                                                          |
| ------------------------------ | --------------------------------------------------------------------------- |
| draft                          | ready for dispatch, cancelled                                               |
| ready for dispatch             | on hold, cancelled                                                          |
| assigned                       | picked up, failed pickup, on hold, cancelled                                |
| picked up                      | in transit, failed delivery, on hold, damaged or missing                    |
| in transit                     | delivered, failed delivery, on hold, damaged or missing, returned to sender |
| failed pickup                  | picked up, ready for dispatch, on hold, cancelled                           |
| failed delivery                | in transit, returned to sender, on hold, cancelled                          |
| on hold                        | ready for dispatch, cancelled                                               |
| damaged or missing             | in transit, delivered, returned to sender, on hold, cancelled               |
| delivered, returned, cancelled | terminal                                                                    |

Failed pickup, failed delivery, on hold, damage, return, and cancellation require a reason. That reason stays off the public tracking page. A customer-safe message is optional and separate.

Field transitions (`picked up`, `in transit`, `failed pickup`) need `shipments.field`, or the assigned driver's `shipments.driver_action`. Closing (`delivered`, `returned`) needs `shipments.close`, with the same driver exception for delivery. Drivers cannot update a shipment that is not theirs, and they cannot dispatch.

Window times without a timezone are read in the organization timezone. Values that already include an offset are absolute. Both are stored in UTC.

## Dispatch

`AssignShipment` locks the driver, the vehicle, and the shipment, in that order. Overlapping active windows are rejected unless the organization allows them. Reassignment cancels the previous active row inside the same transaction. A nullable `active_shipment_key` / `active_trip_key` unique index allows one active row and many completed rows. MySQL generated columns on those foreign keys failed with error 1215, so the keys are real columns maintained when the assignment is saved, and nulled explicitly when a query-builder update completes or cancels a row.

Trips are manual stop lists. Creating a trip assigns every shipment on one trip-level assignment. Those shipments are not assigned again one by one. Reorder writes a temporary sequence then the final sequence so the unique `(trip, sequence)` key holds. Stop status is a projection of the shipment status in the same transaction. The shipment status remains authoritative.

There is no route optimizer and no live GPS. The driver screen links to an external map for navigation and says the workspace needs a connection.

## Proof, tracking, money, notices

Delivery evidence is immutable. A correction is a new row with `amends_id`. Files are metadata plus bytes on the local disk under `evidence/{organization}/{random}`. A signature must be a PNG data URL. It is a captured image, not a legal electronic signature. Recipient name is required by default. Photo and signature can be turned on per organization.

Public tracking uses the SHA-256 of an unguessable token. The raw token is shown once. `/track/{token}` is limited to 30 requests a minute and returns 410 when the token is expired or revoked. The page shows cities, status, and the customer-safe message. It does not show street addresses, internal notes, internal reasons, or the driver's phone.

Charges are integer cents. A discount cannot exceed the non-void base plus accessorial total. Marking a shipment delivered does not mark a charge paid. Tax basis points in organization settings are an estimate field only; the report does not invent tax.

Notification rows are inserted in the same transaction as the status change. Sending happens after commit, from a terminating callback, or from `php artisan fleetpilot:notifications`. The default provider logs the message. `NOTIFICATION_PROVIDER=mail` uses Laravel mail. SMS is sent only when `SMS_WEBHOOK_URL` is set; otherwise the job is skipped. A send failure increments attempts, up to 5, and does not roll the shipment back. The same event and recipient cannot be queued twice.

`Idempotency-Key` on transition and assign replays the saved response. Reusing the key with a different body is rejected.

## Dashboard definitions

Counts are SQL, for the organization-local day, except where noted.

- Awaiting dispatch: current Ready for Dispatch shipments.
- Unassigned: those with no active assignment.
- Scheduled pickups: Assigned shipments whose pickup window starts that day.
- Active: Picked Up or In Transit right now.
- Deliveries due: shipments that are not delivered, cancelled, or returned, whose delivery window overlaps that day.
- Delayed: pickup window ended before pickup, or delivery window ended while the shipment is still out. Cancelled shipments are excluded. This is a current-state check, not a historical reconstruction.
- Failed attempts: `delivery_failed` events that day, not the current status.
- Completed: `actual_delivery_at` on that day.
- Vehicle utilization: distinct vehicles on an assigned, picked-up, or in-transit shipment, divided by active vehicles that are not out of service. Not filtered by the selected day.

## What this build does not do

- No live GPS, geofence, or breadcrumb trail.
- No automatic route optimization. Trip order is the order a dispatcher saved.
- No offline driver queue. The driver UI requires a connection.
- No production deploy and no claim that the log mailer delivers email.
- Registration is still open. A user with no membership sees an empty dashboard until someone adds them.
- The organization screen edits operating settings, adds members, and edits role grants. Owner access stays complete so the organization cannot be locked out.

## Tests

`php artisan test` uses MySQL database `fleetpilot_test`. Coverage includes organization isolation, role visibility, address snapshots, the status machine, idempotency, dispatch overlap, the one-active-assignment unique key, a second MySQL session waiting on the shipment row lock, proof-of-delivery rules, public token expiry and revocation, notification retry without a status rollback, trip reorder, billing math, dashboard definitions, pagination, and document download rules.
