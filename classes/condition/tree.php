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

use local_remindstudio\registry;

/**
 * The conditions of a rule: groups of conditions joined by ALL or ANY.
 *
 * A group is ['op' => 'all'|'any', 'items' => [...]]; a condition is ['type' => 'inactive', 'days' => 7, ...].
 * An empty ALL group matches everyone.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tree {
    /**
     * SQL that is true for the learners who match the tree.
     *
     * @param array $node
     * @param sql_context $ctx
     * @return string
     */
    public static function to_sql(array $node, sql_context $ctx): string {
        if (isset($node['type'])) {
            return '(' . registry::condition($node['type'])->get_sql(self::config($node), $ctx) . ')';
        }
        $op = self::op($node);
        $parts = array_map(fn(array $item) => self::to_sql($item, $ctx), $node['items'] ?? []);
        if (!$parts) {
            return $op === 'all' ? '1 = 1' : '1 = 0';
        }
        return '(' . implode($op === 'all' ? ' AND ' : ' OR ', $parts) . ')';
    }

    /**
     * Throw an exception if a condition is unknown or has settings that are not valid.
     *
     * @param array $node
     */
    public static function validate(array $node): void {
        if (isset($node['type'])) {
            registry::condition($node['type'])->validate(self::config($node));
            return;
        }
        self::op($node);
        foreach ($node['items'] ?? [] as $item) {
            if (!is_array($item)) {
                throw new \invalid_parameter_exception('Each item of a condition group must be a condition or a group');
            }
            self::validate($item);
        }
    }

    /**
     * The settings of a condition: everything but its type.
     *
     * @param array $node
     * @return array
     */
    private static function config(array $node): array {
        unset($node['type']);
        return $node;
    }

    /**
     * The operator of a group.
     *
     * @param array $node
     * @return string all or any.
     */
    private static function op(array $node): string {
        $op = $node['op'] ?? 'all';
        if ($op !== 'all' && $op !== 'any') {
            throw new \invalid_parameter_exception("A condition group must be 'all' or 'any'");
        }
        return $op;
    }
}
