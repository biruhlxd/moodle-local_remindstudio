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

use local_remindstudio\engine\sender;

/**
 * Sends a batch of queued reminders.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_batch extends \core\task\adhoc_task {
    /**
     * Queue a batch.
     *
     * @param int[] $logids Log rows with status "queued".
     */
    public static function queue(array $logids): void {
        $task = new self();
        $task->set_custom_data(['logids' => array_values($logids)]);
        \core\task\manager::queue_adhoc_task($task);
    }

    #[\Override]
    public function get_name(): string {
        return get_string('task_sendbatch', 'local_remindstudio');
    }

    #[\Override]
    public function execute(): void {
        (new sender())->send(array_map('intval', $this->get_custom_data()->logids ?? []));
    }
}
