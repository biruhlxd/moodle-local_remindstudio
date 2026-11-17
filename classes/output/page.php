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

namespace local_remindstudio\output;

/**
 * What the course pages of Reminder Studio share: access checks, page setup and the tabs.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page {
    /** @var string[] Tab strings by script, in order; pacing is for those who manage rules. */
    private const TABS = [
        'index' => 'tab_rules',
        'upcoming' => 'tab_upcoming',
        'learners' => 'tab_learners',
        'log' => 'tab_log',
        'pacing' => 'tab_pacing',
    ];

    /**
     * Check access and set up the page.
     *
     * @param string $script Such as "index".
     * @param int $courseid
     * @param string $capability viewcourse or managecourse.
     * @param array $params Other URL parameters.
     * @return \stdClass The course.
     */
    public static function setup(string $script, int $courseid, string $capability, array $params = []): \stdClass {
        global $PAGE;
        $course = get_course($courseid);
        // The page has called require_login() already.
        $context = \context_course::instance($course->id);
        require_capability('local/remindstudio:' . $capability, $context);
        $PAGE->set_url(new \moodle_url("/local/remindstudio/$script.php", ['id' => $courseid] + $params));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(get_string('pluginname', 'local_remindstudio') . \moodle_page::TITLE_SEPARATOR
            . format_string($course->shortname, true, ['context' => $context]));
        $PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
        return $course;
    }

    /**
     * The page header with the plugin's heading and tabs.
     *
     * @param \stdClass $course
     * @param string $current Script of the current tab.
     * @return string
     */
    public static function header(\stdClass $course, string $current): string {
        global $OUTPUT;
        $canmanage = has_capability('local/remindstudio:managecourse', \context_course::instance($course->id));
        $tabs = [];
        foreach (self::TABS as $script => $string) {
            if ($script !== 'pacing' || $canmanage) {
                $tabs[] = new \tabobject(
                    $script,
                    new \moodle_url("/local/remindstudio/$script.php", ['id' => $course->id]),
                    get_string($string, 'local_remindstudio')
                );
            }
        }
        $html = $OUTPUT->header() . $OUTPUT->heading(get_string('pluginname', 'local_remindstudio'));
        $html .= $OUTPUT->tabtree($tabs, $current);
        if (!get_config('local_remindstudio', 'enabled')) {
            $html .= $OUTPUT->notification(get_string('siteoff', 'local_remindstudio'), 'warning');
        }
        return $html;
    }

    /**
     * The name of a learner, linked to their timeline.
     *
     * @param \stdClass $user With the name fields.
     * @param int $courseid
     * @return string
     */
    public static function learner_link(\stdClass $user, int $courseid): string {
        return \html_writer::link(
            new \moodle_url('/local/remindstudio/learner.php', ['id' => $courseid, 'userid' => $user->id]),
            fullname($user)
        );
    }

    /**
     * A percentage, or a dash when not known.
     *
     * @param mixed $value
     * @return string
     */
    public static function percent($value): string {
        return $value === null ? '-' : $value . '%';
    }

    /**
     * A date, or a dash for none.
     *
     * @param int $time
     * @return string
     */
    public static function date(int $time): string {
        return $time ? userdate($time, get_string('strftimedatetimeshort', 'langconfig')) : '-';
    }
}
