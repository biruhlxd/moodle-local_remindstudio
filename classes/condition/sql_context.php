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
 * Collects the SQL parameters of the conditions of one rule, with names that can't clash.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sql_context {
    /** @var array Named parameters added so far. */
    public array $params = [];

    /** @var int Parameters added so far. */
    private int $count = 0;

    /**
     * Constructor.
     *
     * @param int $courseid The course the rule is checked in.
     * @param int $now The time the rule is checked at.
     */
    public function __construct(
        /** @var int The course the rule is checked in. */
        public readonly int $courseid,
        /** @var int The time the rule is checked at. */
        public readonly int $now,
    ) {
    }

    /**
     * Add a parameter.
     *
     * @param int|string $value
     * @return string The placeholder to put in the SQL, such as ":rsc3".
     */
    public function param(int|string $value): string {
        $name = 'rsc' . (++$this->count);
        $this->params[$name] = $value;
        return ':' . $name;
    }
}
