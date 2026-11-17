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
 * Backs up a course's pacing settings and reminder rules (not learners' data).
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_remindstudio_plugin extends backup_local_plugin {
    /**
     * The course's settings and rules.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $pacing = new backup_nested_element('pacing', ['id'], ['pacingmode', 'duration', 'autopace', 'paused']);
        $rules = new backup_nested_element('rules');
        $rule = new backup_nested_element('rule', ['id'], ['name', 'enabled', 'triggertype', 'audience', 'conditions',
            'exits', 'priority', 'essential', 'dryrununtil', 'timecreated', 'timemodified']);
        $steps = new backup_nested_element('steps');
        $step = new backup_nested_element('step', ['id'], ['sortorder', 'delay', 'actions', 'subject', 'body', 'bodyformat']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($pacing);
        $wrapper->add_child($rules);
        $rules->add_child($rule);
        $rule->add_child($steps);
        $steps->add_child($step);

        $pacing->set_source_table('local_remindstudio_course', ['courseid' => backup::VAR_COURSEID]);
        $rule->set_source_table('local_remindstudio_rule', ['courseid' => backup::VAR_COURSEID]);
        $step->set_source_table('local_remindstudio_step', ['ruleid' => backup::VAR_PARENTID], 'sortorder ASC');
        return $plugin;
    }
}
