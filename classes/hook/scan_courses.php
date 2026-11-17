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

/**
 * Lets other plugins add courses to the regular scan, such as those their site rules apply to.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Lets other plugins add courses to the Reminder Studio scan')]
#[\core\attribute\tags('message', 'course')]
final class scan_courses {
    /** @var int[] Course ids, keyed by id. */
    private array $courseids = [];

    /**
     * Add courses.
     *
     * @param int[] $courseids
     */
    public function add_courseids(array $courseids): void {
        foreach ($courseids as $courseid) {
            $this->courseids[(int) $courseid] = (int) $courseid;
        }
    }

    /**
     * The courses added so far.
     *
     * @return int[]
     */
    public function get_courseids(): array {
        return array_values($this->courseids);
    }
}
