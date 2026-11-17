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

use stdClass;

/**
 * The {placeholders} a reminder's subject and body can use.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class placeholders {
    /** @var string[] The placeholder names. */
    public const NAMES = [
        'firstname', 'lastname', 'fullname', 'coursename', 'courseurl', 'courselink', 'progress', 'expectedprogress',
        'deadline', 'daysleft', 'lastaccess', 'nextactivity', 'sitename',
    ];

    /**
     * The placeholders in a text that don't exist.
     *
     * @param string $text
     * @return string[]
     */
    public static function unknown(string $text): array {
        preg_match_all('/\{([a-z]+)\}/', $text, $matches);
        return array_values(array_diff(array_unique($matches[1]), self::NAMES));
    }

    /**
     * Fill in the placeholders.
     *
     * @param string $text
     * @param array $values From values().
     * @return string
     */
    public static function replace(string $text, array $values): string {
        $pairs = [];
        foreach ($values as $name => $value) {
            $pairs['{' . $name . '}'] = $value;
        }
        return strtr($text, $pairs);
    }

    /**
     * The values of the placeholders for a learner, in the current language.
     *
     * @param stdClass $user
     * @param stdClass $course
     * @param stdClass|null $pace The learner's row in local_remindstudio_pace, if any.
     * @param bool $html Escape values and give links as HTML (for the body), or plain text (for the subject).
     * @param int $now
     * @return array By name.
     */
    public static function values(stdClass $user, stdClass $course, ?stdClass $pace, bool $html, int $now): array {
        global $DB, $SITE;
        $context = \context_course::instance($course->id);
        $timezone = \core_date::get_user_timezone($user);
        $notknown = get_string('notknown', 'local_remindstudio');
        $date = fn(int $time) => userdate($time, get_string('strftimedate', 'langconfig'), $timezone);
        $link = fn(string $url, string $name) => $html ? \html_writer::link($url, s($name)) : "$name ($url)";

        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $user->id, 'courseid' => $course->id]);
        $end = (int) ($pace->expectedend ?? 0);
        $next = self::next_activity($user, $course);

        $values = [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'fullname' => fullname($user),
            'coursename' => $coursename,
            'courseurl' => $courseurl,
            'progress' => isset($pace->progresspct) ? $pace->progresspct . '%' : $notknown,
            'expectedprogress' => isset($pace->expectedpct) ? $pace->expectedpct . '%' : $notknown,
            'deadline' => $end ? $date($end) : $notknown,
            'daysleft' => $end ? (string) max(0, (int) ceil(($end - $now) / DAYSECS)) : $notknown,
            'lastaccess' => $lastaccess ? $date((int) $lastaccess) : get_string('never'),
            'sitename' => format_string($SITE->fullname, true, ['context' => \context_system::instance()]),
        ];
        if ($html) {
            $values = array_map('s', $values);
        }
        $values['courselink'] = $link($courseurl, $coursename);
        $values['nextactivity'] = $next ? $link($next->url->out(false), $next->get_formatted_name()) : $values['courselink'];
        return $values;
    }

    /**
     * The first activity, in course order, the learner can open and hasn't completed.
     *
     * @param stdClass $user
     * @param stdClass $course
     * @return \cm_info|null
     */
    private static function next_activity(stdClass $user, stdClass $course): ?\cm_info {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return null;
        }
        foreach (get_fast_modinfo($course, $user->id)->get_cms() as $cm) {
            if (
                $cm->uservisible && $cm->url && $completion->is_enabled($cm)
                    && !in_array(
                        $completion->get_data($cm, false, $user->id)->completionstate,
                        [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]
                    )
            ) {
                return $cm;
            }
        }
        return null;
    }
}
