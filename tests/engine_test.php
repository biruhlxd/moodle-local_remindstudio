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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_remindstudio\engine\scanner;
use local_remindstudio\engine\sender;
use local_remindstudio\task\send_batch;

/**
 * Tests for checking rules and sending reminders.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scanner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(sender::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rule::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(placeholders::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(privacy\provider::class)]
final class engine_test extends \advanced_testcase {
    /** @var int 2026-01-01 12:00 UTC. */
    private const NOW = 1767268800;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset();
        // No limits and no send window unless a test sets them.
        set_config('maxpercourse', 0, 'local_remindstudio');
        set_config('maxpersite', 0, 'local_remindstudio');
        set_config('sendstart', 0, 'local_remindstudio');
        set_config('sendend', 0, 'local_remindstudio');
    }

    /**
     * A course with one learner.
     *
     * @param array $user User fields.
     * @return array [course, user]
     */
    private function course_with_learner(array $user = []): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user($user + ['timezone' => 'UTC']);
        $gen->enrol_user($user->id, $course->id, 'student');
        return [$course, $user];
    }

    /**
     * A rule in two steps three days apart, by default for learners inactive for 7 days.
     *
     * @param int $courseid
     * @param array $data Rule fields to change.
     * @return rule
     */
    private function rule(int $courseid, array $data = []): rule {
        return rule::create($data + [
            'courseid' => $courseid,
            'name' => 'Inactive',
            'conditions' => ['op' => 'all', 'items' => [['type' => 'inactive', 'days' => 7]]],
        ], [
            ['subject' => 'Hi {firstname}', 'body' => '<p>Come back to {courselink}</p>'],
            ['delay' => 3 * DAYSECS, 'subject' => 'Second reminder', 'body' => '<p>Still waiting</p>'],
        ]);
    }

    /**
     * Record that the learner opened the course.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $time
     */
    private function access(int $courseid, int $userid, int $time): void {
        global $DB;
        $DB->delete_records('user_lastaccess', ['courseid' => $courseid, 'userid' => $userid]);
        $DB->insert_record('user_lastaccess', ['courseid' => $courseid, 'userid' => $userid, 'timeaccess' => $time]);
    }

    public function test_each_step_is_sent_once(): void {
        global $DB;
        [$course, $user] = $this->course_with_learner();
        $rule = $this->rule($course->id);

        $logids = (new scanner(self::NOW))->scan_course($course->id);
        $this->assertCount(1, $logids);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(send_batch::class));
        $this->assertSame([], (new scanner(self::NOW + HOURSECS))->scan_course($course->id));

        $sink = $this->redirectMessages();
        (new sender())->send($logids);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Hi ' . $user->firstname, $messages[0]->subject);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $messages[0]->fullmessagehtml);
        $this->assertSame('sent', $DB->get_field('local_remindstudio_log', 'status', ['id' => $logids[0]]));

        $this->assertCount(1, (new scanner(self::NOW + 3 * DAYSECS))->scan_course($course->id));
        $this->assertSame('done', $DB->get_field('local_remindstudio_track', 'status', ['ruleid' => $rule->id]));
        $this->assertSame([], (new scanner(self::NOW + 10 * DAYSECS))->scan_course($course->id));
    }

    public function test_learner_who_comes_back_leaves_the_rule(): void {
        global $DB;
        [$course, $user] = $this->course_with_learner();
        $rule = $this->rule($course->id, [
            'conditions' => ['op' => 'all', 'items' => [['type' => 'coursecompletion', 'complete' => 0]]],
            'exits' => ['accessed' => 1],
        ]);
        $this->assertCount(1, (new scanner(self::NOW))->scan_course($course->id));

        $this->access($course->id, $user->id, self::NOW + HOURSECS);
        $this->assertSame([], (new scanner(self::NOW + 3 * DAYSECS))->scan_course($course->id));
        $this->assertSame('exited', $DB->get_field('local_remindstudio_track', 'status', ['ruleid' => $rule->id]));
    }

    public function test_learner_who_matches_again_starts_again(): void {
        global $DB;
        [$course, $user] = $this->course_with_learner();
        $rule = $this->rule($course->id);
        $this->assertCount(1, (new scanner(self::NOW))->scan_course($course->id));

        $this->access($course->id, $user->id, self::NOW + DAYSECS);
        $this->assertSame([], (new scanner(self::NOW + 2 * DAYSECS))->scan_course($course->id));
        $this->assertSame('cleared', $DB->get_field('local_remindstudio_track', 'status', ['ruleid' => $rule->id]));

        // Inactive for 7 days again: a new round starts at step 1.
        $logids = (new scanner(self::NOW + 9 * DAYSECS))->scan_course($course->id);
        $this->assertCount(1, $logids);
        $log = $DB->get_record('local_remindstudio_log', ['id' => $logids[0]]);
        $this->assertEquals(2, $DB->get_field('local_remindstudio_track', 'occurrence', ['id' => $log->trackid]));
        $this->assertEquals($rule->get_steps()[0]->id, $log->stepid);
    }

    public function test_daily_limit_lets_the_highest_priority_go_first(): void {
        global $DB;
        set_config('maxpercourse', 1, 'local_remindstudio');
        [$course] = $this->course_with_learner();
        $low = $this->rule($course->id, ['name' => 'Low', 'priority' => 1]);
        $high = $this->rule($course->id, ['name' => 'High', 'priority' => 5]);

        $logids = (new scanner(self::NOW))->scan_course($course->id);
        $this->assertCount(1, $logids);
        $this->assertEquals($high->id, $DB->get_field('local_remindstudio_log', 'ruleid', ['id' => $logids[0]]));
        $this->assertSame('capcourse', $DB->get_field('local_remindstudio_track', 'waitreason', ['ruleid' => $low->id]));

        // A day later the other rule's turn comes.
        $logids = (new scanner(self::NOW + DAYSECS + 1))->scan_course($course->id);
        $this->assertEquals($low->id, $DB->get_field('local_remindstudio_log', 'ruleid', ['id' => $logids[0]]));
    }

    public function test_reminders_wait_for_the_send_window(): void {
        global $DB;
        set_config('sendstart', 8, 'local_remindstudio');
        set_config('sendend', 20, 'local_remindstudio');
        [$course] = $this->course_with_learner(['timezone' => 'Europe/London']);
        $rule = $this->rule($course->id);

        // 03:00 in London.
        $this->assertSame([], (new scanner(self::NOW - 9 * HOURSECS))->scan_course($course->id));
        $this->assertSame('quiethours', $DB->get_field('local_remindstudio_track', 'waitreason', ['ruleid' => $rule->id]));
        // 12:00.
        $this->assertCount(1, (new scanner(self::NOW))->scan_course($course->id));
    }

    public function test_dry_run_logs_without_sending(): void {
        global $DB;
        [$course] = $this->course_with_learner();
        $rule = $this->rule($course->id, ['dryrununtil' => self::NOW + DAYSECS]);

        $this->assertSame([], (new scanner(self::NOW))->scan_course($course->id));
        $this->assertSame('dryrun', $DB->get_field('local_remindstudio_log', 'status', ['ruleid' => $rule->id]));

        // Live: everyone starts afresh.
        $this->assertCount(1, (new scanner(self::NOW + 2 * DAYSECS))->scan_course($course->id));
    }

    public function test_suspended_learner_gets_nothing(): void {
        [$course] = $this->course_with_learner(['suspended' => 1]);
        $this->rule($course->id);
        $this->assertSame([], (new scanner(self::NOW))->scan_course($course->id));
    }

    public function test_teachers_are_not_reminded(): void {
        $gen = $this->getDataGenerator();
        [$course] = $this->course_with_learner();
        $gen->enrol_user($gen->create_user()->id, $course->id, 'editingteacher');
        $this->rule($course->id);
        $this->assertCount(1, (new scanner(self::NOW))->scan_course($course->id));
    }

    public function test_copy_goes_to_teachers(): void {
        $gen = $this->getDataGenerator();
        [$course] = $this->course_with_learner();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        rule::create(
            ['courseid' => $course->id, 'name' => 'Copy'],
            [['subject' => 'Hello', 'body' => 'Text', 'actions' => ['messagelearner', 'copyteachers']]]
        );

        $sink = $this->redirectMessages();
        (new sender())->send((new scanner(self::NOW))->scan_course($course->id));
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $this->assertEquals($teacher->id, $messages[1]->useridto);
        $this->assertStringEndsWith(': Hello', $messages[1]->subject);
    }

    public function test_enrolment_starts_an_event_rule(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $rule = rule::create(
            ['courseid' => $course->id, 'name' => 'Welcome', 'triggertype' => rule::TRIGGER_ENROLLED],
            [['delay' => HOURSECS, 'subject' => 'Welcome to {coursename}', 'body' => 'Hello']]
        );
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $this->assertTrue($DB->record_exists('local_remindstudio_track', ['ruleid' => $rule->id, 'userid' => $user->id]));

        $this->assertSame([], (new scanner(time()))->scan_course($course->id));
        $this->assertCount(1, (new scanner(time() + 2 * HOURSECS))->scan_course($course->id));
    }

    public function test_unknown_placeholder_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\moodle_exception::class);
        rule::create(['courseid' => $course->id, 'name' => 'Bad'], [['subject' => 'Hi {nickname}', 'body' => '']]);
    }

    public function test_unknown_condition_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\moodle_exception::class);
        rule::create(['courseid' => $course->id, 'name' => 'Bad',
            'conditions' => ['op' => 'all', 'items' => [['type' => 'moonphase']]]], [['subject' => 'Hi', 'body' => '']]);
    }

    public function test_privacy_export_and_delete(): void {
        global $DB;
        [$course, $user] = $this->course_with_learner();
        $this->rule($course->id);
        $this->redirectMessages();
        (new sender())->send((new scanner(self::NOW))->scan_course($course->id));
        $context = \context_course::instance($course->id);

        $contexts = privacy\provider::get_contexts_for_userid($user->id)->get_contextids();
        $this->assertEquals([$context->id], array_values(array_unique($contexts)));

        $approved = new approved_contextlist($user, 'local_remindstudio', [$context->id]);
        privacy\provider::export_user_data($approved);
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_remindstudio')]);
        $this->assertSame('Hi {firstname}', $data->reminders[0]->subject);

        privacy\provider::delete_data_for_user($approved);
        foreach (['local_remindstudio_pace', 'local_remindstudio_track', 'local_remindstudio_log'] as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user->id]), $table);
        }
    }
}
