# Reminder Studio for Moodle

Automatic reminders and nudges for learners, built around each learner's own deadline and pace.

- **Pace model**: for self-paced, fixed-date and open courses, every learner gets a start date, an expected finish
  date (with per-learner extensions) and a "behind pace" figure.
- **Rules** with ALL/ANY conditions: never opened the course, inactive for N days, days since enrolment, progress,
  behind pace, finish date coming or passed, activity or course completed.
- **Sequences** of up to 5 steps (a nudge, a second nudge, then a copy to the teachers), which stop when the learner
  comes back, completes the course or completes an activity.
- **Good manners**: daily limits per learner, priorities, a send window in each learner's time zone, dry runs, and a
  link in every reminder to snooze or stop a course's reminders.
- Rules can start on a scan, on enrolment or on course completion. Messages use Moodle notifications (web, email,
  mobile), with placeholders such as `{firstname}`, `{progress}`, `{deadline}`, `{daysleft}` and `{nextactivity}`.

## Requirements

Moodle 4.5 to 5.3, PHP 8.1 or later. Course completion tracking makes the progress and pace figures more useful but is
not required.

## Installation

Copy the plugin to `local/remindstudio` (or install the ZIP from *Site administration > Plugins > Install plugins*)
and visit *Site administration > Notifications*. Cron must run: a scheduled task finds the reminders that are due and
background tasks send them.

## Use

In a course, *More > Reminders* has these tabs:

- **Rules**: add a rule from a recipe (Welcome, Never started, Inactive learners, Behind pace, Finish date coming,
  Finish date passed, Course completed) or start blank, then adjust who gets it, when it stops and the messages. A
  rule can run as a dry run first: it is logged but nothing is sent. *Pause this course's reminders* stops all of
  them at once.
- **Upcoming**: the reminders that would go out in the next 14 days.
- **Learners**: each learner's start and finish dates, progress and pace, with a timeline and extensions.
- **Log**: each reminder that was queued, sent, failed or logged in a dry run.
- **Pacing**: how start and finish dates are worked out for the course.

Site-wide settings (send window, daily limits per learner, default pacing, log retention) are at *Site administration > Plugins > Local
plugins > Reminder Studio*. Rules are included in course backup and restore.

## Capabilities

- `local/remindstudio:viewcourse`: see reminders and learners' pace in a course (teachers, managers).
- `local/remindstudio:managecourse`: manage the rules of a course (editing teachers, managers).
- `local/remindstudio:receivecopy`: receive the teacher copies of reminders.
- `local/remindstudio:managesite`: manage the site settings.

## For developers

Other plugins can add conditions and actions through the `\local_remindstudio\hook\collect_types` hook, and can
apply rules to courses or add courses to the scan through the `course_rules` and `scan_courses` hooks.

## Privacy

The plugin stores each learner's pace data, extensions, opt-out choices and a log of reminders sent. It implements
the Privacy API for export and deletion. Nothing is sent outside the site.

## Licence

GNU GPL v3 or later. © 2026 Biruh Alemnew
