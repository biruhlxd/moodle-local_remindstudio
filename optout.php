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
 * Where a learner snoozes or stops a course's reminders, from the link in a reminder. No login needed: the link is
 * signed for the learner and course, and changes are made only when the page's form is posted.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The signed link replaces the login.
require('../../config.php');

use local_remindstudio\optout;

$userid = required_param('u', PARAM_INT);
$courseid = required_param('c', PARAM_INT);
$token = required_param('t', PARAM_ALPHANUM);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/remindstudio/optout.php', ['u' => $userid, 'c' => $courseid, 't' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('optouttitle', 'local_remindstudio'));

if (!optout::check($userid, $courseid, $token)) {
    throw new moodle_exception('error_badlink', 'local_remindstudio');
}
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursename = format_string($course->fullname, true, ['context' => context_course::instance($courseid)]);

$choice = optional_param('choice', '', PARAM_ALPHA);
if ($choice !== '' && data_submitted()) {
    $until = match ($choice) {
        'snooze' => time() + optout::SNOOZE,
        'stop' => 0,
        default => null,
    };
    optout::set($userid, $courseid, $until);
    redirect($PAGE->url, get_string('optoutsaved', 'local_remindstudio'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('optouttitle', 'local_remindstudio'));
$until = optout::until($userid, $courseid, time());
if ($until === null) {
    $status = get_string('optout_on', 'local_remindstudio', $coursename);
} else if ($until) {
    $status = get_string('optout_snoozed', 'local_remindstudio', ['course' => $coursename, 'until' => userdate($until)]);
} else {
    $status = get_string('optout_off', 'local_remindstudio', $coursename);
}
echo html_writer::tag('p', $status);
echo html_writer::tag('p', get_string('optout_essential', 'local_remindstudio'), ['class' => 'text-muted small']);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
$buttons = $until === null ? ['snooze', 'stop'] : ['resume'];
foreach ($buttons as $button) {
    echo html_writer::tag(
        'button',
        get_string('optout_' . $button, 'local_remindstudio'),
        ['type' => 'submit', 'name' => 'choice', 'value' => $button, 'class' => 'btn btn-secondary me-2']
    );
}
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
