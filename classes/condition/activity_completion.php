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

namespace local_remindstudio\condition;

/**
 * Condition: the learner has, or hasn't, completed an activity. Settings: cmid, complete (1 or 0).
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_completion extends base {
    #[\Override]
    public function get_type(): string {
        return 'activitycompletion';
    }

    #[\Override]
    public function get_name(): string {
        return get_string('condition_activitycompletion', 'local_remindstudio');
    }

    #[\Override]
    public function get_sql(array $config, sql_context $ctx): string {
        // Completion states 1 and 2 are COMPLETION_COMPLETE and COMPLETION_COMPLETE_PASS.
        $sql = "EXISTS (SELECT 1 FROM {course_modules_completion} cmc
                         WHERE cmc.userid = u.id AND cmc.coursemoduleid = {$ctx->param(self::int_setting($config, 'cmid'))}
                               AND cmc.completionstate IN (1, 2))";
        return empty($config['complete']) ? "NOT $sql" : $sql;
    }

    #[\Override]
    public function describe(array $config): string {
        $cm = get_coursemodule_from_id('', self::int_setting($config, 'cmid'));
        $name = $cm ? format_string($cm->name) : get_string('missingactivity', 'local_remindstudio');
        return get_string(
            empty($config['complete']) ? 'describe_activitynotcomplete' : 'describe_activitycomplete',
            'local_remindstudio',
            $name
        );
    }
}
