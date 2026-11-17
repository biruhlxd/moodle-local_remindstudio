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
 * Each learner's dates and pace in a course, the furthest behind first.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use core_user\fields;
use local_remindstudio\output\page;
use local_remindstudio\pace\calculator;

$courseid = required_param('id', PARAM_INT);
require_login($courseid);
$course = page::setup('learners', $courseid, 'viewcourse');
(new calculator(time()))->update_if_stale($courseid);

$namefields = 'u.' . implode(', u.', fields::get_name_fields());
$learners = $DB->get_records_sql("
    SELECT p.id, p.userid, p.timestart, p.expectedend, p.progresspct, p.expectedpct, p.pacegap, $namefields, ula.timeaccess,
           (SELECT COUNT(1) FROM {local_remindstudio_log} l
             WHERE l.courseid = p.courseid AND l.userid = p.userid AND l.status = 'sent') AS sent
      FROM {local_remindstudio_pace} p
      JOIN {user} u ON u.id = p.userid
 LEFT JOIN {user_lastaccess} ula ON ula.userid = p.userid AND ula.courseid = p.courseid
     WHERE p.courseid = :courseid", ['courseid' => $courseid]);
// The furthest behind first, those without a pace last.
usort($learners, fn($a, $b) => [$a->pacegap === null, -(int) $a->pacegap, fullname($a)]
    <=> [$b->pacegap === null, -(int) $b->pacegap, fullname($b)]);

echo page::header($course, 'learners');
if (!$learners) {
    echo $OUTPUT->notification(get_string('nolearners', 'local_remindstudio'), 'info', false);
    echo $OUTPUT->footer();
    exit;
}
$table = new html_table();
$table->head = [
    get_string('learner', 'local_remindstudio'),
    get_string('start', 'local_remindstudio'),
    get_string('finish', 'local_remindstudio'),
    get_string('progress', 'local_remindstudio'),
    get_string('expectedprogress', 'local_remindstudio'),
    get_string('behind', 'local_remindstudio'),
    get_string('lastaccess'),
    get_string('sent', 'local_remindstudio'),
];
foreach ($learners as $learner) {
    $learner->id = $learner->userid;
    $gap = $learner->pacegap === null ? '-' : get_string('points', 'local_remindstudio', $learner->pacegap);
    if ($learner->pacegap !== null && $learner->pacegap >= 20) {
        $gap = html_writer::span($gap, 'badge bg-danger');
    }
    $table->data[] = [
        page::learner_link($learner, $courseid),
        page::date((int) $learner->timestart),
        page::date((int) $learner->expectedend),
        page::percent($learner->progresspct),
        page::percent($learner->expectedpct),
        $gap,
        $learner->timeaccess ? format_time(time() - $learner->timeaccess) : get_string('never'),
        $learner->sent,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
