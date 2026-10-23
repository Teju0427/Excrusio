# Excursio — Phase 1 Architecture

> *Stay somewhere special. Experience something extraordinary.*
> Status: **Phase 1 (design only — no code yet).** Waiting for approval before Phase 2.

---

## 0. Confirmed scope & assumptions

| Item | Decision |
|---|---|
| Stack | PHP 8.3+, **Laravel 13** (released 17 Mar 2026, needs PHP ≥ 8.3), MySQL 8, Blade, Bootstrap 5.3, vanilla JS, Bootstrap Icons, Chart.js |
| Front-end build | **No Node/Vite build step.** Bootstrap, Icons and Chart.js are vendored into `public/vendor/`. Our own CSS/JS are plain files in `public/css` and `public/js`. → simpler Docker image, faster deploys, nothing breaks if npm is unavailable. |
| Money | Stored as **integers in paise** (`₹1,250.00` → `125000`). No floating-point rounding errors. Displayed with `en-IN` formatting. |
| Currency | INR only (v1). |
| Time zone | App stores UTC; displays in each listing's destination timezone (column on `destinations`). |
| Payments | A full checkout flow where no real money moves. `PaymentGateway` interface → `DemoGateway` (always available) + optional **Razorpay Test Mode** gateway (Phase 5, needs your free Razorpay account; keys stored in `.env`). Excursio never handles card/UPI details itself. |
| Budget | ₹0. Nothing paid is activated without your approval. |
| Existing code | No project folder is connected to this session yet, so nothing has been inspected. **Before Phase 2, connect your Excursio folder (or share the GitHub repo)** so I can check for existing code first. |

---

## 0a. Approved signature features

| Feature | How it works | New tables / pieces |
|---|---|---|
| **Combined trip cart** | Put a stay, a dinner and an activity in one cart and pay once. The server rejects clashes (dinner during a ski session, activity outside the stay dates, less than 60 min between two items). **All bookings succeed together or none are created** (one DB transaction). | `carts`, `cart_items`, `booking_groups` (bookings get a nullable `booking_group_id`) |
| **Waitlist** | When a slot or room type is full, users can join a waitlist. When a booking is cancelled, the next person is notified and gets a **30-minute claim window**, during which the spot is held for them. | `waitlist_entries` (status: waiting / offered / claimed / lapsed, `offer_expires_at`) |
| **QR check-in** | Each confirmed booking shows a QR code containing a signed check-in token. The provider scans it with their phone camera (html5-qrcode) to mark the guest checked in. **Only checked-in bookings can be reviewed.** | `bookings.checked_in_at`, `checkin_token` |
| **Refund timeline** | Before paying, the user sees "Cancel by 12 Nov → ₹12,500 back · by 20 Nov → ₹6,250 · after → ₹0", generated from the same `CancellationService` used for real cancellations, so the numbers always match. | — |
| **Map** | Leaflet + OpenStreetMap on detail and search pages; no API key, follows OSM's tile usage policy. | uses `lat/lng` |
| **Wishlist** | A heart icon on cards; a "Saved" page in the customer dashboard. | `wishlists` (unique user + listing) |

**Later, if time allows:** weather for outdoor activities (Open-Meteo), cheapest-dates calendar, price suggestions for providers, English/日本語 switch.

---

## 1. Design review of your reference image

**Verdict: yes, it's a good direction.** The muted greens, ivory, serif headings and big scenery photo read as "premium hospitality", and it's clearly different from Wanderly. But the reference is a **single-property landing page** (one apartment). Excursio is a **marketplace with three categories, search, filters and checkout**. That gap causes most of the problems below.

### Problems to fix (and how we will fix them)

| # | Problem | Why it matters | Fix in Excursio |
|---|---|---|---|
| 1 | It's a one-property template, not a marketplace | No pattern for result grids, filters, price comparison, maps, dashboards | Keep the *style* (type, colour, spacing) but design our own marketplace layouts: filter sidebar, result cards, sticky booking panel |
| 2 | Looks like a commercial theme (the "Tulum Luxury Apartment" layout) | Copying its layout, copy text or photos = licence/copyright risk | Original layout, original copy, our own logo; only the *visual language* is borrowed |
| 3 | **Low-contrast colours** in your palette (measured, WCAG AA needs 4.5:1 for body text) | Sage, gold and grey text become hard to read, especially on phones outdoors | See contrast table below — sage/gold used only for large text, icons, borders; darker variants for small text |
| 4 | Thin serif fonts at small sizes | Elegant at 48px, unreadable at 14px | Serif (*Cormorant Garamond*) only for headings ≥ 28px; body/forms/prices in *Inter* |
| 5 | White text directly on a photo | Text disappears on bright sky/snow photos (skiing!) | Dark gradient overlay behind hero text on every hero |
| 6 | Huge full-width photos | Slow first load — the #1 reason a site "feels slow" | Optimised WebP images (hero ≤ 250 KB, cards ≤ 60 KB), `srcset`, `loading="lazy"` below the fold, fixed width/height to stop layout jumps |
| 7 | Hotlinked placeholder images | Random URLs die (e.g. `source.unsplash.com` no longer works) → broken cards | Seed images downloaded once, optimised, **committed to the repo** with a `CREDITS.md` (source + licence). They ship inside the Docker image, so they survive Render restarts |
| 8 | The hero search bar has 5 fields side by side | Doesn't fit on a phone | Tabs (Stays / Dining / Activities) + fields that stack on mobile; full-width "Search" button |
| 9 | Small "Learn more" buttons | Hard to tap; vague label | 44px min touch targets; specific labels ("View stay", "Reserve a table", "Book session") |
| 10 | 3D floor-plan render in the reference | We can't generate those for every listing | Not used; a clean "Rooms & options" table instead |
| 11 | Price shown as `$499/ad` | Wrong currency/format for India | `₹12,500 / night`, `₹2,400 per person` |
| 12 | All categories look identical | Users confuse a room with a dinner reservation | Each category gets its own icon, label chip and **its own homepage row** (as you asked) |
| 13 | Google Fonts load late | Text flashes or jumps | `font-display: swap`, preconnect, only 2 weights per font |

### Contrast check (measured on ivory `#F7F5EF`)

| Colour | Ratio | OK for small text? | Use |
|---|---|---|---|
| Charcoal `#292D29` | 12.8 | ✅ | body text |
| Forest `#243D35` (white text on it) | 11.7 | ✅ | primary buttons, nav, footer |
| Secondary text `#73766F` | 4.2 | ❌ (just under) | → replace with **`#5F635C` (5.6)** for small text |
| Sage `#87988A` | 2.8 | ❌ | borders, icons, large headings only |
| Gold `#B79A65` | 2.5 | ❌ | thin dividers, stars, accents → **`#76602F` (5.5)** when used as text |
| White on gold button | 2.7 | ❌ | don't use gold buttons with white text; use forest-green buttons |

---

## 2. Homepage layout (separate rows per category)

```
┌ Nav: logo · Stays · Dining · Experiences · Destinations · My Bookings · Sign in · [Join] ┐
│ HERO photo + overlay                                                                    │
│   "Find your next extraordinary experience."                                            │
│   [ Stays | Dining | Activities ]  ← tabs change the fields below                       │
│   [Destination][Check-in][Check-out][Guests][Rooms]  [Search]                           │
├─ ROW 1  Featured stays        (4 cards, "View all stays →")                             │
├─ ROW 2  Exceptional dining    (4 cards, cuisine · price range · next free slot)         │
├─ ROW 3  Adventures            (4 cards, duration · difficulty · from ₹/person)          │
├─ ROW 4  Destination inspiration (editorial mosaic; "12 stays · 4 restaurants" from DB)  │
├─ ROW 5  Why Excursio          (4 honest benefits only)                                  │
└ Footer: categories · account · contact placeholder · privacy · terms · demo disclaimer  ┘
```
On mobile each row becomes a horizontal swipe strip. All rows are **queried from the database** and cached for 10 minutes. Destinations without listings show "Coming soon", never fake counts.

---

## 3. Database design

ER diagram: [`er-diagram.mermaid`](er-diagram.mermaid).

### Core idea: one booking system, three "inventory" types

| Category | What is reserved | Capacity rule |
|---|---|---|
| Stay | a **stay unit** (room type) for a **range of nights** | for every night, `rooms booked ≤ unit.quantity` |
| Dining | a **time slot** (e.g. Sat 19:30) | `guests booked ≤ slot.capacity` (seats) |
| Activity | a **time slot / session** (e.g. 9:00 ski lesson) | `participants booked ≤ slot.capacity` |

Dining and activities share the same `time_slots` table. Stays use `stay_units` + date ranges. **All three write to one `bookings` table**, so dashboards, cancellations, reviews and analytics are written once.

### Tables

| Table | Purpose / key columns | Constraints & indexes |
|---|---|---|
| `users` | name, email, password (bcrypt/argon), role `customer/provider/admin`, status `active/suspended`, phone | unique email |
| `destinations` | name, slug, country, region, timezone, hero image, is_featured | unique slug |
| `categories` | sub-types admins manage: *Villa, Boutique hotel, Fine dining, Rooftop, Skiing, Trekking…*; `listing_type` | unique (listing_type, slug) |
| `listings` | shared fields: provider, destination, category, `listing_type`, title, slug, summary, description, address, lat/lng, base price, `booking_mode instant/request`, `cancellation_policy`, `status`, cached `rating_avg`, `reviews_count`, `is_illustrative` flag | unique slug; index (listing_type, status, destination_id); index (provider_id) |
| `stay_details` (1:1) | check-in/out times, house rules, property size | FK unique listing_id |
| `dining_details` (1:1) | cuisine, price band ₹–₹₹₹₹, dining type, dress code, max party size, deposit per guest | |
| `activity_details` (1:1) | duration, difficulty, min age, inclusions, equipment, safety info, weather policy, booking cut-off hours | |
| `listing_images` | url/path, alt text, credit, licence, sort order, is_cover | index (listing_id, sort_order) |
| `amenities` + `amenity_listing` | Wi-Fi, pool, ski storage, vegan menu… | unique pair |
| `stay_units` | room name, max guests, beds, `quantity` (identical rooms), price per night | index listing_id |
| `schedules` | weekly rules for dining/activities: weekday, start, end, interval, capacity | used to **generate** `time_slots` |
| `time_slots` | starts_at, ends_at, capacity, optional price override, status | unique (listing_id, starts_at); index (listing_id, starts_at, status) |
| `price_rules` | seasonal / weekend price for a listing or unit: date range, weekday mask, price | index (stay_unit_id, start_date) |
| `availability_blocks` | provider closures (renovation, holiday) | index (stay_unit_id, start_date, end_date) |
| `bookings` | see below | unique `reference`; indexes for overlap & capacity queries |
| `booking_participants` | activity participant names + ages (needed for age rules) | FK cascade |
| `payments` | gateway `demo`, reference, amount, status — kept **separate from booking totals** | |
| `reviews` | booking_id (**unique** → one review per booking), rating 1–5, body, status | |
| `reports` | complaints about listings/reviews/bookings (polymorphic) | |
| `contact_messages` | so the Contact form actually stores something | |
| `notifications` | Laravel's built-in database notifications | |
| `audit_logs` | actor, action, model type/id, old/new JSON, IP, time | index (auditable_type, auditable_id) |
| `sessions`, `cache`, `jobs`, `password_reset_tokens` | Laravel defaults — **DB-backed** because hosting disk is ephemeral | |

### `bookings` columns
`reference` (e.g. `EXC-ST-7K3P9Q`), `source` (`online`/`offline`), customer (nullable for offline), `guest_name`, listing, `listing_type`, `stay_unit_id` *or* `time_slot_id`, `check_in`, `check_out`, `nights`, `rooms`, `guests`, contact name/email/phone, special requests, `eligibility_acknowledged_at`, **price snapshot** (`unit_price`, `subtotal`, `fees`, `total`, all paise), `cancellation_policy` snapshot, `status`, `payment_status`, `hold_expires_at`, `confirmed_at`, `cancelled_at`, `cancelled_by`, `cancellation_reason`, `refund_amount`, timestamps.

Delete rules: listings are **archived, never deleted** once they have bookings (`restrictOnDelete`). Images, details, units cascade with their listing.

---

## 4. Booking engine

### 4.1 Flow (identical shape for all three)

```
Detail page ─► Availability check (Fetch API, JSON)
          ─► "Reserve" form POST
          ─► BookingService::hold()   [DB transaction + row lock]
                 status = awaiting_payment, hold_expires_at = now + 10 min
          ─► Checkout page (price breakdown from server)
          ─► "Complete demo payment"  → DemoGateway
          ─► status = confirmed (instant) or pending (request mode)
          ─► Confirmation page + notification + audit log
```
Dining with no deposit skips checkout: reserved → `confirmed` immediately ("Pay at the restaurant").

### 4.2 Statuses & allowed transitions

| From → To | Who | Applies to |
|---|---|---|
| *(new)* → `awaiting_payment` | customer | stays, activities, dining with deposit |
| `awaiting_payment` → `expired` | system (hold timeout) | all |
| `awaiting_payment` → `confirmed` | demo payment, instant listing | all |
| `awaiting_payment` → `pending` | demo payment, request listing | stays (mainly) |
| `pending` → `confirmed` / `rejected` | provider | request listings |
| `pending`/`confirmed` → `cancelled` | customer (per policy) or provider | all |
| `confirmed` → `completed` | system after end time | all |

A `BookingStatus` PHP enum holds this table; any other transition throws an exception. `rejected` and provider-cancelled bookings get a **full demo refund**.

### 4.2a Decided: hotel approval, offline bookings, rooms-left (stays)

- **Stays are request-to-book.** The customer pays (demo) when sending the request, and the status is `pending`. The hotel approves → `confirmed`, or rejects → `rejected` with a 100% refund. With no reply in 24 h → `expired` with a 100% refund. A pending request **holds the rooms**, so nobody else can take them meanwhile.
- **Offline bookings.** Providers can add phone or walk-in bookings from their dashboard. `bookings.source` is `online` or `offline`, and offline bookings have no customer account (`customer_id` is nullable; guest name and phone are stored instead). Offline bookings use up the same room inventory, so online availability stays correct.
- **Rooms left.** Each room type shows live availability for the selected dates, e.g. "Only 2 rooms left". It is calculated as `quantity − max rooms booked on any night in the range`. Rooms already booked are never offered.
- Dining and activities stay **instant-confirm**, which is how real restaurants and tour operators usually work.

### 4.3 Preventing double booking (the important part)

Inside `DB::transaction()`:
1. `SELECT … FOR UPDATE` the **stay_unit** row (or **time_slot** row). Two customers booking the same room/slot now queue up instead of racing.
2. Count what is already taken, counting `pending`, `confirmed`, and `awaiting_payment` **only while the hold hasn't expired**:
   - Stays: for every night in `[check_in, check_out)`, rooms booked on overlapping bookings (`existing.check_in < new.check_out AND existing.check_out > new.check_in`) + requested rooms ≤ `quantity`, and no `availability_blocks` overlap.
   - Slots: `SUM(guests)` + requested ≤ `capacity`, slot `open`, `starts_at` > now + cut-off.
3. Recalculate the price on the server, insert booking, commit. Any failure → rollback, nothing half-saved.
4. A `reference` unique index + a request **idempotency key** (hidden form token) stop double-click duplicates.

### 4.3a Verified (Phase 5)

- `scripts/concurrency-check.php` forks 20–25 real processes (each with its own MySQL connection) that try to book the same last room at the same instant. Result: exactly **1** succeeds, the rest get “sold out”, **1** booking saved, 0 errors. A 10-seat dinner slot raced by 15 parties of 2 ends with exactly 5 bookings / 10 seats.
- Times of bookings, slots and stays are stored as the **venue's local wall-clock time** (“19:30 in Kyoto”); `App\Support\VenueClock` gives “now” at the venue in the same form for cut-offs and refund windows. Hold and approval deadlines use real UTC time.

### 4.4 Validation rules

- **Stays:** check-out > check-in; 1–30 nights; check-in ≥ today; guests ≤ `max_guests × rooms`; rooms ≤ quantity.
- **Dining:** slot belongs to listing and is open; guests between 1 and `max_party_size`; date not in a closure; booking ≥ 2 h before slot.
- **Activities:** participants ≤ remaining capacity; each participant's age ≥ `min_age` if set; safety/eligibility checkbox required; booking before cut-off (e.g. 12 h).

### 4.5 Pricing (`PricingService`, server-only)

- **Stay:** Σ over each night of (matching `price_rule` price **or** unit price) × rooms.
- **Activity:** (slot override **or** listing price) × participants.
- **Dining:** deposit per guest × guests (often ₹0).
- **Fees:** `config('excursio.service_fee_percent')`, default **0**. No invented taxes; if shown, labelled "illustrative".
- Browser-submitted prices are **ignored**. The form sends only IDs, dates and counts.

### 4.6 Cancellation policies (`CancellationService`)

| Policy | Refund (demo) |
|---|---|
| Flexible | 100% if ≥ 24 h before start; 0% after |
| Moderate | 100% if ≥ 5 days before; 50% until 24 h before; 0% after |
| Strict | 50% if ≥ 7 days before; 0% after |
| Dining | free cancellation until 2 h before |
| Weather (activities) | provider cancels the slot → every booking cancelled with 100% refund + notification |

Policy is **copied onto the booking** so later policy edits don't change old bookings.

### 4.7 No cron on free hosting

Free Render web services have no scheduler and sleep when idle. So time-based work runs **lazily**:
- Expired holds are simply ignored in capacity counts (they don't need cleaning to be safe).
- When a dashboard loads, past `confirmed` bookings are flipped to `completed`.
- Time slots are generated 90 days ahead whenever a provider saves a schedule, and topped up when a listing's availability is viewed.
- Artisan commands (`bookings:complete`, `slots:generate`) also exist for local use / paid schedulers later.

---

### 4.8 Host inventory changes (Phase 6, `HostInventoryService`)

- Host edits take the **same row locks** as the booking engine (stay units, future time slots), so an edit and a guest booking can't interleave.
- Room count can't drop below the most rooms already booked on any future night (refused with the date).
- Price changes affect new bookings only — every booking keeps its own price snapshot.
- Closing dates stops new sales; existing bookings in that period stay valid and the host is told how many.
- Saving a weekly schedule deletes and regenerates only future sessions with **no bookings and no waitlist**; booked sessions are kept unchanged.
- QR check-in (F3): the QR encodes `/host/check-in/{40-char token}`; only the listing's host can open it (others get 404), only confirmed bookings, from 12 h before start until the end (venue-local time). Repeat scans are harmless; every check-in is audit-logged.

### 4.9 Dashboard numbers (`AnalyticsService`)

All figures are computed from database rows on each request. Money = succeeded demo charges − succeeded demo refunds. Occupancy = room-nights booked ÷ (rooms × nights) for the next 30 days. Charts use one fixed colour per category (stay #1F8A5F, dining #D4881C, experiences #2F74C0 — checked for colour-blind separation), always with a legend or axis labels and a "View as table" with the same numbers.

## 5. Roles & permissions

| Action | Customer | Provider | Admin |
|---|---|---|---|
| Browse / search / view published listings | ✅ | ✅ | ✅ |
| Book, view **own** bookings, cancel per policy | ✅ | — | view all |
| Review own **completed** booking (once) | ✅ | — | moderate |
| Create / edit **own** listings, schedules, rooms, prices | — | ✅ | ✅ all |
| Confirm / reject bookings **for own listings** | — | ✅ | ✅ |
| Publish listing | — | submits for review | approves / rejects |
| Manage users, categories, reports, audit logs, analytics | — | — | ✅ |

Enforced with `role` middleware + **Policies** (`BookingPolicy`, `ListingPolicy`, `ReviewPolicy`). Bookings are fetched as `$user->bookings()->where('reference', …)`, so changing an ID in the URL returns 404, not someone else's booking. Admin users are created only by seeder/command, never by public signup.

---

## 6. Folder structure

```
excursio/
├─ app/
│  ├─ Enums/            ListingType, BookingStatus, PaymentStatus, UserRole, CancellationPolicy
│  ├─ Http/Controllers/
│  │   ├─ Public/        Home, Stay, Dining, Activity, Destination, Search, Page, Contact
│  │   ├─ Customer/      Dashboard, Booking, Checkout, Review, Profile
│  │   ├─ Provider/      Dashboard, Listing, Unit, Schedule, Slot, BookingRequest, Calendar
│  │   ├─ Admin/         Dashboard, User, Listing, Category, Booking, Review, Report, Audit
│  │   └─ Api/           Availability (JSON for Fetch API)
│  ├─ Http/Requests/     StoreStayBookingRequest, StoreSlotBookingRequest, ListingRequest…
│  ├─ Models/
│  ├─ Policies/
│  ├─ Services/          ListingSearch, Availability, Booking, Pricing, Cancellation,
│  │                     Notification, Review, Audit
│  └─ Payments/          PaymentGateway (interface), DemoGateway
├─ database/
│  ├─ migrations/  ├─ seeders/  └─ factories/
├─ public/
│  ├─ css/excursio.css   ├─ js/excursio.js   ├─ vendor/(bootstrap, icons, chart.js)
│  └─ images/listings/    hand-picked WebP photos + credits.json
├─ resources/views/
│  ├─ layouts/ (app, dashboard)  ├─ components/ (listing-card, price, status-badge, gallery…)
│  ├─ public/ ├─ customer/ ├─ provider/ ├─ admin/ └─ auth/
├─ routes/web.php
├─ tests/Feature/ (Auth, Booking, Capacity, Pricing, Policies…)  tests/Unit/
├─ docker/ (nginx.conf, php.ini, start.sh)   Dockerfile   render.yaml
└─ docs/ ARCHITECTURE.md, er-diagram.mermaid, DEPLOYMENT.md, BOOKING_RULES.md
```

---

## 7. Performance plan ("it should work fast")

- Eager-load relationships (`with('coverImage','destination')`) — no N+1 queries; Laravel's `preventLazyLoading()` on in development to catch mistakes.
- Indexes listed above; server-side pagination (12 per page).
- Homepage rows and destination counts cached 10 min; cache cleared when a listing changes.
- No JS framework; one small `excursio.js`. Fetch API only for availability, slot loading and filters.
- Images: WebP, sized variants, lazy loading.
- Production: `php artisan optimize`, OPcache on, `APP_DEBUG=false`.
- **Honest limit:** on Render's free tier the app sleeps after 15 min idle and takes ~1 min to wake. That's a hosting limit, not a code problem — see §8.

---

## 8. Deployment plan (₹0) — Render vs Azure

Verified Oct 2026; will be re-checked in Phase 8.

| | **Option A: Render + Aiven MySQL** (recommended) | **Option B: Azure for Students** |
|---|---|---|
| App | Render free web service via **Docker** (PHP-FPM + Nginx) | App Service (Linux, PHP 8.3) |
| Database | Aiven free MySQL: 1 GB storage, 1 GB RAM, no card, no time limit | Azure Database for MySQL Flexible Server |
| Cost | ₹0 | ₹0 out of pocket — uses the **$100 / 12-month student credit** (sign-up via university email, no card) |
| Catches | App sleeps after 15 min idle (~1 min wake). 750 free hours/month. Disk wiped on restart. Aiven may **power off an idle free DB** (you get notified and can power it back on). | When credit or 12 months runs out, resources are **shut down** unless you upgrade to paid. Credit is for learning/dev, not commercial production. Must watch spending. |
| Why not Render's own DB? | Render has no MySQL, and its free Postgres **expires after 30 days** | — |

The code will be **deploy-ready for both**: one Dockerfile, a `render.yaml`, env-based config (DB SSL certificate for Aiven), DB sessions/cache, `storage` never used for permanent files, and image uploads disabled on hosted environments unless free object storage is approved later.

Other deployment risks to check in Phase 8: outbound email on free hosts (password-reset emails may need a free email API or will use the `log` driver in the demo), and Aiven connection limit (76) — fine for a demo.

---

## 9. Testing plan

PHPUnit/Pest feature tests on a separate MySQL test database: auth & roles, ownership (provider A can't edit provider B's listing), customer privacy (URL tampering → 404), overlapping stays, slot capacity, last-seat race (two bookings for one seat → one fails), price recalculation ignoring tampered input, status transitions, cancellation refunds, review eligibility & duplicates, dashboard metrics from seeded data. I'll report only tests that actually ran.

---

## 10. Signature features (approved)

| # | Feature | How it works | New schema |
|---|---|---|---|
| F1 | **One cart: stay + dinner + activity** | The cart lives in the DB session. At checkout, `BundleBookingService` locks every room/slot row **in a fixed order** (stay units, then slots, each by id) so concurrent checkouts can't deadlock, validates every item, and checks for clashes: dinner/activity times overlapping, items outside the stay dates, and a 60-minute buffer between items in different destinations. Everything is booked in **one transaction**: all items or none. | `booking_groups` (reference, customer, total, payment_status); `bookings.booking_group_id` (nullable) |
| F2 | **Waitlist for full slots** | "Join waitlist" on a full slot. When a booking on that slot is cancelled, `WaitlistService` offers the seats to the first entry that fits, with 30 minutes to claim. Offers that aren't claimed in time are skipped the next time anyone reads the waitlist (no cron needed). | `waitlist_entries` (time_slot_id, user_id, party_size, status `waiting/offered/claimed/expired`, offer_expires_at); unique (time_slot_id, user_id) |
| F3 | **QR check-in** | Each confirmed booking gets a random `checkin_token` shown as a QR code. The provider scans it with the phone camera (needs HTTPS, which Render provides) or types the reference. **Only checked-in bookings can be reviewed.** QR libraries are vendored JS files. | `bookings.checkin_token` (unique), `checked_in_at`, `checked_in_by` |
| F4 | **Weather for outdoor activities** | Open-Meteo forecast (free, no API key, non-commercial use), fetched server-side and cached 3 h. The forecast only covers about 16 days ahead, so for later dates it shows "Forecast available closer to your date". When a provider cancels a slot for weather, all affected bookings get a 100% refund and a **"Rebook next session"** button that keeps the same party size. | `activity_details.weather_dependent` (bool) |
| F6 | **Local names + smart destination search** | Destinations can have a local name shown small under the English one (e.g. "Kyoto · 京都", "Japan · 日本"). Search also matches **aliases**, so typing "Nihon", "Nippon", "日本" or "Kioto" finds Japan/Kyoto listings, and "Bangalore" finds Bengaluru, "Kodagu" finds Coorg. Matching is a simple indexed alias lookup, with no translation or external API. The site itself stays in English. | `destinations.local_name` (nullable); `destination_aliases` (destination_id, alias, unique, indexed) |
| F5 | **Refund timeline before booking** | `CancellationService::timeline()` shows the refund in ₹ for each window ("Until 15 Dec: ₹12,000 back · Until 21 Dec: ₹6,000 · After: ₹0"), on the detail page, at checkout and on the booking page. | none |

### Also added (standard features of professional booking sites)
- **Map** on detail and search pages (Leaflet + OpenStreetMap, free with attribution, light traffic).
- **Wishlist / saved listings** (`wishlists`, unique user+listing).
- **One-click demo logins** on the login page (customer / provider / admin) so recruiters can try every role without signing up. Demo accounts can't change their password or email, and an admin "Reset demo data" button reseeds the database.
- **Provider approval:** new provider accounts start as `pending` until an admin approves them.
- **Booking receipt** page that prints cleanly to PDF from the browser, labelled "Demo — no payment taken".
- Custom 403/404/419/500 pages, SEO meta and Open Graph tags, `robots.txt`.

---

## 11. Milestones

| Phase | Deliverable |
|---|---|
| 1 ✅ | This document + ER diagram |
| 2 ✅ | Laravel 13 install, MySQL config, design system (CSS tokens, layout, components), all migrations, seeders with ~30 synthetic listings across 8 destinations, homepage shell |
| 3 ✅ | Auth (register/login/reset/profile), roles, middleware, policies + tests |
| 4 ✅ | Browse pages, category rows, search + filters + sorting + pagination, detail pages, galleries, map, wishlist, weather (F4), local names + alias search (F6) |
| 5 ✅ | Booking engine: holds, locking, pricing, demo checkout, hotel approval, offline bookings, refund timeline (F5), cart bundles (F1), waitlist (F2) + concurrency tests |
| 6 ✅ | Customer, provider (calendar, availability, listing editor, QR check-in F3), admin dashboards with Chart.js, moderation, categories, reports, contact, audit log, notifications |
| 7 ✅ | Reviews (check-in only) + moderation + host replies, demo reset, security headers/CSP, security tests, full test run |
| 8 | Docs, GitHub, Dockerfile/render.yaml, deploy, end-to-end booking test on the public URL |
