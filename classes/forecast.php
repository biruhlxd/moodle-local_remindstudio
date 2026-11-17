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

namespace local_remindstudio;

use local_remindstudio\engine\scanner;
use local_remindstudio\pace\calculator;

/**
 * Who is going to receive which reminder in the coming days.
 *
 * An estimate: learners already in a rule get their next steps on time; for rules checked on a scan, the conditions
 * are checked for each coming day, so time-based ones (inactive, days since enrolment, finish date) move on, while
 * progress stays as it is today. Sending limits and the send window are not taken into account.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forecast {
    /**
     * Constructor.
     *
     * @param int $now
     * @param int $days How far ahead to look.
     */
    public function __construct(
        /** @var int The time to look ahead from. */
        private readonly int $now,
        /** @var int How many days ahead to look. */
        private readonly int $days = 14,
    ) {
    }

    /**
     * The reminders coming in a course.
     *
     * @param int $courseid
     * @return \stdClass[] Each: time, userid, rule, step (number), subject, starts (the learner joins the rule then);
     *     in time order.
     */
    public function course(int $courseid): array {
        global $DB;
        (new calculator($this->now))->update_if_stale($courseid);
        $end = $this->now + $this->days * DAYSECS;
        $rows = [];
        foreach (rule::get_enabled_for_course($courseid) as $rule) {
            $steps = $rule->get_steps();
            if (!$steps) {
                continue;
            }
            $tracks = $DB->get_records_select(
                'local_remindstudio_track',
                'ruleid = :ruleid AND courseid = :courseid AND status <> :cleared',
                ['ruleid' => $rule->id, 'courseid' => $courseid, 'cleared' => 'cleared']
            );
            $ontrack = [];
            foreach ($tracks as $track) {
                $ontrack[$track->userid] = true;
                if ($track->status === 'active') {
                    $time = max($this->now, (int) $track->timenextdue);
                    $this->chain($rows, $rule, $steps, (int) $track->userid, $track->nextstep - 1, $time, $end, false);
                }
            }
            if ($rule->triggertype !== rule::TRIGGER_SCAN) {
                continue;
            }
            for ($day = 0; $day <= $this->days; $day++) {
                $time = $this->now + $day * DAYSECS;
                foreach ((new scanner($time))->matching_users($rule) as $userid) {
                    if (empty($ontrack[$userid])) {
                        $ontrack[$userid] = true;
                        $this->chain($rows, $rule, $steps, $userid, 0, $time + (int) $steps[0]->delay, $end, true);
                    }
                }
            }
        }
        usort($rows, fn($a, $b) => [$a->time, $a->userid] <=> [$b->time, $b->userid]);
        return $rows;
    }

    /**
     * Add a learner's steps from one step on, while they fall in the period.
     *
     * @param array $rows
     * @param rule $rule
     * @param \stdClass[] $steps
     * @param int $userid
     * @param int $index Of the first step.
     * @param int $time Of the first step.
     * @param int $end
     * @param bool $starts Whether the learner joins the rule at the first step.
     */
    private function chain(
        array &$rows,
        rule $rule,
        array $steps,
        int $userid,
        int $index,
        int $time,
        int $end,
        bool $starts
    ): void {
        $first = $index;
        while (isset($steps[$index]) && $time <= $end) {
            $rows[] = (object) [
                'time' => $time,
                'userid' => $userid,
                'rule' => $rule,
                'step' => $index + 1,
                'subject' => $steps[$index]->subject,
                'starts' => $starts && $index === $first,
            ];
            $index++;
            $time += (int) ($steps[$index]->delay ?? 0);
        }
    }
}
