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

use local_remindstudio\pace\course_settings;

/**
 * Restores a course's pacing settings and reminder rules, pointing activity conditions at the restored activities.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_remindstudio_plugin extends restore_local_plugin {
    /**
     * The paths to restore.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element('local_remindstudio_pacing', $this->get_pathfor('/pacing')),
            new restore_path_element('local_remindstudio_rule', $this->get_pathfor('/rules/rule')),
            new restore_path_element('local_remindstudio_step', $this->get_pathfor('/rules/rule/steps/step')),
        ];
    }

    /**
     * Restore the pacing settings.
     *
     * @param array $data
     */
    public function process_local_remindstudio_pacing($data) {
        course_settings::save($this->task->get_courseid(), (array) $data);
    }

    /**
     * Restore a rule.
     *
     * @param array $data
     */
    public function process_local_remindstudio_rule($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        unset($data->id);
        $data->courseid = $this->task->get_courseid();
        $newid = $DB->insert_record('local_remindstudio_rule', $data);
        $this->set_mapping('local_remindstudio_rule', $oldid, $newid);
    }

    /**
     * Restore a step.
     *
     * @param array $data
     */
    public function process_local_remindstudio_step($data) {
        global $DB;
        $data = (object) $data;
        unset($data->id);
        $data->ruleid = $this->get_new_parentid('local_remindstudio_rule');
        $DB->insert_record('local_remindstudio_step', $data);
    }

    /**
     * Once the activities are restored: point conditions and exits at the new activities and roles. A rule whose
     * activity wasn't restored is turned off.
     */
    public function after_restore_course() {
        global $DB;
        $mappings = $DB->get_records(
            'backup_ids_temp',
            ['backupid' => $this->get_restoreid(), 'itemname' => 'local_remindstudio_rule'],
            '',
            'id, newitemid'
        );
        foreach ($mappings as $mapping) {
            $rule = $DB->get_record('local_remindstudio_rule', ['id' => $mapping->newitemid]);
            if (!$rule) {
                continue;
            }
            $missing = false;
            $conditions = json_decode($rule->conditions ?? '', true) ?: [];
            $conditions = $this->map_conditions($conditions, $missing);
            $exits = json_decode($rule->exits ?? '', true) ?: [];
            if (!empty($exits['cmid'])) {
                $exits['cmid'] = $this->map_cmid((int) $exits['cmid'], $missing);
            }
            $audience = json_decode($rule->audience ?? '', true) ?: [];
            $audience['roles'] = array_map(
                fn($roleid) => (int) ($this->get_mappingid('role', $roleid) ?: $roleid),
                $audience['roles'] ?? []
            );
            $DB->update_record('local_remindstudio_rule', [
                'id' => $rule->id,
                'conditions' => json_encode($conditions),
                'exits' => json_encode($exits),
                'audience' => json_encode($audience),
                'enabled' => $missing ? 0 : $rule->enabled,
            ]);
        }
    }

    /**
     * Point the activity conditions of a tree at the restored activities.
     *
     * @param array $node
     * @param bool $missing Set when an activity wasn't restored.
     * @return array
     */
    private function map_conditions(array $node, bool &$missing): array {
        if (isset($node['type'])) {
            if (isset($node['cmid'])) {
                $node['cmid'] = $this->map_cmid((int) $node['cmid'], $missing);
            }
            return $node;
        }
        foreach ($node['items'] ?? [] as $i => $item) {
            $node['items'][$i] = $this->map_conditions($item, $missing);
        }
        return $node;
    }

    /**
     * The restored course module id.
     *
     * @param int $cmid
     * @param bool $missing Set when the activity wasn't restored.
     * @return int
     */
    private function map_cmid(int $cmid, bool &$missing): int {
        $newid = (int) $this->get_mappingid('course_module', $cmid);
        if (!$newid) {
            $missing = true;
        }
        return $newid;
    }
}
