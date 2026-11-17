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

namespace local_remindstudio\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: each learner's pace, extension, place in the rules and the reminders sent to them, per course.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Tables with a courseid and a userid. */
    private const TABLES = [
        'local_remindstudio_pace',
        'local_remindstudio_override',
        'local_remindstudio_track',
        'local_remindstudio_log',
    ];

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_remindstudio_pace', [
            'userid' => 'privacy:metadata:pace:userid',
            'timeenrolled' => 'privacy:metadata:pace:timeenrolled',
            'expectedend' => 'privacy:metadata:pace:expectedend',
            'progresspct' => 'privacy:metadata:pace:progresspct',
            'expectedpct' => 'privacy:metadata:pace:expectedpct',
        ], 'privacy:metadata:pace');
        $collection->add_database_table('local_remindstudio_override', [
            'userid' => 'privacy:metadata:override:userid',
            'expectedend' => 'privacy:metadata:override:expectedend',
            'note' => 'privacy:metadata:override:note',
        ], 'privacy:metadata:override');
        $collection->add_database_table('local_remindstudio_track', [
            'userid' => 'privacy:metadata:track:userid',
            'nextstep' => 'privacy:metadata:track:nextstep',
            'status' => 'privacy:metadata:track:status',
        ], 'privacy:metadata:track');
        $collection->add_database_table('local_remindstudio_log', [
            'userid' => 'privacy:metadata:log:userid',
            'status' => 'privacy:metadata:log:status',
            'timesent' => 'privacy:metadata:log:timesent',
        ], 'privacy:metadata:log');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::TABLES as $table) {
            $contextlist->add_from_sql("
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {{$table}} t ON t.courseid = ctx.instanceid AND ctx.contextlevel = :contextlevel
                 WHERE t.userid = :userid", ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid]);
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {{$table}} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $where = ['courseid' => $context->instanceid, 'userid' => $userid];
            $data = (object) [];
            if ($pace = $DB->get_record('local_remindstudio_pace', $where)) {
                $data->pace = (object) [
                    'enrolled' => transform::datetime($pace->timeenrolled),
                    'expectedfinish' => $pace->expectedend ? transform::datetime($pace->expectedend) : null,
                    'progress' => $pace->progresspct,
                    'expectedprogress' => $pace->expectedpct,
                ];
            }
            if ($override = $DB->get_record('local_remindstudio_override', $where)) {
                $data->extension = (object) [
                    'expectedfinish' => transform::datetime($override->expectedend),
                    'note' => $override->note,
                ];
            }
            $reminders = $DB->get_records_sql('
                SELECT l.id, r.name AS rule, s.subject, l.status, l.timecreated, l.timesent
                  FROM {local_remindstudio_log} l
                  JOIN {local_remindstudio_rule} r ON r.id = l.ruleid
                  JOIN {local_remindstudio_step} s ON s.id = l.stepid
                 WHERE l.courseid = :courseid AND l.userid = :userid
              ORDER BY l.id', $where);
            foreach ($reminders as $reminder) {
                $data->reminders[] = (object) [
                    'rule' => $reminder->rule,
                    'subject' => $reminder->subject,
                    'status' => $reminder->status,
                    'time' => transform::datetime($reminder->timesent ?: $reminder->timecreated),
                ];
            }
            $data->rules = array_values($DB->get_records_sql('
                SELECT t.id, r.name AS rule, t.nextstep, t.status
                  FROM {local_remindstudio_track} t
                  JOIN {local_remindstudio_rule} r ON r.id = t.ruleid
                 WHERE t.courseid = :courseid AND t.userid = :userid
              ORDER BY t.id', $where));
            if (isset($data->pace) || isset($data->extension) || isset($data->reminders) || $data->rules) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_remindstudio')], $data);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_course) {
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['courseid' => $context->instanceid]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                foreach (self::TABLES as $table) {
                    $DB->delete_records($table, ['courseid' => $context->instanceid, 'userid' => $userid]);
                }
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        foreach (self::TABLES as $table) {
            $DB->delete_records_select(
                $table,
                "courseid = :courseid AND userid $insql",
                $params + ['courseid' => $context->instanceid]
            );
        }
    }
}
