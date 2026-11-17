<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_remindstudio\pace;

use stdClass;

/**
 * Works out each learner's dates and pace in a course and stores them in local_remindstudio_pace.
 *
 * - Start: the course start date for fixed dates, otherwise when the learner was enrolled.
 * - Expected finish: the learner's extension if any; otherwise the course end date (fixed dates), or the start plus the
 *   course duration, or the end of the enrolment (self-paced); none for open courses.
 * - Progress: share of the activities with completion tracking that the learner has completed.
 * - Expected progress: share of those activities whose target date has passed. The target date is the activity's
 *   "expect completed on" date; with auto-pace, activities without one are spread evenly from start to finish in
 *   course order. With no target dates at all, it is the share of the learner's time that has passed.
 * - Pace gap: expected minus actual progress, in percentage points (positive means behind).
 *
 * Restrictions on activities are not taken into account yet: every tracked, visible activity counts.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculator {
    /** @var int How long values are used before they are worked out again, in seconds. */
    public const MAX_AGE = HOURSECS;

    /**
     * Constructor.
     *
     * @param int $now
     */
    public function __construct(
        /** @var int The time to work out pace at. */
        private readonly int $now,
    ) {
    }

    /**
     * Work out the course again if its values are older than MAX_AGE.
     *
     * @param int $courseid
     */
    public function update_if_stale(int $courseid): void {
        global $DB;
        $last = (int) $DB->get_field_sql(
            'SELECT MAX(timecalculated) FROM {local_remindstudio_pace} WHERE courseid = ?',
            [$courseid]
        );
        if ($last <= $this->now - self::MAX_AGE || $last > $this->now) {
            $this->update_course($courseid);
        }
    }

    /**
     * Work out and store the values of every learner in a course.
     *
     * @param int $courseid
     */
    public function update_course(int $courseid): void {
        global $DB;
        $course = get_course($courseid);
        $settings = course_settings::get($courseid);
        $context = \context_course::instance($courseid);

        [$esql, $params] = get_enrolled_sql($context, 'moodle/course:isincompletionreports', 0, true);
        $params['courseid'] = $courseid;
        $enrolments = $DB->get_recordset_sql("
            SELECT ue.id, ue.userid, ue.timestart, ue.timeend, ue.timecreated
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
              JOIN ($esql) je ON je.id = ue.userid
             WHERE ue.status = 0 AND e.status = 0", $params);
        $learners = [];
        foreach ($enrolments as $ue) {
            $enrolled = (int) ($ue->timestart ?: $ue->timecreated);
            $learner = $learners[$ue->userid] ??= (object) ['enrolled' => $enrolled, 'enrolend' => (int) $ue->timeend];
            $learner->enrolled = min($learner->enrolled, $enrolled);
            // One enrolment without an end means no end.
            $learner->enrolend = $learner->enrolend && $ue->timeend ? max($learner->enrolend, (int) $ue->timeend) : 0;
        }
        $enrolments->close();

        $activities = $this->tracked_activities($course);
        $completed = [];
        if ($activities && $learners) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($activities), SQL_PARAMS_NAMED);
            // Completion states 1 and 2 are COMPLETION_COMPLETE and COMPLETION_COMPLETE_PASS.
            $completed = $DB->get_records_sql_menu("
                SELECT userid, COUNT(1)
                  FROM {course_modules_completion}
                 WHERE coursemoduleid $insql AND completionstate IN (1, 2)
              GROUP BY userid", $inparams);
        }
        $extensions = $DB->get_records_menu('local_remindstudio_override', ['courseid' => $courseid], '', 'userid, expectedend');

        $existing = $DB->get_records('local_remindstudio_pace', ['courseid' => $courseid], '', '*');
        $byuser = [];
        foreach ($existing as $row) {
            $byuser[$row->userid] = $row;
        }
        $transaction = $DB->start_delegated_transaction();
        foreach ($learners as $userid => $learner) {
            $row = $this->learner_values(
                $course,
                $settings,
                $learner,
                $activities,
                (int) ($completed[$userid] ?? 0),
                (int) ($extensions[$userid] ?? 0)
            );
            $row->courseid = $courseid;
            $row->userid = $userid;
            $row->timecalculated = $this->now;
            if (isset($byuser[$userid])) {
                $row->id = $byuser[$userid]->id;
                $DB->update_record('local_remindstudio_pace', $row);
                unset($byuser[$userid]);
            } else {
                $DB->insert_record('local_remindstudio_pace', $row);
            }
        }
        if ($byuser) {
            $DB->delete_records_list('local_remindstudio_pace', 'id', array_column($byuser, 'id'));
        }
        $transaction->allow_commit();
    }

    /**
     * The activities with completion tracking, in course order.
     *
     * @param stdClass $course
     * @return int[] Target date by course module id (0 when it has none).
     */
    private function tracked_activities(stdClass $course): array {
        if (!$course->enablecompletion) {
            return [];
        }
        $activities = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->completion && $cm->visible && !$cm->deletioninprogress) {
                $activities[$cm->id] = (int) $cm->completionexpected;
            }
        }
        return $activities;
    }

    /**
     * The values of one learner.
     *
     * @param stdClass $course
     * @param stdClass $settings From course_settings::get().
     * @param stdClass $learner enrolled and enrolend.
     * @param int[] $activities From tracked_activities().
     * @param int $completed How many of them the learner has completed.
     * @param int $extension The learner's own expected finish date, or 0.
     * @return stdClass
     */
    private function learner_values(
        stdClass $course,
        stdClass $settings,
        stdClass $learner,
        array $activities,
        int $completed,
        int $extension
    ): stdClass {
        $start = $settings->pacingmode === course_settings::FIXED ? (int) $course->startdate : $learner->enrolled;
        $end = match ($settings->pacingmode) {
            course_settings::FIXED => (int) $course->enddate,
            course_settings::SELFPACED => $settings->duration ? $start + $settings->duration : $learner->enrolend,
            default => 0,
        };
        $end = $extension ?: $end;

        $total = count($activities);
        $progress = $total ? (int) round(100 * min($completed, $total) / $total) : null;
        $expected = $this->expected_progress($start, $end, array_values($activities), (bool) $settings->autopace);

        return (object) [
            'timeenrolled' => $learner->enrolled,
            'timestart' => $start,
            'expectedend' => $end,
            'progresspct' => $progress,
            'expectedpct' => $expected,
            'pacegap' => $progress === null || $expected === null ? null : $expected - $progress,
        ];
    }

    /**
     * Where the learner should be by now, in percent.
     *
     * @param int $start
     * @param int $end 0 when there is no deadline.
     * @param int[] $targets Target date of each tracked activity in course order (0 when it has none).
     * @param bool $autopace Spread activities without a target date between start and end.
     * @return int|null Null when it can't be known.
     */
    private function expected_progress(int $start, int $end, array $targets, bool $autopace): ?int {
        $total = count($targets);
        if (!$total) {
            return null;
        }
        $window = $end > $start ? $end - $start : 0;
        if ($autopace && $window) {
            foreach ($targets as $i => $target) {
                $targets[$i] = $target ?: (int) ($start + $window * ($i + 1) / $total);
            }
        }
        $dated = array_filter($targets);
        if ($dated) {
            $due = count(array_filter($dated, fn(int $target) => $target <= $this->now));
            return (int) round(100 * $due / count($dated));
        }
        if (!$window) {
            return null;
        }
        return (int) round(100 * min(1, max(0, ($this->now - $start) / $window)));
    }
}
