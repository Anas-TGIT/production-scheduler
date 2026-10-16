# Production Scheduler

A Laravel 13 application that sequences manufacturing orders on a single production line, taking into account
production rates, need-by dates and changeover delays between product types.

- **Baseline:** Earliest Due Date (EDD), with a deterministic tie-break on order ID.
- **Bonus:** a changeover-aware local search that batches similar products, compared side by side with EDD.
- **UI:** order form (create, edit, delete), schedule table, timeline and summary metrics.

Why each choice was made: [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Run it

**With Docker** (nothing else needed):

```bash
docker compose up -d --build
```

**With local PHP** (PHP 8.3+, Composer 2, `pdo_sqlite`; no Node.js needed):

```bash
composer setup
php artisan serve
```

Open <http://localhost:8000>. Both commands create the database and load the catalogue plus 6 demo orders due
tomorrow and the day after. `composer setup` uses `migrate:fresh`, so running it again resets the data.

```bash
php artisan test          # 80 tests
./vendor/bin/pint --test  # code style
```

| Variable | Default | Meaning |
|---|---|---|
| `SCHEDULING_CHANGEOVER_MINUTES` | `30` | Delay when switching product type (≥ 0) |
| `SCHEDULING_DISPLAY_TIMEZONE` | `America/Toronto` | Timezone of form input and displayed times |
| `SCHEDULING_HEURISTIC_MAX_SIMULATED_ORDERS` | `200000` | Work budget of the heuristic (`0` = EDD only) |

The Docker container runs with `APP_DEBUG=false`; use `DOCKER_APP_DEBUG=true docker compose up -d` to debug.

## How it works

| Type | Rate | Products |
|---|---|---|
| Type 1 | 715 units/h | A, B, F |
| Type 2 | 770 units/h | C |
| Type 3 | 1,000 units/h | D, E |

```
app/Domain/Scheduling/          pure PHP: no database or framework calls
  ProductionDurationCalculator    quantities → minutes
  TimelineSimulator               sequence → start/finish times, setup, lateness, metrics
  Strategies/                     EarliestDueDateStrategy, ChangeoverAwareStrategy
  OrderSchedulingMapper           Eloquent orders → immutable DTOs
app/Data/                       laravel-data input objects + Rules/ (same type, future deadline)
app/Http/Controllers/           thin: OrderController, ScheduleController
app/Support/DisplayTime.php     display timezone ↔ UTC conversion
```

`GET /schedule` loads the orders with their products, maps them to DTOs, lets a strategy choose the order,
and the `TimelineSimulator` computes every time. It is the only place where timing rules live.

## Rules and assumptions

- One line, one order at a time, never interrupted or split; production is continuous (no shifts or holidays).
- All orders are available when the schedule starts (the page's start field, or now by default).
- **Duration:** `ceil(total quantity × 60 / rate)` minutes, rounded up once per order, not per line
  (1,430 Type 1 units = 120 min; 75 A + 75 B = 13 min, not 14).
- **Setup:** none before the first order or between orders of the same type; otherwise the configured delay.
- **On time** means finishing at or before the need-by time; one minute later is late.
- **Times** are stored in UTC and typed and shown in the display timezone.
- **Validation:**
  - at least one product line;
  - existing products, each listed only once;
  - whole quantities from 1 to 1,000,000;
  - all products of the same type, checked against the database;
  - a need-by date in the future. An edited order may keep its unchanged past deadline.

  Order and lines are saved in one transaction.
- An order that can no longer be scheduled (e.g. mixed types after a catalogue change) is left out with a
  warning instead of breaking the page.

## Example

Start 08:00, 30-minute changeover: A 1,430 due 12:00, C 770 due 15:00, B 715 due 17:00.

| Sequence | Finishes | Setup | Late |
|---|---|---|---|
| EDD: A → C → B | 13:00 | 60 min | 0 |
| Heuristic (A and B grouped) | 12:30 | 30 min | 0 |

On the demo data from tomorrow 08:00, the heuristic cuts setup from 2h30 to 1h00 with no late orders.

## Trade-offs and limitations

- The schedule is recalculated on each request; it is not saved or versioned.
- The heuristic is a local search: it improves on EDD but is not proven optimal. Its time is capped at
  about 1.2 s whatever the number of orders; on large backlogs it may stop early.
- SQLite, no authentication, permanent deletes, single machine, no working calendars.
- Tailwind comes from a CDN to avoid a front-end build step; a production app would compile it.
- The Docker image uses PHP's built-in server: fine for a demo, not for production (use PHP-FPM + Nginx).

## Possible next steps

Working calendars, several production lines, saved schedule versions, background recalculation for large
backlogs, and stronger optimisation behind the same `SchedulingStrategy` interface.
