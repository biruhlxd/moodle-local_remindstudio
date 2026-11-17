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

/**
 * Learners snoozing or stopping the reminders of a course, through a signed link that works without logging in.
 *
 * Essential rules (such as compliance deadlines) still reach learners who opted out.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class optout {
    /** @var int How long a snooze lasts. */
    public const SNOOZE = 7 * DAYSECS;

    /**
     * The link to the page where a learner snoozes or stops a course's reminders.
     *
     * @param int $userid
     * @param int $courseid
     * @return \moodle_url
     */
    public static function url(int $userid, int $courseid): \moodle_url {
        return new \moodle_url(
            '/local/remindstudio/optout.php',
            ['u' => $userid, 'c' => $courseid, 't' => self::token($userid, $courseid)]
        );
    }

    /**
     * Whether a token is the one for this learner and course.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $token
     * @return bool
     */
    public static function check(int $userid, int $courseid, string $token): bool {
        return hash_equals(self::token($userid, $courseid), $token);
    }

    /**
     * Until when the learner opted out of the course's reminders.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int|null Null when they get reminders; 0 when stopped until they turn them back on.
     */
    public static function until(int $userid, int $courseid, int $now): ?int {
        global $DB;
        $until = $DB->get_field('local_remindstudio_optout', 'timeuntil', ['userid' => $userid, 'courseid' => $courseid]);
        if ($until === false || ($until > 0 && $until <= $now)) {
            return null;
        }
        return (int) $until;
    }

    /**
     * Snooze, stop or turn back on a course's reminders for a learner.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|null $until 0 to stop, a time to snooze, null to turn them back on.
     */
    public static function set(int $userid, int $courseid, ?int $until): void {
        global $DB;
        $where = ['userid' => $userid, 'courseid' => $courseid];
        $DB->delete_records('local_remindstudio_optout', $where);
        if ($until !== null) {
            $DB->insert_record('local_remindstudio_optout', $where + ['timeuntil' => $until, 'timecreated' => time()]);
        }
    }

    /**
     * SQL that is true for the learner u.id when they opted out of the course.
     *
     * @param string $courseparam Placeholder of the course id.
     * @param string $nowparam Placeholder of the time.
     * @return string
     */
    public static function sql(string $courseparam, string $nowparam): string {
        return "EXISTS (SELECT 1 FROM {local_remindstudio_optout} oo
                         WHERE oo.userid = u.id AND oo.courseid = $courseparam
                               AND (oo.timeuntil = 0 OR oo.timeuntil > $nowparam))";
    }

    /**
     * The token for a learner and course.
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    private static function token(int $userid, int $courseid): string {
        $secret = get_config('local_remindstudio', 'secret');
        if (!$secret) {
            $secret = random_string(40);
            set_config('secret', $secret, 'local_remindstudio');
        }
        return substr(hash_hmac('sha256', "$userid:$courseid", $secret), 0, 32);
    }
}
