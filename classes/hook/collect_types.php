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

namespace local_remindstudio\hook;

use local_remindstudio\action\base as action;
use local_remindstudio\condition\base as condition;

/**
 * Lets other plugins add their own conditions and actions to Reminder Studio.
 *
 * The built-in ones are added first, and the first one added with a given type wins.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Lets other plugins add their own conditions and actions to Reminder Studio rules')]
#[\core\attribute\tags('message', 'course')]
final class collect_types {
    /** @var condition[] Conditions by type. */
    private array $conditions = [];

    /** @var action[] Actions by type. */
    private array $actions = [];

    /**
     * Add a condition.
     *
     * @param condition $condition
     */
    public function add_condition(condition $condition): void {
        $this->conditions[$condition->get_type()] ??= $condition;
    }

    /**
     * Add an action.
     *
     * @param action $action
     */
    public function add_action(action $action): void {
        $this->actions[$action->get_type()] ??= $action;
    }

    /**
     * The conditions added so far.
     *
     * @return condition[] By type.
     */
    public function get_conditions(): array {
        return $this->conditions;
    }

    /**
     * The actions added so far.
     *
     * @return action[] By type.
     */
    public function get_actions(): array {
        return $this->actions;
    }
}
