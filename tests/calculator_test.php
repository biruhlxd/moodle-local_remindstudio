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

use local_remindstudio\pace\calculator;
use local_remindstudio\pace\course_settings;

/**
 * Tests for each learner's dates and pace.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(calculator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(course_settings::class)]
final class calculator_test extends \advanced_testcase {
    /** @var int 2026-01-01 12:00 UTC. */
    private const NOW = 1767268800;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A course with tracked activities and one learner who completed some of them.
     *
     * @param array $course Course fields.
     * @param int $activities
     * @param int $completed
     * @param int $enrolled When the learner was enrolled.
     * @return array [course, user]
     */
    private function course_with_learner(array $course, int $activities, int $completed, int $enrolled): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course($course + ['enablecompletion' => 1]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student', 'manual', $enrolled);
        for ($i = 0; $i < $activities; $i++) {
            $page = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
            if ($i < $completed) {
                $DB->insert_record('course_modules_completion', ['coursemoduleid' => $page->cmid, 'userid' => $user->id,
                    'completionstate' => COMPLETION_COMPLETE, 'timemodified' => self::NOW]);
            }
        }
        return [$course, $user];
    }

    /**
     * The learner's stored values.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass
     */
    private function pace(int $courseid, int $userid): \stdClass {
        global $DB;
        (new calculator(self::NOW))->update_course($courseid);
        return $DB->get_record('local_remindstudio_pace', ['courseid' => $courseid, 'userid' => $userid], '*', MUST_EXIST);
    }

    public function test_self_paced_learner_behind(): void {
        $enrolled = self::NOW - 20 * DAYSECS;
        [$course, $user] = $this->course_with_learner([], 5, 2, $enrolled);
        course_settings::save($course->id, ['pacingmode' => 'selfpaced', 'duration' => 30 * DAYSECS]);

        $pace = $this->pace($course->id, $user->id);
        $this->assertEquals($enrolled, $pace->timestart);
        $this->assertEquals($enrolled + 30 * DAYSECS, $pace->expectedend);
        $this->assertEquals(40, $pace->progresspct);
        $this->assertEquals(67, $pace->expectedpct);
        $this->assertEquals(27, $pace->pacegap);
    }

    public function test_fixed_dates_and_extension(): void {
        [$course, $user] = $this->course_with_learner(
            ['startdate' => self::NOW - 10 * DAYSECS, 'enddate' => self::NOW + 10 * DAYSECS],
            2,
            1,
            self::NOW - DAYSECS
        );

        // Automatic pacing uses fixed dates because the course has an end date.
        $pace = $this->pace($course->id, $user->id);
        $this->assertEquals(self::NOW + 10 * DAYSECS, $pace->expectedend);
        $this->assertEquals(50, $pace->expectedpct);
        $this->assertEquals(0, $pace->pacegap);

        course_settings::set_extension($course->id, $user->id, self::NOW + 30 * DAYSECS);
        $pace = $this->pace($course->id, $user->id);
        $this->assertEquals(self::NOW + 30 * DAYSECS, $pace->expectedend);
        $this->assertEquals(25, $pace->expectedpct);
        $this->assertEquals(-25, $pace->pacegap);
    }

    public function test_auto_pace_spreads_activities(): void {
        [$course, $user] = $this->course_with_learner([], 3, 0, self::NOW - 20 * DAYSECS);
        course_settings::save($course->id, ['pacingmode' => 'selfpaced', 'duration' => 30 * DAYSECS, 'autopace' => 1]);

        // Targets at days 10, 20 and 30: two have passed.
        $this->assertEquals(67, $this->pace($course->id, $user->id)->expectedpct);
    }

    public function test_open_course_has_no_deadline(): void {
        [$course, $user] = $this->course_with_learner(['enddate' => 0], 2, 1, self::NOW - DAYSECS);
        $pace = $this->pace($course->id, $user->id);
        $this->assertEquals(0, $pace->expectedend);
        $this->assertEquals(50, $pace->progresspct);
        $this->assertNull($pace->expectedpct);
        $this->assertNull($pace->pacegap);
    }

    public function test_unenrolled_learner_is_removed(): void {
        global $DB;
        [$course, $user] = $this->course_with_learner([], 1, 0, self::NOW - DAYSECS);
        $this->pace($course->id, $user->id);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        enrol_get_plugin('manual')->unenrol_user($instance, $user->id);

        (new calculator(self::NOW))->update_course($course->id);
        $this->assertFalse($DB->record_exists('local_remindstudio_pace', ['courseid' => $course->id]));
    }
}
