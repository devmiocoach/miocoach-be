# Checkins — Deferred Work (modules_todo)

Body metrics and progress photos are owned by the `Progress` module — this module covers check-in forms only.

Minimal scheduling (`checkins:dispatch`, hourly) ships now.

## Resolved

- **Dispatch idempotency/dedupe**: a `checkin_dispatches` marker table (unique on `checkin_form_id`, `client_id`, `period_key`) records every reminder actually sent. The command relies on the unique-index violation, not a prior `exists()` check, so concurrent runs can't double-send.
- **Missed-hour catch-up**: `isDue` no longer requires an exact hour match — any run at or after `send_time` on the due day fires (and dedupe prevents re-sending once it has). Limitation: a due day skipped entirely (e.g. a multi-day outage) is not retroactively caught up; the client simply gets no reminder for that period.
- **Biweekly correctness**: the week count between assignment and now is computed from `startOfWeek()`-aligned dates before diffing, instead of diffing raw timestamps — removes the off-by-one risk from a DST shift or a time-of-day gap landing near a week boundary.
- **Per-client timezone**: `clients.timezone` (new column, default `Europe/Rome`, added via a guarded migration in this module since the table is owned by `Users`) is used to evaluate both `send_time` and the day-of-week/day-of-month due-checks in the client's local time, not the app timezone.

## Still deferred

- **Real push transport**: `StubPushService` only logs. Add a device-token table, an FCM/Expo driver, and a real notification channel; rebind `PushServiceContract`.
- **Full-outage catch-up**: if the scheduler is down for an entire due day (not just a missed hour), that period's reminder is not sent retroactively. Would need a lookback window with its own dedupe-period semantics.
- **Per-coach timezone**: only the client's timezone is honored; a coach viewing/creating forms doesn't get timezone-aware `send_time` display.
