# Innora

One property. One Albanian reception desk. Two folders.

| App | Path | Role |
| --- | --- | --- |
| Laravel 13 | this folder | Reservations, availability, auth, channel door |
| React + Vite | `../Innora-frontend` | Calendar and desk, copy in Albanian |

The person who used to copy Booking.com stays onto a notebook is gone. The owner does not use the Booking.com website. This desk is the only place a stay is written or read.

## Shape, same as HoReCa

HoReCa is a multi-company restaurant and hotel platform. Innora is not that product. It keeps the same engineering shape and drops the rest.

- Controllers are transport only. They pass the authenticated user into a manager and return `{ success, message, data }` through `ApiTrait`.
- Business rules live in `app/Managers`. Models hold relations, casts, and scopes.
- Expected failures use `HasMessages` and `messages.*` in `lang/en` and `lang/sq`. The desk locale is Albanian.
- Lists go through property visibility, then one pipeline filter per query parameter.
- Multi-table writes run in a transaction. A stay locks the physical room with `lockForUpdate`.
- Anything outside the building goes through `App\Channels\Contracts\ChannelProvider`. `ReservationManager` does not call Booking.com.

Not in this product: POS, folios, fiscalization, a permission matrix, or more than one property.

## Modules

| Module | Owns | Does not own |
| --- | --- | --- |
| Auth | Sanctum session, one reception user | Roles |
| Property | The hotel. Every row hangs off `property_id` | A second tenant |
| Inventory | Room types, physical rooms, room blocks | Rates and restrictions |
| Reservations | Create, edit, check-in, check-out, cancel, import | Channel HTTP |
| Guests | Name and phone, found or created with the stay | A CRM |
| Availability | “Is this room free?” and “how many of this type are left?” | The calendar layout |
| Channels | Push availability. Later, pull stays into `importExternal` | The desk form |
| Sync | `sync_logs`. The screen reads “Sinkronizuar” or “Problem me lidhjen” | Provider names |

## How a stay is born

The receptionist types a stay. Source is `TELEFON`, `RECEPSION`, `WHATSAPP`, or `DIREKT`. `BOOKING` is rejected on `POST /api/reservations`.

Booking.com arrives later through `ReservationManager::importExternal`: new external id creates a stay and assigns a free room of that type; the same id updates; cancel frees the room; a repeated create does not insert a second row.

A direct stay is committed before `SyncAvailabilityJob` runs. A failed provider call does not roll back the stay. Milestone 1 binds `NullChannelProvider`, which writes a succeeded `sync_logs` row and does not open a socket.

## Calendar rule

A stay occupies `[check_in, check_out)`. Checkout morning is free. `cancelled` does not block the room. The React app displays the API. It does not decide availability.

## Frontend

Pages stay in Albanian feature folders: `hyrje`, `permbledhje`, `kalendari`, `rezervimet`, `dhomat`, `klientet`, `cilesimet`. HTTP lives in `src/lib/api`. TanStack Query holds server state. Zustand holds the calendar view and open dialogs. Copy lives in `src/i18n/sq.json`.

## Next

Replace `NullChannelProvider` with one connectivity provider. Pull stays into `importExternal`. Push availability after commit. The screen copy does not change.
