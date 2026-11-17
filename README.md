# Reminder Studio for Moodle

Automatic reminders and nudges for learners, built around each learner's own deadline and pace.

- **Pace model**: for self-paced, fixed-date and open courses, every learner gets a start date, an expected finish
  date (with per-learner extensions) and a "behind pace" figure.
- **Rules** with ALL/ANY conditions: never opened the course, inactive for N days, days since enrolment, progress,
  behind pace, finish date coming or passed, activity or course completed.
- **Sequences** of up to 5 steps (a nudge, a second nudge, then a copy to the teachers), which stop when the learner
  comes back, completes the course or completes an activity.
- **Good manners**: daily limits per learner, priorities, a send window in each learner's time zone, dry runs.
- Rules can start on a scan, on enrolment or on course completion. Messages use Moodle notifications (web, email,
  mobile), with placeholders such as `{firstname}`, `{progress}`, `{deadline}`, `{daysleft}` and `{nextactivity}`.

Other plugins can add conditions and actions through the `\local_remindstudio\hook\collect_types` hook.

**Status:** 0.1.0 (alpha), the rules engine. Course screens (rule editor, recipes, forecast, learner timeline,
opt-out) come in the next release.

Requires Moodle 4.5 or later.

## Installation

Copy the plugin to `local/remindstudio` and visit *Site administration > Notifications*. Settings are at
*Site administration > Plugins > Local plugins > Reminder Studio*.

## Licence

GNU GPL v3 or later. © 2026 Biruh Alemnew, [edtechtools.shop](https://edtechtools.shop)
