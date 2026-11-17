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
 * Condition: the learner has never opened the course.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class never_accessed extends base {
    #[\Override]
    public function get_type(): string {
        return 'neveraccessed';
    }

    #[\Override]
    public function get_name(): string {
        return get_string('condition_neveraccessed', 'local_remindstudio');
    }

    #[\Override]
    public function get_sql(array $config, sql_context $ctx): string {
        return "NOT EXISTS (SELECT 1 FROM {user_lastaccess} ula
                             WHERE ula.userid = u.id AND ula.courseid = {$ctx->param($ctx->courseid)})";
    }

    #[\Override]
    public function describe(array $config): string {
        return get_string('describe_neveraccessed', 'local_remindstudio');
    }
}
