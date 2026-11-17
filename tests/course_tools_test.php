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
use local_remindstudio\engine\sender;
use local_remindstudio\form\rule_form;

/**
 * Tests for what the course pages use: recipes, editing, summaries, the forecast, opting out, backup and restore.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(recipes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rule::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(summary::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(forecast::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(optout::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rule_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_local_remindstudio_plugin::class)]
final class course_tools_test extends \advanced_testcase {
    /** @var int 2026-01-01 12:00 UTC. */
    private const NOW = 1767268800;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset();
        set_config('maxpercourse', 0, 'local_remindstudio');
        set_config('maxpersite', 0, 'local_remindstudio');
        set_config('sendstart', 0, 'local_remindstudio');
        set_config('sendend', 0, 'local_remindstudio');
    }

    /**
     * A course with one learner.
     *
     * @return array [course, user]
     */
    private function course_with_learner(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user(['timezone' => 'UTC']);
        $gen->enrol_user($user->id, $course->id, 'student');
        return [$course, $user];
    }

    public function test_every_recipe_makes_a_valid_rule(): void {
        $course = $this->getDataGenerator()->create_course();
        foreach (array_keys(recipes::names()) as $key) {
            [$data, $steps] = recipes::get($key, $course->id);
            $rule = rule::create($data, $steps);
            $this->assertCount(count($steps), $rule->get_steps(), $key);
            $this->assertNotEmpty(summary::rule($rule->triggertype, $rule->conditions), $key);
        }
    }

    public function test_summary_in_plain_words(): void {
        $this->assertSame(
            'Learners who have not opened the course for 7 days and have not completed the course.',
            summary::rule(rule::TRIGGER_SCAN, ['op' => 'all', 'items' => [
                ['type' => 'inactive', 'days' => 7],
                ['type' => 'coursecompletion', 'complete' => 0],
            ]])
        );
        $this->assertSame('When a learner is enrolled. All learners.', summary::rule(rule::TRIGGER_ENROLLED, []));
        $this->assertSame('Learners who have progress less than 50%', summary::conditions(['op' => 'any', 'items' => [
            ['type' => 'progress', 'op' => '<', 'value' => 50],
        ]]));
    }

    public function test_update_keeps_learners_in_place(): void {
        global $DB;
        [$course] = $this->course_with_learner();
        [$data, $steps] = recipes::get('inactive', $course->id);
        $rule = rule::create($data, $steps);
        (new scanner(self::NOW))->scan_course($course->id);

        $steps[1]['subject'] = 'Changed';
        array_pop($steps);
        $rule = rule::update($rule->id, ['name' => 'Renamed'] + $data, $steps);
        $this->assertSame('Renamed', $rule->name);
        $this->assertSame('Changed', $rule->get_steps()[1]->subject);
        $this->assertCount(2, $rule->get_steps());
        $this->assertEquals(2, $DB->get_field('local_remindstudio_track', 'nextstep', ['ruleid' => $rule->id]));
    }

    public function test_form_data_round_trip(): void {
        $course = $this->getDataGenerator()->create_course();
        [$data, $steps] = recipes::get('neverstarted', $course->id);
        $custom = ['activities' => [], 'roles' => [], 'conditioncount' => 2, 'stepcount' => 3, 'dryrununtil' => 0];
        $form = new rule_form(new \moodle_url('/local/remindstudio/edit.php'), $custom);
        $formdata = (object) (rule_form::to_formdata($data, $steps) + ['enabled' => 1]);
        [$newdata, $newsteps] = $form->to_rule($formdata, $course->id);
        $this->assertSame($data['conditions'], $newdata['conditions']);
        $this->assertSame($data['exits'], $newdata['exits']);
        $this->assertSame(array_column($steps, 'subject'), array_column($newsteps, 'subject'));
        $this->assertSame(array_column($steps, 'actions'), array_column($newsteps, 'actions'));
        $this->assertSame(array_column($steps, 'delay'), array_column($newsteps, 'delay'));
    }

    public function test_forecast_shows_steps_and_new_learners(): void {
        [$course, $user] = $this->course_with_learner();
        [$data, $steps] = recipes::get('inactive', $course->id);
        rule::create($data, $steps);
        $this->setAdminUser();

        // Never opened: matches today, so all three steps fall within 14 days (days 0, 7 and 14).
        $rows = (new forecast(self::NOW))->course($course->id);
        $this->assertSame([1, 2, 3], array_column($rows, 'step'));
        $this->assertTrue($rows[0]->starts);
        $this->assertEquals($user->id, $rows[0]->userid);

        // Once the first step is sent, the forecast continues from step 2.
        (new scanner(self::NOW))->scan_course($course->id);
        $rows = (new forecast(self::NOW))->course($course->id);
        $this->assertSame([2, 3], array_column($rows, 'step'));
        $this->assertEquals(self::NOW + 7 * DAYSECS, $rows[0]->time);
    }

    public function test_opted_out_learner_gets_only_essential_reminders(): void {
        [$course, $user] = $this->course_with_learner();
        $normal = rule::create(['courseid' => $course->id, 'name' => 'Normal'], [['subject' => 'Hi', 'body' => '']]);
        $essential = rule::create(
            ['courseid' => $course->id, 'name' => 'Essential', 'essential' => 1],
            [['subject' => 'Must', 'body' => '']]
        );

        $url = optout::url($user->id, $course->id);
        $this->assertTrue(optout::check($user->id, $course->id, $url->get_param('t')));
        $this->assertFalse(optout::check($user->id + 1, $course->id, $url->get_param('t')));

        optout::set($user->id, $course->id, 0);
        $scanner = new scanner(self::NOW);
        $this->assertSame([], $scanner->matching_users($normal));
        $this->assertCount(1, $scanner->matching_users($essential));

        // A snooze ends by itself.
        optout::set($user->id, $course->id, self::NOW + DAYSECS);
        $this->assertSame([], $scanner->matching_users($normal));
        $this->assertCount(1, (new scanner(self::NOW + 2 * DAYSECS))->matching_users($normal));
    }

    public function test_reminder_has_the_optout_link_unless_essential(): void {
        [$course] = $this->course_with_learner();
        rule::create(['courseid' => $course->id, 'name' => 'Normal'], [['subject' => 'Normal', 'body' => 'Text']]);
        rule::create(
            ['courseid' => $course->id, 'name' => 'Essential', 'essential' => 1],
            [['subject' => 'Essential', 'body' => 'Text']]
        );
        $sink = $this->redirectMessages();
        (new sender())->send((new scanner(self::NOW))->scan_course($course->id));
        $messages = [];
        foreach ($sink->get_messages() as $message) {
            $messages[$message->subject] = $message->fullmessagehtml;
        }
        $this->assertStringContainsString('/local/remindstudio/optout.php', $messages['Normal']);
        $this->assertStringNotContainsString('/local/remindstudio/optout.php', $messages['Essential']);
    }

    public function test_backup_and_restore_copy_rules_and_point_at_new_activities(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $page = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        \local_remindstudio\pace\course_settings::save($course->id, ['pacingmode' => 'selfpaced', 'duration' => 30 * DAYSECS]);
        rule::create([
            'courseid' => $course->id,
            'name' => 'Page',
            'conditions' => ['op' => 'all', 'items' => [['type' => 'activitycompletion', 'cmid' => $page->cmid, 'complete' => 0]]],
            'exits' => ['cmid' => $page->cmid],
        ], [['subject' => 'Read the page', 'body' => ''], ['delay' => DAYSECS, 'subject' => 'Again', 'body' => '']]);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $newcourseid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $rules = rule::get_for_course($newcourseid);
        $this->assertCount(1, $rules);
        $newcmid = (int) $DB->get_field('course_modules', 'id', ['course' => $newcourseid]);
        $this->assertNotEquals($page->cmid, $newcmid);
        $this->assertEquals($newcmid, $rules[0]->conditions['items'][0]['cmid']);
        $this->assertEquals($newcmid, $rules[0]->exits['cmid']);
        $this->assertTrue($rules[0]->enabled);
        $this->assertSame(['Read the page', 'Again'], array_column($rules[0]->get_steps(), 'subject'));
        $this->assertSame('selfpaced', $DB->get_field('local_remindstudio_course', 'pacingmode', ['courseid' => $newcourseid]));
    }
}
