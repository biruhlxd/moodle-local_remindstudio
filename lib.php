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

/**
 * Callbacks for local_remindstudio.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add Reminders to the course's More menu.
 *
 * @param navigation_node $navigation The course administration node.
 * @param stdClass $course
 * @param context $context The course context.
 */
function local_remindstudio_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if ($course->id == SITEID || !has_capability('local/remindstudio:viewcourse', $context)) {
        return;
    }
    $navigation->add(
        get_string('reminders', 'local_remindstudio'),
        new moodle_url('/local/remindstudio/index.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_remindstudio',
        new pix_icon('i/notifications', '')
    );
}
