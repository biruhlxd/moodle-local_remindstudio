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

use local_remindstudio\rule;

/**
 * Lets other plugins apply site rules to a course.
 *
 * A site rule is stored with course 0; the plugin adds it with rule::with_course(). An inactive one (turned off in
 * this course) is shown on the course's Rules tab but not checked.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Lets other plugins apply site-wide Reminder Studio rules to a course')]
#[\core\attribute\tags('message', 'course')]
final class course_rules {
    /** @var \stdClass[] Each: rule, active, locked, manageurl. */
    private array $rules = [];

    /**
     * Constructor.
     *
     * @param int $courseid
     */
    public function __construct(
        /** @var int The course. */
        public readonly int $courseid,
    ) {
    }

    /**
     * Apply a site rule to the course.
     *
     * @param rule $rule The site rule, with_course() this course.
     * @param bool $active False when it is turned off in this course.
     * @param bool $locked Whether teachers can't turn it off in the course.
     * @param \moodle_url|null $manageurl Where teachers turn it off or on in the course.
     */
    public function add_rule(rule $rule, bool $active = true, bool $locked = false, ?\moodle_url $manageurl = null): void {
        $this->rules[$rule->id] ??= (object) [
            'rule' => $rule,
            'active' => $active,
            'locked' => $locked,
            'manageurl' => $manageurl,
        ];
    }

    /**
     * The site rules added so far.
     *
     * @return \stdClass[] Each: rule, active, locked, manageurl.
     */
    public function get_rules(): array {
        return array_values($this->rules);
    }
}
