# Technical decisions

Why the project is built the way it is. What is implemented is described as such; ideas for later are listed
at the end.

## 1. EDD as the baseline

Orders are sorted by need-by date, then by ID so that two orders due at the same time always come out in the
same order. EDD is simple, fast (one sort) and easy to explain: produce first what is due first.

Its limit is that it ignores changeovers. In the brief's example (A Type 1 due 12:00, C Type 2 due 15:00,
B Type 1 due 17:00), EDD produces A → C → B: two changeovers, 60 minutes of setup, finished at 13:00.
Grouping A and B gives one changeover and finishes at 12:30, with every order still on time. The classic
guarantees of EDD assume no sequence-dependent setup times, so they do not apply here. EDD is a baseline,
not an optimum.

## 2. Choosing the order is separate from computing the times

Two questions are kept apart: *in which order* to produce (a `SchedulingStrategy`), and *when* each order
starts and finishes (the `TimelineSimulator`). Every strategy, including the heuristic that tests many
candidate sequences, uses the same simulator. The timing rules (no setup before the first order, setup only
when the type changes, rounding) exist in exactly one place, so a future rule such as working hours would
apply to every strategy at once.

## 3. A pure domain and thin controllers

`app/Domain/Scheduling` is plain PHP: no database queries and no framework calls. The controller loads the
orders with eager loading (no N+1 queries), a mapper turns them into immutable DTOs, and the scheduler does
the rest. The engine's unit tests therefore run without a database, in milliseconds, and the business logic is
not spread across controllers, models and views.

The `SchedulingStrategy` interface exists because there are two real strategies, compared on the same data.
Nothing else was added for its own sake: no repositories, no events, no extra layers. Eloquent is enough for
four tables.

## 4. The heuristic

The brief's bonus asks for batching similar products. The heuristic starts from the EDD sequence and keeps
the best improving move among all swaps of two orders and all moves of one order to another position. Each
candidate is scored by the simulator and compared in this order: number of late orders, total lateness,
setup time, then total duration. Being on time always matters more than saving setup time. Moves are tried in
a fixed order and only strict improvements are accepted, so the result is always the same for the same data.
It reaches a local optimum, which is not proven to be the best possible schedule.

Its cost is capped by a work budget: the number of order placements it may simulate. A first version capped
the number of orders instead; measured, that took 89 seconds for 60 orders. With the budget, the time stays
around 1.2 seconds whatever the size, and the best sequence found so far is returned, never worse than EDD.
The heuristic only runs when it is selected.

## 5. Durations and time

- Durations are `ceil(total quantity × 60 / rate)` minutes, computed with integers and rounded once per order.
  Rounding each line would add minutes: 75 A + 75 B takes 13 minutes, not 14.
- Times are stored in UTC and shown in a configurable timezone (America/Toronto). Dates use
  `CarbonImmutable`, so adding minutes to a start time never changes the caller's value.
- The need-by date must be in the future. Laravel's `after:now` rule would read the typed time as UTC, so
  the value is first converted from the display timezone and then compared. When an order is edited, its
  unchanged past deadline may be kept, otherwise a typo in the customer name could not be fixed.

## 6. Validation

Input goes through spatie/laravel-data objects instead of form requests: the controller receives a typed,
validated object, and errors redirect back to the form with the typed values. The same object validates
creation and editing. The two business checks are rule classes in `app/Data/Rules`: all products of an order
share one type (read from the database, never from a type sent by the browser), and the deadline is in the
future. An order and its lines are saved in one transaction, so a failure never leaves half an order.

## 7. Robustness

- An order that can no longer be scheduled (for example, mixed types after a catalogue change) is left out
  with a warning and a link to fix it, instead of making the whole page fail.
- The demo seeder only fills an empty table, so running it twice does not duplicate orders.
- The Docker container runs with debug turned off by default.

## 8. Tests

The business rules are tested first, because a rounding or setup error does not show in the UI but shifts
the whole schedule:
- 1,430 Type 1 units take 120 minutes, and quantities are summed before rounding;
- no setup before the first order or between same-type orders;
- finishing exactly at the deadline is on time, and one minute later is late;
- empty input gives an empty schedule;
- the brief's three-order example.

Feature tests cover the form (mixed types refused, invalid quantities, nothing saved on failure), the
schedule page, editing and deleting. Tests that depend on the current time freeze the clock.

## 9. Trade-offs made to fit 2–3 days

Single machine, continuous production, schedule recalculated on each request, SQLite, no authentication,
permanent deletes, Tailwind from a CDN, and PHP's built-in server in Docker. Each of them keeps the project
easy to run and review, and each can be replaced without touching the scheduling engine.

## 10. How it could evolve (not implemented)

- **Several lines:** first assign each order to a line, then sequence each line with the same simulator.
- **Working hours:** a calendar injected into the simulator that skips nights, weekends and holidays; the
  strategies would not change.
- **Saved schedules:** store each computed schedule as a version, and freeze the first hours that are
  already running on the floor.
- **Large backlogs:** recalculate in a queued job when an order changes, and show the latest version.
- **Better optimisation:** simulated annealing, tabu search or a constraint solver, behind the same
  `SchedulingStrategy` interface and compared with EDD on the same metrics.
