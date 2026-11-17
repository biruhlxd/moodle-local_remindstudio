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
 * Condition: the learner has, or hasn't, completed the course. Settings: complete (1 or 0).
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completion extends base {
    #[\Override]
    public function get_type(): string {
        return 'coursecompletion';
    }

    #[\Override]
    public function get_name(): string {
        return get_string('condition_coursecompletion', 'local_remindstudio');
    }

    #[\Override]
    public function get_sql(array $config, sql_context $ctx): string {
        $sql = "EXISTS (SELECT 1 FROM {course_completions} cc
                         WHERE cc.userid = u.id AND cc.course = {$ctx->param($ctx->courseid)} AND cc.timecompleted > 0)";
        return empty($config['complete']) ? "NOT $sql" : $sql;
    }

    #[\Override]
    public function describe(array $config): string {
        return get_string(
            empty($config['complete']) ? 'describe_coursenotcomplete' : 'describe_coursecomplete',
            'local_remindstudio'
        );
    }
}
