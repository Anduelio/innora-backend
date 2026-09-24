# Room domain

A property sells **room types**. Reception works with **physical rooms**.

A stay occupies `[check_in, check_out)`. The checkout morning is free for the next guest. A reservation from 22 to 25 September occupies the nights of 22, 23 and 24.

## Two states

Operational status is how the room is prepared: `ready`, `dirty`, `cleaning`, `inspected`, `maintenance`, `out_of_order`.

Occupancy comes from reservations. A room can be ready and still occupied tonight. Checkout sets the operational status to `dirty`. Check-in is allowed only when the room is assigned, not blocked, and `ready` or `inspected`.

## Inventory

Sellable rooms are active, not `maintenance`, and not `out_of_order`. A night's availability for a room type is:

free physical rooms of that type, minus reservations of that type that have no room assigned yet.

A Booking.com-style stay can exist with a room type and no room number. Assigning a room later checks the property, the type, overlaps, and blocks.

## Property hours

`default_check_in_time` and `default_check_out_time` belong to the property. A reservation stores `expected_arrival` and, when it happens, `checked_in_at` / `checked_out_at`.

## What stays out

Beds and amenities live on the room type. Physical rooms inherit them. Channel ids live in `channel_room_type_maps`, not on `room_types`. Photos have a table and no upload yet.

## Channel credentials

Beds24 is the only channel configured for now. The catalog stays a list, so another provider can be added later. Credentials are typed in Cilësimet and stored on `channel_connections.config_encrypted`. The API returns secrets masked. Only the public base URL may live in configuration. One property keeps one row per provider, and only one of those rows is active.

## Folio

Each reservation has one primary folio (`FOL-{id}`). Room nights are posted as snapshot charges when the stay is created or an open folio is rebuilt. Extra charges and payments are separate rows. Balance = posted charges − posted payments. A closed folio rejects normal edits.
