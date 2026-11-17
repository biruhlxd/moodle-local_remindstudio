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
 * Something a step does for a learner, such as sending them a message.
 *
 * Other plugins add actions through the \local_remindstudio\hook\collect_types hook.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * The name the action is stored under, unique across plugins.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * The name shown to teachers.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Do it. Throw an exception when it fails; the message is kept in the log.
     *
     * @param reminder $reminder The learner, course and the message written for them.
     */
    abstract public function execute(reminder $reminder): void;
}
