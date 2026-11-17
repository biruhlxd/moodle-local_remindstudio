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
 * The reminders coming in a course in the next days.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use core_user\fields;
use local_remindstudio\forecast;
use local_remindstudio\output\page;

$courseid = required_param('id', PARAM_INT);
$days = optional_param('days', 14, PARAM_INT);
$days = in_array($days, [1, 7, 14], true) ? $days : 14;
require_login($courseid);
$course = page::setup('upcoming', $courseid, 'viewcourse', ['days' => $days]);

$rows = (new forecast(time(), $days))->course($courseid);
$shown = array_slice($rows, 0, 500);
$users = [];
if ($shown) {
    $users = $DB->get_records_list(
        'user',
        'id',
        array_unique(array_column($shown, 'userid')),
        '',
        'id, ' . implode(', ', fields::get_name_fields())
    );
}

echo page::header($course, 'upcoming');
$choices = [];
foreach ([1, 7, 14] as $choice) {
    $choices[$choice] = get_string('nextdays', 'local_remindstudio', $choice);
}
echo $OUTPUT->single_select(
    $PAGE->url,
    'days',
    $choices,
    $days,
    null,
    null,
    ['label' => get_string('show'), 'labelattributes' => ['class' => 'me-2']]
);
echo html_writer::tag('p', get_string('upcoming_help', 'local_remindstudio'), ['class' => 'text-muted small mt-2']);

if (!$rows) {
    echo $OUTPUT->notification(get_string('noupcoming', 'local_remindstudio'), 'info', false);
} else {
    $table = new html_table();
    $table->head = [
        get_string('when', 'local_remindstudio'),
        get_string('learner', 'local_remindstudio'),
        get_string('rulename', 'local_remindstudio'),
        get_string('step', 'local_remindstudio'),
        get_string('stepsubject', 'local_remindstudio'),
    ];
    foreach ($shown as $row) {
        $rule = format_string($row->rule->name);
        if ($row->rule->is_dryrun($row->time)) {
            $rule .= ' ' . html_writer::span(get_string('dryrun', 'local_remindstudio'), 'badge bg-info text-dark');
        }
        $table->data[] = [
            page::date($row->time),
            page::learner_link($users[$row->userid], $courseid),
            $rule,
            $row->step,
            s($row->subject),
        ];
    }
    echo html_writer::table($table);
    if (count($rows) > count($shown)) {
        echo html_writer::tag('p', get_string('moreupcoming', 'local_remindstudio', count($rows) - count($shown)));
    }
}
echo $OUTPUT->footer();
