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

namespace local_remindstudio\engine;

use local_remindstudio\action\reminder;
use local_remindstudio\event\reminder_sent;
use local_remindstudio\registry;
use stdClass;

/**
 * Sends queued reminders: writes each step's message for the learner and runs the step's actions.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sender {
    /**
     * Send the queued reminders among these log rows.
     *
     * @param int[] $logids
     */
    public function send(array $logids): void {
        global $DB;
        if (!$logids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($logids, SQL_PARAMS_NAMED);
        $logs = $DB->get_records_select(
            'local_remindstudio_log',
            "id $insql AND status = :queued",
            $params + ['queued' => 'queued'],
            'id'
        );
        foreach ($logs as $log) {
            $this->send_one($log);
        }
    }

    /**
     * Send one reminder and record the result.
     *
     * @param stdClass $log
     */
    private function send_one(stdClass $log): void {
        global $DB;
        $user = \core_user::get_user($log->userid);
        $step = $DB->get_record('local_remindstudio_step', ['id' => $log->stepid]);
        $course = $DB->get_record('course', ['id' => $log->courseid]);
        if (!$user || $user->deleted || $user->suspended) {
            $this->finish($log, 'failed', get_string('reason_userinactive', 'local_remindstudio'));
            return;
        }
        if (!$step || !$course) {
            $this->finish($log, 'failed', get_string('reason_gone', 'local_remindstudio'));
            return;
        }
        try {
            $pace = $DB->get_record('local_remindstudio_pace', ['courseid' => $course->id, 'userid' => $user->id]) ?: null;
            $essential = (bool) $DB->get_field('local_remindstudio_rule', 'essential', ['id' => $log->ruleid]);
            $reminder = reminder::create($step, $user, $course, $pace, time(), !$essential);
            foreach (json_decode($step->actions, true) ?: [] as $type) {
                registry::action($type)->execute($reminder);
            }
        } catch (\Throwable $e) {
            $this->finish($log, 'failed', $e->getMessage());
            return;
        }
        $this->finish($log, 'sent', null, \core_text::substr($reminder->subject, 0, 255));
        reminder_sent::create([
            'objectid' => $log->id,
            'context' => \context_course::instance($course->id),
            'relateduserid' => $user->id,
            'other' => ['ruleid' => (int) $log->ruleid, 'stepid' => (int) $log->stepid],
        ])->trigger();
    }

    /**
     * Record the result.
     *
     * @param stdClass $log
     * @param string $status sent or failed.
     * @param string|null $reason
     * @param string|null $subject The subject as sent.
     */
    private function finish(stdClass $log, string $status, ?string $reason = null, ?string $subject = null): void {
        global $DB;
        $DB->update_record(
            'local_remindstudio_log',
            ['id' => $log->id, 'status' => $status, 'reason' => $reason, 'subject' => $subject, 'timesent' => time()]
        );
    }
}
