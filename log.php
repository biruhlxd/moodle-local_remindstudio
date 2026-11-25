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
 * The reminders sent in a course, newest first.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use core_user\fields;
use local_remindstudio\output\page;

$courseid = required_param('id', PARAM_INT);
$pageno = optional_param('page', 0, PARAM_INT);
$perpage = 50;
require_login($courseid);
$course = page::setup('log', $courseid, 'viewcourse', ['page' => $pageno]);

$namefields = 'u.' . implode(', u.', fields::get_name_fields());
$total = $DB->count_records('local_remindstudio_log', ['courseid' => $courseid]);
$logs = $DB->get_records_sql("
    SELECT l.id, l.userid, l.status, l.reason, l.timecreated, l.timesent, r.name,
           COALESCE(l.subject, s.subject) AS subject, s.sortorder, $namefields
      FROM {local_remindstudio_log} l
      JOIN {local_remindstudio_rule} r ON r.id = l.ruleid
      JOIN {user} u ON u.id = l.userid
 LEFT JOIN {local_remindstudio_step} s ON s.id = l.stepid
     WHERE l.courseid = :courseid
  ORDER BY l.timecreated DESC, l.id DESC", ['courseid' => $courseid], $pageno * $perpage, $perpage);

echo page::header($course, 'log');
if (!$logs) {
    echo $OUTPUT->notification(get_string('nothingsent', 'local_remindstudio'), 'info', false);
    echo $OUTPUT->footer();
    exit;
}
$table = new html_table();
$table->head = [
    get_string('when', 'local_remindstudio'),
    get_string('learner', 'local_remindstudio'),
    get_string('rulename', 'local_remindstudio'),
    get_string('step', 'local_remindstudio'),
    get_string('stepsubject', 'local_remindstudio'),
    get_string('status'),
];
foreach ($logs as $log) {
    $user = clone $log;
    $user->id = $log->userid;
    $table->data[] = [
        page::date((int) ($log->timesent ?: $log->timecreated)),
        page::learner_link($user, $courseid),
        format_string($log->name),
        $log->sortorder ?? '-',
        s($log->subject ?? ''),
        get_string('status_' . $log->status, 'local_remindstudio') . ($log->reason ? ': ' . s($log->reason) : ''),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $pageno, $perpage, $PAGE->url);
echo $OUTPUT->footer();
