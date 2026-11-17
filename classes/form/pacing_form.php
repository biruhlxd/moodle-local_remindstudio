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

namespace local_remindstudio\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_remindstudio\pace\course_settings;

/**
 * How a course is paced.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pacing_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $modes = [];
        foreach ([course_settings::AUTO, course_settings::SELFPACED, course_settings::FIXED, course_settings::OPEN] as $mode) {
            $modes[$mode] = get_string('pacing_' . $mode, 'local_remindstudio');
        }
        $mform->addElement('select', 'pacingmode', get_string('pacingmode', 'local_remindstudio'), $modes);
        $mform->addHelpButton('pacingmode', 'pacingmode', 'local_remindstudio');
        $mform->addElement('duration', 'duration', get_string('duration', 'local_remindstudio'), ['defaultunit' => DAYSECS]);
        $mform->addHelpButton('duration', 'duration', 'local_remindstudio');
        $mform->hideIf('duration', 'pacingmode', 'neq', course_settings::SELFPACED);
        $mform->addElement('advcheckbox', 'autopace', get_string('autopace', 'local_remindstudio'));
        $mform->addHelpButton('autopace', 'autopace', 'local_remindstudio');
        $mform->hideIf('autopace', 'pacingmode', 'eq', course_settings::OPEN);
        $mform->addElement('advcheckbox', 'paused', get_string('paused', 'local_remindstudio'));
        $mform->addHelpButton('paused', 'paused', 'local_remindstudio');
        $this->add_action_buttons(false);
    }
}
