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
 * Condition: days before or after the learner's expected finish date. Settings: when (before or after), days.
 *
 * "before" matches from the moment the date is no more than that many days away until it passes; "after" matches
 * once that many days have passed.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deadline extends base {
    #[\Override]
    public function get_type(): string {
        return 'deadline';
    }

    #[\Override]
    public function get_name(): string {
        return get_string('condition_deadline', 'local_remindstudio');
    }

    #[\Override]
    public function get_sql(array $config, sql_context $ctx): string {
        $days = self::int_setting($config, 'days');
        if (self::when($config) === 'before') {
            return "p.expectedend > {$ctx->param($ctx->now)} AND p.expectedend <= {$ctx->param($ctx->now + $days * DAYSECS)}";
        }
        return "p.expectedend > 0 AND p.expectedend <= {$ctx->param($ctx->now - $days * DAYSECS)}";
    }

    #[\Override]
    public function describe(array $config): string {
        return get_string('describe_deadline' . self::when($config), 'local_remindstudio', self::int_setting($config, 'days'));
    }

    /**
     * The "when" setting.
     *
     * @param array $config
     * @return string before or after.
     */
    private static function when(array $config): string {
        $when = $config['when'] ?? '';
        if ($when !== 'before' && $when !== 'after') {
            throw new \invalid_parameter_exception("Condition setting 'when' must be before or after");
        }
        return $when;
    }
}
