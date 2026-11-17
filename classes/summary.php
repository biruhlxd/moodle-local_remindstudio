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

namespace local_remindstudio;

/**
 * Rules in plain words, such as "Learners who have not opened the course for 7 days and have not completed the course".
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class summary {
    /**
     * Who a rule is for and when it starts.
     *
     * @param string $trigger One of the rule::TRIGGER_ constants.
     * @param array $conditions Condition tree.
     * @return string
     */
    public static function rule(string $trigger, array $conditions): string {
        $who = self::conditions($conditions);
        return match ($trigger) {
            rule::TRIGGER_ENROLLED => get_string('summary_enrolled', 'local_remindstudio', $who),
            rule::TRIGGER_COURSECOMPLETED => get_string('summary_coursecompleted', 'local_remindstudio', $who),
            default => get_string('summary_scan', 'local_remindstudio', $who),
        };
    }

    /**
     * Who matches a condition tree.
     *
     * @param array $tree
     * @return string
     */
    public static function conditions(array $tree): string {
        if (!isset($tree['type']) && empty($tree['items'])) {
            return get_string('summary_all', 'local_remindstudio');
        }
        return get_string('summary_who', 'local_remindstudio', self::part($tree, true));
    }

    /**
     * When each step is sent.
     *
     * @param \stdClass[] $steps
     * @return string
     */
    public static function steps(array $steps): string {
        $parts = [];
        foreach (array_values($steps) as $i => $step) {
            $a = ['step' => $i + 1, 'delay' => format_time((int) $step->delay)];
            if (!$step->delay) {
                $parts[] = get_string('summary_stepnow', 'local_remindstudio', $a);
            } else {
                $parts[] = get_string($i ? 'summary_stepafter' : 'summary_stepfirst', 'local_remindstudio', $a);
            }
        }
        return implode('; ', $parts);
    }

    /**
     * A condition or group in words.
     *
     * @param array $node
     * @param bool $top Whether it is the outermost group (no brackets).
     * @return string
     */
    private static function part(array $node, bool $top): string {
        if (isset($node['type'])) {
            $config = $node;
            unset($config['type']);
            try {
                return registry::condition($node['type'])->describe($config);
            } catch (\moodle_exception $e) {
                return $node['type'];
            }
        }
        $joiner = get_string(($node['op'] ?? 'all') === 'any' ? 'summary_or' : 'summary_and', 'local_remindstudio');
        $text = implode(" $joiner ", array_map(fn(array $item) => self::part($item, false), $node['items'] ?? []));
        return $top || count($node['items'] ?? []) < 2 ? $text : "($text)";
    }
}
