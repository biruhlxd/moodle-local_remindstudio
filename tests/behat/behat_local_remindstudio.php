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
 * Behat steps for local_remindstudio.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps for local_remindstudio.
 */
class behat_local_remindstudio extends behat_base {
    /**
     * Opens the signed opt-out link that a learner gets in reminders for a course.
     *
     * @Given /^I open the reminder opt-out link of "(?P<username>[^"]*)" for "(?P<shortname>[^"]*)"$/
     * @param string $username
     * @param string $shortname
     */
    public function i_open_the_reminder_optout_link(string $username, string $shortname): void {
        $userid = $this->get_user_id_by_identifier($username);
        $courseid = $this->get_course_id($shortname);
        $this->execute('behat_general::i_visit', [\local_remindstudio\optout::url($userid, $courseid)]);
    }
}
