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
 * One learner in a course: their dates and pace, their extension, where they are in each rule, and what was sent.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_remindstudio\form\extension_form;
use local_remindstudio\optout;
use local_remindstudio\output\page;
use local_remindstudio\pace\calculator;
use local_remindstudio\pace\course_settings;

$courseid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
require_login($courseid);
$course = page::setup('learner', $courseid, 'viewcourse', ['userid' => $userid]);
$context = context_course::instance($courseid);
$user = core_user::get_user($userid, '*', MUST_EXIST);
$haslog = $DB->record_exists('local_remindstudio_log', ['courseid' => $courseid, 'userid' => $userid]);
if (!is_enrolled($context, $user) && !$haslog) {
    throw new moodle_exception('notenrolled', '', '', fullname($user));
}

$where = ['courseid' => $courseid, 'userid' => $userid];
$canmanage = has_capability('local/remindstudio:managecourse', $context);
if ($canmanage) {
    $override = $DB->get_record('local_remindstudio_override', $where);
    $form = new extension_form($PAGE->url);
    if ($data = $form->get_data()) {
        course_settings::set_extension($courseid, $userid, (int) $data->expectedend, $data->note ?? '');
        (new calculator(time()))->update_course($courseid);
        redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $form->set_data(['expectedend' => $override->expectedend ?? 0, 'note' => $override->note ?? '']);
}

(new calculator(time()))->update_if_stale($courseid);
$pace = $DB->get_record('local_remindstudio_pace', $where);

echo page::header($course, 'learners');
echo $OUTPUT->heading(fullname($user), 3);

$facts = [
    get_string('start', 'local_remindstudio') => page::date((int) ($pace->timestart ?? 0)),
    get_string('finish', 'local_remindstudio') => page::date((int) ($pace->expectedend ?? 0)),
    get_string('progress', 'local_remindstudio') => page::percent($pace->progresspct ?? null),
    get_string('expectedprogress', 'local_remindstudio') => page::percent($pace->expectedpct ?? null),
];
$until = optout::until($userid, $courseid, time());
if ($until !== null) {
    $facts[get_string('remindersoff', 'local_remindstudio')] = $until ? page::date($until) : get_string('yes');
}
echo html_writer::start_tag('dl', ['class' => 'row']);
foreach ($facts as $label => $value) {
    echo html_writer::tag('dt', $label, ['class' => 'col-sm-3']) . html_writer::tag('dd', $value, ['class' => 'col-sm-9']);
}
echo html_writer::end_tag('dl');

if ($canmanage) {
    $form->display();
}

echo $OUTPUT->heading(get_string('learnerrules', 'local_remindstudio'), 4);
$tracks = $DB->get_records_sql('
    SELECT t.*, r.name
      FROM {local_remindstudio_track} t
      JOIN {local_remindstudio_rule} r ON r.id = t.ruleid
     WHERE t.courseid = :courseid AND t.userid = :userid AND t.status <> :cleared
  ORDER BY t.timestarted DESC', $where + ['cleared' => 'cleared']);
if (!$tracks) {
    echo html_writer::tag('p', get_string('notinrules', 'local_remindstudio'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('rulename', 'local_remindstudio'),
        get_string('status'),
        get_string('nextstep', 'local_remindstudio'),
        get_string('when', 'local_remindstudio'),
        get_string('waiting', 'local_remindstudio'),
    ];
    foreach ($tracks as $track) {
        $active = $track->status === 'active';
        $table->data[] = [
            format_string($track->name),
            get_string('track_' . $track->status, 'local_remindstudio'),
            $active ? $track->nextstep : '-',
            $active ? page::date((int) $track->timenextdue) : '-',
            $track->waitreason ? get_string('wait_' . $track->waitreason, 'local_remindstudio') : '',
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->heading(get_string('learnerreminders', 'local_remindstudio'), 4);
$logs = $DB->get_records_sql('
    SELECT l.*, r.name, s.subject, s.sortorder
      FROM {local_remindstudio_log} l
      JOIN {local_remindstudio_rule} r ON r.id = l.ruleid
 LEFT JOIN {local_remindstudio_step} s ON s.id = l.stepid
     WHERE l.courseid = :courseid AND l.userid = :userid
  ORDER BY l.timecreated DESC, l.id DESC', $where, 0, 100);
if (!$logs) {
    echo html_writer::tag('p', get_string('nothingsent', 'local_remindstudio'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('when', 'local_remindstudio'),
        get_string('rulename', 'local_remindstudio'),
        get_string('step', 'local_remindstudio'),
        get_string('stepsubject', 'local_remindstudio'),
        get_string('status'),
    ];
    foreach ($logs as $log) {
        $table->data[] = [
            page::date((int) ($log->timesent ?: $log->timecreated)),
            format_string($log->name),
            $log->sortorder ?? '-',
            s($log->subject ?? ''),
            get_string('status_' . $log->status, 'local_remindstudio') . ($log->reason ? ': ' . s($log->reason) : ''),
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
