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

/**
 * Deletes log rows and cleared tracks older than the retention period.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_log extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_cleanuplog', 'local_remindstudio');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        $days = (int) get_config('local_remindstudio', 'logretention');
        if ($days <= 0) {
            return;
        }
        $before = time() - $days * DAYSECS;
        $DB->delete_records_select(
            'local_remindstudio_log',
            'timecreated < :before AND status <> :queued',
            ['before' => $before, 'queued' => 'queued']
        );
        $DB->delete_records_select(
            'local_remindstudio_track',
            'timestarted < :before AND status = :cleared',
            ['before' => $before, 'cleared' => 'cleared']
        );
    }
}
