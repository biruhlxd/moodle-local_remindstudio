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

namespace local_remindstudio\event;

/**
 * A reminder was sent to a learner.
 *
 * @property-read array $other ruleid and stepid.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder_sent extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_remindstudio_log';
    }

    #[\Override]
    public static function get_name() {
        return get_string('event_remindersent', 'local_remindstudio');
    }

    #[\Override]
    public function get_description() {
        return "Reminder Studio sent step with id '{$this->other['stepid']}' of rule with id '{$this->other['ruleid']}' "
            . "to the user with id '{$this->relateduserid}'.";
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
        if (!isset($this->other['ruleid'], $this->other['stepid'])) {
            throw new \coding_exception('The ruleid and stepid must be set in other.');
        }
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'local_remindstudio_log', 'restore' => \core\event\base::NOT_MAPPED];
    }

    #[\Override]
    public static function get_other_mapping() {
        return ['ruleid' => \core\event\base::NOT_MAPPED, 'stepid' => \core\event\base::NOT_MAPPED];
    }
}
