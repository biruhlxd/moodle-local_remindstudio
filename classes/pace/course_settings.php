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
 * How a course is paced, and whether its reminders are paused.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_settings {
    /** @var string Fixed dates if the course has an end date, otherwise open. */
    public const AUTO = 'auto';
    /** @var string No deadline. */
    public const OPEN = 'open';
    /** @var string Each learner has a set time from their enrolment. */
    public const SELFPACED = 'selfpaced';
    /** @var string Everyone has the course's start and end dates. */
    public const FIXED = 'fixed';

    /**
     * The settings of a course, with "auto" resolved.
     *
     * @param int $courseid
     * @return stdClass pacingmode (open, selfpaced or fixed), duration (seconds), autopace, paused.
     */
    public static function get(int $courseid): stdClass {
        global $DB;
        $record = $DB->get_record('local_remindstudio_course', ['courseid' => $courseid]);
        $settings = (object) [
            'pacingmode' => $record->pacingmode ?? self::AUTO,
            'duration' => (int) ($record->duration ?? 0),
            'autopace' => (int) ($record->autopace ?? 0),
            'paused' => (int) ($record->paused ?? 0),
        ];
        if ($settings->pacingmode === self::AUTO) {
            $settings->pacingmode = get_config('local_remindstudio', 'defaultpacing') ?: self::AUTO;
        }
        if ($settings->pacingmode === self::AUTO) {
            $enddate = $DB->get_field('course', 'enddate', ['id' => $courseid]);
            $settings->pacingmode = $enddate ? self::FIXED : self::OPEN;
        }
        return $settings;
    }

    /**
     * Save some of the settings of a course.
     *
     * @param int $courseid
     * @param array $data Any of pacingmode, duration, autopace, paused.
     */
    public static function save(int $courseid, array $data): void {
        global $DB;
        $data = array_intersect_key($data, array_flip(['pacingmode', 'duration', 'autopace', 'paused']));
        if (
            isset($data['pacingmode'])
                && !in_array($data['pacingmode'], [self::AUTO, self::OPEN, self::SELFPACED, self::FIXED], true)
        ) {
            throw new \invalid_parameter_exception('Unknown pacing mode: ' . $data['pacingmode']);
        }
        $data['timemodified'] = time();
        if ($id = $DB->get_field('local_remindstudio_course', 'id', ['courseid' => $courseid])) {
            $DB->update_record('local_remindstudio_course', ['id' => $id] + $data);
        } else {
            $DB->insert_record('local_remindstudio_course', ['courseid' => $courseid] + $data);
        }
    }

    /**
     * Give a learner their own expected finish date (an extension), or remove it.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $expectedend 0 removes it.
     * @param string $note
     */
    public static function set_extension(int $courseid, int $userid, int $expectedend, string $note = ''): void {
        global $DB;
        $where = ['courseid' => $courseid, 'userid' => $userid];
        if (!$expectedend) {
            $DB->delete_records('local_remindstudio_override', $where);
        } else if ($id = $DB->get_field('local_remindstudio_override', 'id', $where)) {
            $DB->update_record(
                'local_remindstudio_override',
                ['id' => $id, 'expectedend' => $expectedend, 'note' => $note, 'timemodified' => time()]
            );
        } else {
            $DB->insert_record(
                'local_remindstudio_override',
                $where + ['expectedend' => $expectedend, 'note' => $note, 'timemodified' => time()]
            );
        }
    }
}
