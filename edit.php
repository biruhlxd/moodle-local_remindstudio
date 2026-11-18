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
 * Add or edit a reminder rule, blank or from a recipe.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_remindstudio\form\rule_form;
use local_remindstudio\output\page;
use local_remindstudio\recipes;
use local_remindstudio\rule;

$courseid = required_param('id', PARAM_INT);
$ruleid = optional_param('ruleid', 0, PARAM_INT);
$recipe = optional_param('recipe', '', PARAM_ALPHANUMEXT);

require_login($courseid);
$course = page::setup('edit', $courseid, 'managecourse', array_filter(['ruleid' => $ruleid, 'recipe' => $recipe]));
$context = context_course::instance($courseid);
$index = new moodle_url('/local/remindstudio/index.php', ['id' => $courseid]);

if ($ruleid) {
    $rule = rule::get($ruleid);
    if ($rule->courseid != $courseid) {
        throw new moodle_exception('invalidrecord', 'error', '', 'local_remindstudio_rule');
    }
    [$data, $steps] = $rule->export();
} else if ($recipe !== '' && $recipe !== 'blank') {
    [$data, $steps] = recipes::get($recipe, $courseid);
} else {
    $data = ['name' => '', 'conditions' => ['op' => 'all', 'items' => []]];
    $steps = [['subject' => '', 'body' => '']];
}
if (!$data['roles'] = $data['roles'] ?? []) {
    $data['roles'] = array_keys(get_archetype_roles('student'));
}

$activities = [];
foreach (get_fast_modinfo($course)->get_cms() as $cm) {
    if ($cm->completion && !$cm->deletioninprogress) {
        $activities[$cm->id] = $cm->get_formatted_name();
    }
}
$formconditions = rule_form::form_conditions($data['conditions']);
$form = new rule_form($PAGE->url, [
    'activities' => $activities,
    'roles' => get_viewable_roles($context),
    'conditioncount' => max(1, count($formconditions)),
    'stepcount' => max(1, count($steps)),
    'dryrununtil' => $data['dryrununtil'] ?? 0,
]);

if ($form->is_cancelled()) {
    redirect($index);
}
if ($formdata = $form->get_data()) {
    [$newdata, $newsteps] = $form->to_rule($formdata, $courseid);
    if ($ruleid) {
        rule::update($ruleid, $newdata, $newsteps);
    } else {
        rule::create($newdata, $newsteps);
    }
    redirect($index, get_string('rulesaved', 'local_remindstudio'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form->set_data(rule_form::to_formdata($data, $steps));
echo page::header($course, 'index');
echo $OUTPUT->heading(get_string($ruleid ? 'editrule' : 'addrule', 'local_remindstudio'), 3);
if (count($formconditions) !== count($data['conditions']['items'] ?? [])) {
    echo $OUTPUT->notification(get_string('conditionsnotshown', 'local_remindstudio'), 'warning');
}
$form->display();
echo $OUTPUT->footer();
