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

/**
 * A learner's own finish date (an extension).
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extension_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('date_selector', 'expectedend', get_string('extension', 'local_remindstudio'), ['optional' => true]);
        $mform->addHelpButton('expectedend', 'extension', 'local_remindstudio');
        $mform->addElement('text', 'note', get_string('extensionnote', 'local_remindstudio'), ['size' => 50]);
        $mform->setType('note', PARAM_TEXT);
        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
