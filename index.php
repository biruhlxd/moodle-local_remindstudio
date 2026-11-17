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
 * The reminder rules of a course.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_remindstudio\engine\scanner;
use local_remindstudio\output\page;
use local_remindstudio\pace\calculator;
use local_remindstudio\pace\course_settings;
use local_remindstudio\recipes;
use local_remindstudio\rule;
use local_remindstudio\summary;

$courseid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$ruleid = optional_param('ruleid', 0, PARAM_INT);

require_login($courseid);
$course = page::setup('index', $courseid, 'viewcourse');
$context = context_course::instance($courseid);
$canmanage = has_capability('local/remindstudio:managecourse', $context);
$url = new moodle_url('/local/remindstudio/index.php', ['id' => $courseid]);

if ($action !== '') {
    require_capability('local/remindstudio:managecourse', $context);
    require_sesskey();
    $rule = $ruleid ? rule::get($ruleid) : null;
    if ($rule && $rule->courseid != $courseid) {
        throw new moodle_exception('invalidrecord', 'error', '', 'local_remindstudio_rule');
    }
    switch ($action) {
        case 'enable':
        case 'disable':
            $rule?->set_enabled($action === 'enable');
            break;
        case 'pause':
        case 'resume':
            course_settings::save($courseid, ['paused' => (int) ($action === 'pause')]);
            break;
        case 'delete':
            if (!optional_param('confirm', 0, PARAM_BOOL)) {
                echo page::header($course, 'index');
                echo $OUTPUT->confirm(
                    get_string('deleteruleconfirm', 'local_remindstudio', format_string($rule->name)),
                    new moodle_url($url, ['action' => 'delete', 'ruleid' => $rule->id, 'confirm' => 1, 'sesskey' => sesskey()]),
                    $url
                );
                echo $OUTPUT->footer();
                exit;
            }
            $rule->delete();
            break;
    }
    redirect($url);
}

$settings = course_settings::get($courseid);
$rules = rule::get_for_course($courseid);
(new calculator(time()))->update_if_stale($courseid);
$scanner = new scanner(time());
$sent = $DB->get_records_sql_menu("
    SELECT ruleid, COUNT(1)
      FROM {local_remindstudio_log}
     WHERE courseid = :courseid AND status = 'sent' AND timesent > :since
  GROUP BY ruleid", ['courseid' => $courseid, 'since' => time() - 7 * DAYSECS]);

echo page::header($course, 'index');

if ($settings->paused) {
    echo $OUTPUT->notification(get_string('coursepaused', 'local_remindstudio'), 'warning');
}
if ($canmanage) {
    echo html_writer::start_div('d-flex flex-wrap align-items-center mb-3');
    echo $OUTPUT->single_select(
        new moodle_url('/local/remindstudio/edit.php', ['id' => $courseid]),
        'recipe',
        ['blank' => get_string('blankrule', 'local_remindstudio')] + recipes::names(),
        '',
        ['' => get_string('addrule', 'local_remindstudio')],
        null,
        ['label' => get_string('addrule', 'local_remindstudio'), 'labelattributes' => ['class' => 'sr-only']]
    );
    $pause = $settings->paused ? 'resume' : 'pause';
    echo html_writer::div($OUTPUT->single_button(
        new moodle_url($url, ['action' => $pause, 'sesskey' => sesskey()]),
        get_string('course' . $pause, 'local_remindstudio'),
        'post'
    ), 'ms-auto');
    echo html_writer::end_div();
}

$inherited = rule::get_inherited($courseid)->get_rules();
if (!$rules) {
    echo $OUTPUT->notification(get_string('norules', 'local_remindstudio'), 'info', false);
}

$table = new html_table();
$table->head = [
    get_string('rulename', 'local_remindstudio'),
    get_string('rulewhowhen', 'local_remindstudio'),
    get_string('matchingnow', 'local_remindstudio'),
    get_string('sentweek', 'local_remindstudio'),
];
if ($canmanage) {
    $table->head[] = get_string('actions');
}
foreach ($rules as $rule) {
    $name = format_string($rule->name);
    if (!$rule->enabled) {
        $name .= ' ' . html_writer::span(get_string('off', 'local_remindstudio'), 'badge bg-secondary');
    } else if ($rule->is_dryrun(time())) {
        $name .= ' ' . html_writer::span(get_string('dryrun', 'local_remindstudio'), 'badge bg-info text-dark');
    }
    if ($rule->essential) {
        $name .= ' ' . html_writer::span(get_string('essential', 'local_remindstudio'), 'badge bg-warning text-dark');
    }
    $row = [
        $name,
        s(summary::rule($rule->triggertype, $rule->conditions)) . html_writer::div(
            s(summary::steps($rule->get_steps())),
            'small text-muted'
        ),
        $rule->enabled ? count($scanner->matching_users($rule)) : '-',
        $sent[$rule->id] ?? 0,
    ];
    if ($canmanage) {
        $links = [
            html_writer::link(
                new moodle_url('/local/remindstudio/edit.php', ['id' => $courseid, 'ruleid' => $rule->id]),
                get_string('edit')
            ),
            html_writer::link(
                new moodle_url($url, ['action' => $rule->enabled ? 'disable' : 'enable', 'ruleid' => $rule->id,
                    'sesskey' => sesskey()]),
                get_string($rule->enabled ? 'turnoff' : 'turnon', 'local_remindstudio')
            ),
            html_writer::link(
                new moodle_url($url, ['action' => 'delete', 'ruleid' => $rule->id, 'sesskey' => sesskey()]),
                get_string('delete')
            ),
        ];
        $row[] = implode(' · ', $links);
    }
    $table->data[] = $row;
}
if ($rules) {
    echo html_writer::table($table);
}

if ($inherited) {
    echo $OUTPUT->heading(get_string('siterules', 'local_remindstudio'), 3);
    $table = new html_table();
    $table->head = [
        get_string('rulename', 'local_remindstudio'),
        get_string('rulewhowhen', 'local_remindstudio'),
        get_string('matchingnow', 'local_remindstudio'),
        get_string('sentweek', 'local_remindstudio'),
        get_string('actions'),
    ];
    foreach ($inherited as $entry) {
        $rule = $entry->rule;
        $name = format_string($rule->name);
        if (!$entry->active) {
            $name .= ' ' . html_writer::span(get_string('offhere', 'local_remindstudio'), 'badge bg-secondary');
        }
        if ($entry->locked) {
            $name .= ' ' . html_writer::span(get_string('locked', 'local_remindstudio'), 'badge bg-light text-dark');
        }
        $table->data[] = [
            $name,
            s(summary::rule($rule->triggertype, $rule->conditions))
                . html_writer::div(s(summary::steps($rule->get_steps())), 'small text-muted'),
            $entry->active ? count($scanner->matching_users($rule)) : '-',
            $sent[$rule->id] ?? 0,
            $canmanage && $entry->manageurl && !$entry->locked
                ? html_writer::link($entry->manageurl, get_string($entry->active ? 'turnoff' : 'turnon', 'local_remindstudio'))
                : '',
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
