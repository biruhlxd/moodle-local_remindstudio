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
 * A condition a learner can match, such as "hasn't opened the course for 7 days".
 *
 * A condition is checked for all the learners of a course in one query: get_sql() returns a WHERE fragment about the
 * learner u.id, which may also use p, their row in local_remindstudio_pace (null when it hasn't been worked out yet).
 * Other plugins add conditions through the \local_remindstudio\hook\collect_types hook.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * The name the condition is stored under, unique across plugins.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * The name shown to teachers.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * SQL that is true for the learners who match.
     *
     * @param array $config The condition's settings from the rule.
     * @param sql_context $ctx Course, time and parameters.
     * @return string
     */
    abstract public function get_sql(array $config, sql_context $ctx): string;

    /**
     * The condition in plain words, to complete "Learners who ...".
     *
     * @param array $config
     * @return string
     */
    abstract public function describe(array $config): string;

    /**
     * Throw an exception if the settings are not valid.
     *
     * @param array $config
     */
    public function validate(array $config): void {
        $this->get_sql($config, new sql_context(0, 0));
    }

    /**
     * A whole number setting.
     *
     * @param array $config
     * @param string $key
     * @return int
     */
    protected static function int_setting(array $config, string $key): int {
        if (!isset($config[$key]) || !is_numeric($config[$key]) || (int) $config[$key] < 0) {
            throw new \invalid_parameter_exception("Condition setting '$key' must be a whole number of 0 or more");
        }
        return (int) $config[$key];
    }

    /**
     * A comparison operator setting.
     *
     * @param array $config
     * @param string $key
     * @return string One of <, <=, >, >=, =.
     */
    protected static function operator_setting(array $config, string $key = 'op'): string {
        $op = $config[$key] ?? '';
        if (!in_array($op, ['<', '<=', '>', '>=', '='], true)) {
            throw new \invalid_parameter_exception("Condition setting '$key' must be <, <=, >, >= or =");
        }
        return $op;
    }
}
