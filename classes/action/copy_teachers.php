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
 * Action: send a copy of the reminder to the course's teachers (those who can receive copies).
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copy_teachers extends base {
    #[\Override]
    public function get_type(): string {
        return 'copyteachers';
    }

    #[\Override]
    public function get_name(): string {
        return get_string('action_copyteachers', 'local_remindstudio');
    }

    #[\Override]
    public function execute(reminder $reminder): void {
        $context = \context_course::instance($reminder->course->id);
        $teachers = get_enrolled_users($context, 'local/remindstudio:receivecopy', 0, 'u.*', null, 0, 0, true);
        unset($teachers[$reminder->user->id]);
        foreach ($teachers as $teacher) {
            $previous = force_current_language($teacher->lang);
            try {
                $learner = fullname($reminder->user);
                $a = ['learner' => $learner, 'subject' => $reminder->subject];
                $subject = get_string('copysubject', 'local_remindstudio', $a);
                $intro = get_string('copyintro', 'local_remindstudio', $learner);
            } finally {
                force_current_language($previous);
            }
            message_learner::send(
                'remindercopy',
                $teacher,
                $reminder,
                $subject,
                \html_writer::tag('p', s($intro)) . $reminder->html,
                $intro . "\n\n" . $reminder->text
            );
        }
    }
}
