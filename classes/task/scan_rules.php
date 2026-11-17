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

namespace local_remindstudio\task;

use local_remindstudio\engine\scanner;

/**
 * Checks the reminder rules of every course and queues the reminders that are due.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_rules extends \core\task\scheduled_task {
    /** @var int Seconds a run may take before it stops; the next run carries on with the next course. */
    private const TIME_LIMIT = 60;

    #[\Override]
    public function get_name(): string {
        return get_string('task_scanrules', 'local_remindstudio');
    }

    #[\Override]
    public function execute(): void {
        (new scanner(time()))->scan_all(self::TIME_LIMIT);
    }
}
