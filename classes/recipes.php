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
 * Ready-made rules a teacher starts from and adjusts. Their names and messages are language strings.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recipes {
    /** @var array Each recipe: trigger, conditions, exits, and per step its delay in days and whether teachers get a copy. */
    private const RECIPES = [
        'welcome' => [
            'trigger' => rule::TRIGGER_ENROLLED,
            'conditions' => [],
            'exits' => [],
            'steps' => [['hours' => 1]],
        ],
        'neverstarted' => [
            'trigger' => rule::TRIGGER_SCAN,
            'conditions' => [['type' => 'neveraccessed'], ['type' => 'enrolleddays', 'op' => '>=', 'days' => 3]],
            'exits' => ['accessed' => 1, 'coursecompleted' => 1],
            'steps' => [['days' => 0], ['days' => 4], ['days' => 3, 'copy' => true]],
        ],
        'inactive' => [
            'trigger' => rule::TRIGGER_SCAN,
            'conditions' => [['type' => 'inactive', 'days' => 7], ['type' => 'coursecompletion', 'complete' => 0]],
            'exits' => ['accessed' => 1, 'coursecompleted' => 1],
            'steps' => [['days' => 0], ['days' => 7], ['days' => 7, 'copy' => true]],
        ],
        'behindpace' => [
            'trigger' => rule::TRIGGER_SCAN,
            'conditions' => [['type' => 'behindpace', 'points' => 20], ['type' => 'coursecompletion', 'complete' => 0]],
            'exits' => ['coursecompleted' => 1],
            'steps' => [['days' => 0], ['days' => 7, 'copy' => true]],
        ],
        'deadline' => [
            'trigger' => rule::TRIGGER_SCAN,
            'conditions' => [['type' => 'deadline', 'when' => 'before', 'days' => 14],
                ['type' => 'coursecompletion', 'complete' => 0]],
            'exits' => ['coursecompleted' => 1],
            'steps' => [['days' => 0], ['days' => 11, 'copy' => true]],
        ],
        'misseddeadline' => [
            'trigger' => rule::TRIGGER_SCAN,
            'conditions' => [['type' => 'deadline', 'when' => 'after', 'days' => 1],
                ['type' => 'coursecompletion', 'complete' => 0]],
            'exits' => ['coursecompleted' => 1],
            'steps' => [['days' => 0, 'copy' => true]],
        ],
        'completed' => [
            'trigger' => rule::TRIGGER_COURSECOMPLETED,
            'conditions' => [],
            'exits' => [],
            'steps' => [['days' => 0]],
        ],
    ];

    /**
     * The recipes' names.
     *
     * @return string[] By key.
     */
    public static function names(): array {
        $names = [];
        foreach (array_keys(self::RECIPES) as $key) {
            $names[$key] = get_string('recipe_' . $key, 'local_remindstudio');
        }
        return $names;
    }

    /**
     * A recipe as rule data and steps, as rule::create() takes them.
     *
     * @param string $key
     * @param int $courseid
     * @return array [data, steps]
     */
    public static function get(string $key, int $courseid): array {
        $recipe = self::RECIPES[$key] ?? throw new \moodle_exception('error_unknownrecipe', 'local_remindstudio', '', $key);
        $data = [
            'courseid' => $courseid,
            'name' => get_string('recipe_' . $key, 'local_remindstudio'),
            'triggertype' => $recipe['trigger'],
            'conditions' => ['op' => 'all', 'items' => $recipe['conditions']],
            'exits' => $recipe['exits'],
        ];
        $steps = [];
        foreach ($recipe['steps'] as $i => $step) {
            $n = $i + 1;
            $steps[] = [
                'delay' => ($step['days'] ?? 0) * DAYSECS + ($step['hours'] ?? 0) * HOURSECS,
                'subject' => get_string("recipe_{$key}_subject{$n}", 'local_remindstudio'),
                'body' => get_string("recipe_{$key}_body{$n}", 'local_remindstudio'),
                'bodyformat' => FORMAT_HTML,
                'actions' => empty($step['copy']) ? ['messagelearner'] : ['messagelearner', 'copyteachers'],
            ];
        }
        return [$data, $steps];
    }
}
