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

use local_remindstudio\hook\collect_types;

/**
 * The conditions and actions rules can use: the built-in ones and those other plugins add.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var collect_types|null The types, once collected. */
    private static ?collect_types $types = null;

    /**
     * All conditions.
     *
     * @return condition\base[] By type.
     */
    public static function conditions(): array {
        return self::types()->get_conditions();
    }

    /**
     * All actions.
     *
     * @return action\base[] By type.
     */
    public static function actions(): array {
        return self::types()->get_actions();
    }

    /**
     * One condition.
     *
     * @param string $type
     * @return condition\base
     */
    public static function condition(string $type): condition\base {
        return self::conditions()[$type] ?? throw new \moodle_exception('error_unknowncondition', 'local_remindstudio', '', $type);
    }

    /**
     * One action.
     *
     * @param string $type
     * @return action\base
     */
    public static function action(string $type): action\base {
        return self::actions()[$type] ?? throw new \moodle_exception('error_unknownaction', 'local_remindstudio', '', $type);
    }

    /**
     * Forget the collected types (for tests).
     */
    public static function reset(): void {
        self::$types = null;
    }

    /**
     * Collect the types once per request.
     *
     * @return collect_types
     */
    private static function types(): collect_types {
        if (self::$types === null) {
            $types = new collect_types();
            $conditions = [
                new condition\never_accessed(),
                new condition\inactive(),
                new condition\enrolled_days(),
                new condition\progress(),
                new condition\behind_pace(),
                new condition\deadline(),
                new condition\activity_completion(),
                new condition\course_completion(),
            ];
            foreach ($conditions as $condition) {
                $types->add_condition($condition);
            }
            $types->add_action(new action\message_learner());
            $types->add_action(new action\copy_teachers());
            \core\di::get(\core\hook\manager::class)->dispatch($types);
            self::$types = $types;
        }
        return self::$types;
    }
}
