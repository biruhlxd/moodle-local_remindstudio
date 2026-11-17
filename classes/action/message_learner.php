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

namespace local_remindstudio\action;

/**
 * Action: send the reminder to the learner as a Moodle notification (web, email or mobile, as they choose).
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_learner extends base {
    #[\Override]
    public function get_type(): string {
        return 'messagelearner';
    }

    #[\Override]
    public function get_name(): string {
        return get_string('action_messagelearner', 'local_remindstudio');
    }

    #[\Override]
    public function execute(reminder $reminder): void {
        self::send('reminder', $reminder->user, $reminder, $reminder->subject, $reminder->html, $reminder->text);
    }

    /**
     * Send a notification about the course.
     *
     * @param string $provider Message provider of this plugin.
     * @param \stdClass $to
     * @param reminder $reminder
     * @param string $subject
     * @param string $html
     * @param string $text
     */
    public static function send(
        string $provider,
        \stdClass $to,
        reminder $reminder,
        string $subject,
        string $html,
        string $text
    ): void {
        $message = new \core\message\message();
        $message->component = 'local_remindstudio';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $to;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $reminder->course->id]))->out(false);
        $message->contexturlname = format_string(
            $reminder->course->fullname,
            true,
            ['context' => \context_course::instance($reminder->course->id)]
        );
        $message->courseid = $reminder->course->id;
        if (!message_send($message)) {
            throw new \moodle_exception('error_sendfailed', 'local_remindstudio');
        }
    }
}
