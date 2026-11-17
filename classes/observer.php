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

/**
 * Starts learners on the rules that begin with an event.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A learner was enrolled.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        self::start((int) $event->courseid, (int) $event->relateduserid, rule::TRIGGER_ENROLLED);
    }

    /**
     * A learner completed a course.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        self::start((int) $event->courseid, (int) $event->relateduserid, rule::TRIGGER_COURSECOMPLETED);
    }

    /**
     * Start the learner on the course's enabled rules with this trigger.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $trigger
     */
    private static function start(int $courseid, int $userid, string $trigger): void {
        if (!get_config('local_remindstudio', 'enabled')) {
            return;
        }
        $scanner = new scanner(time());
        foreach (rule::get_enabled_for_course($courseid) as $rule) {
            if ($rule->triggertype === $trigger) {
                $scanner->start_for_event($rule, $userid);
            }
        }
    }
}
