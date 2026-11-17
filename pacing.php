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
 * How a course is paced, and pausing its reminders.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_remindstudio\form\pacing_form;
use local_remindstudio\output\page;
use local_remindstudio\pace\calculator;
use local_remindstudio\pace\course_settings;

$courseid = required_param('id', PARAM_INT);
require_login($courseid);
$course = page::setup('pacing', $courseid, 'managecourse');

$form = new pacing_form($PAGE->url);
if ($data = $form->get_data()) {
    course_settings::save($courseid, (array) $data);
    (new calculator(time()))->update_course($courseid);
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$form->set_data($DB->get_record('local_remindstudio_course', ['courseid' => $courseid]) ?: ['pacingmode' => 'auto']);

echo page::header($course, 'pacing');
$resolved = course_settings::get($courseid)->pacingmode;
echo html_writer::tag('p', get_string('pacingnow', 'local_remindstudio', get_string('pacing_' . $resolved, 'local_remindstudio')));
$form->display();
echo $OUTPUT->footer();
