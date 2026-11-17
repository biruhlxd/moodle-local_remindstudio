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

use local_remindstudio\condition\tree;
use stdClass;

/**
 * A reminder rule: who it is for, when it starts, its conditions, its steps and when a learner leaves it.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule {
    /** @var string Checked on every scan: a learner starts when they match the conditions. */
    public const TRIGGER_SCAN = 'scan';
    /** @var string A learner starts when they are enrolled. */
    public const TRIGGER_ENROLLED = 'enrolled';
    /** @var string A learner starts when they complete the course. */
    public const TRIGGER_COURSECOMPLETED = 'coursecompleted';
    /** @var int Most steps a rule can have. */
    public const MAX_STEPS = 5;

    /** @var int */
    public readonly int $id;
    /** @var int */
    public readonly int $courseid;
    /** @var string */
    public readonly string $name;
    /** @var string One of the TRIGGER_ constants. */
    public readonly string $triggertype;
    /** @var int Higher goes first when sending limits allow only some reminders. */
    public readonly int $priority;
    /** @var int Until when the rule only logs what it would send. */
    public readonly int $dryrununtil;
    /** @var bool */
    public readonly bool $enabled;
    /** @var bool Reaches learners who opted out of the course's reminders. */
    public readonly bool $essential;
    /** @var int[] Role ids the rule is for; empty means the student roles. */
    public readonly array $roles;
    /** @var array Condition tree. */
    public readonly array $conditions;
    /** @var array Exit conditions: accessed, coursecompleted, cmid. */
    public readonly array $exits;
    /** @var bool A site rule (stored with course 0) that another plugin applies to courses. */
    public readonly bool $template;
    /** @var stdClass The record the rule was made from. */
    private readonly stdClass $record;

    /**
     * Constructor.
     *
     * @param stdClass $record From local_remindstudio_rule.
     * @param bool $template A site rule applied to the course in $record->courseid.
     */
    private function __construct(stdClass $record, bool $template = false) {
        $this->record = $record;
        $this->template = $template || !$record->courseid;
        $this->id = (int) $record->id;
        $this->courseid = (int) $record->courseid;
        $this->name = $record->name;
        $this->triggertype = $record->triggertype;
        $this->priority = (int) $record->priority;
        $this->dryrununtil = (int) $record->dryrununtil;
        $this->enabled = (bool) $record->enabled;
        $this->essential = (bool) $record->essential;
        $this->roles = array_map('intval', json_decode($record->audience ?? '', true)['roles'] ?? []);
        $this->conditions = json_decode($record->conditions ?? '', true) ?: ['op' => 'all', 'items' => []];
        $this->exits = json_decode($record->exits ?? '', true) ?: [];
    }

    /**
     * Create a rule with its steps.
     *
     * @param array $data courseid, name, and optionally enabled, triggertype, roles, conditions, exits, priority,
     *     essential, dryrununtil.
     * @param array $steps Each: subject, body, and optionally delay (seconds), actions (types), bodyformat.
     * @return self
     */
    public static function create(array $data, array $steps): self {
        return self::save(0, $data, $steps);
    }

    /**
     * Change a rule and its steps. Learners keep their place: a learner waiting for step 3 gets the new step 3.
     *
     * @param int $id
     * @param array $data As for create(); courseid is ignored.
     * @param array $steps As for create().
     * @return self
     */
    public static function update(int $id, array $data, array $steps): self {
        return self::save($id, $data, $steps);
    }

    /**
     * Throw an exception if a rule's data or steps are not valid.
     *
     * @param array $data As for create().
     * @param array $steps As for create().
     */
    public static function validate(array $data, array $steps): void {
        $trigger = $data['triggertype'] ?? self::TRIGGER_SCAN;
        if (!in_array($trigger, [self::TRIGGER_SCAN, self::TRIGGER_ENROLLED, self::TRIGGER_COURSECOMPLETED], true)) {
            throw new \invalid_parameter_exception('Unknown trigger: ' . $trigger);
        }
        tree::validate($data['conditions'] ?? ['op' => 'all', 'items' => []]);
        if (!$steps || count($steps) > self::MAX_STEPS) {
            throw new \invalid_parameter_exception('A rule needs 1 to ' . self::MAX_STEPS . ' steps');
        }
        foreach ($steps as $step) {
            foreach ($step['actions'] ?? ['messagelearner'] as $action) {
                registry::action($action);
            }
            $unknown = placeholders::unknown($step['subject'] . ' ' . ($step['body'] ?? ''));
            if ($unknown) {
                throw new \moodle_exception('error_unknownplaceholder', 'local_remindstudio', '', implode(', ', $unknown));
            }
        }
    }

    /**
     * Insert or update a rule and its steps.
     *
     * @param int $id 0 to create.
     * @param array $data
     * @param array $steps
     * @return self
     */
    private static function save(int $id, array $data, array $steps): self {
        global $DB;
        self::validate($data, $steps);
        $now = time();
        $record = [
            'name' => $data['name'],
            'enabled' => $data['enabled'] ?? 1,
            'triggertype' => $data['triggertype'] ?? self::TRIGGER_SCAN,
            'audience' => json_encode(['roles' => array_values(array_map('intval', $data['roles'] ?? []))]),
            'conditions' => json_encode($data['conditions'] ?? ['op' => 'all', 'items' => []]),
            'exits' => json_encode($data['exits'] ?? []),
            'priority' => $data['priority'] ?? 0,
            'essential' => $data['essential'] ?? 0,
            'dryrununtil' => $data['dryrununtil'] ?? 0,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        if ($id) {
            $DB->update_record('local_remindstudio_rule', ['id' => $id] + $record);
        } else {
            $id = $DB->insert_record(
                'local_remindstudio_rule',
                $record + ['courseid' => $data['courseid'], 'timecreated' => $now]
            );
        }
        $existing = $DB->get_records_menu('local_remindstudio_step', ['ruleid' => $id], '', 'sortorder, id');
        foreach (array_values($steps) as $i => $step) {
            $row = [
                'ruleid' => $id,
                'sortorder' => $i + 1,
                'delay' => $step['delay'] ?? 0,
                'actions' => json_encode(array_values($step['actions'] ?? ['messagelearner'])),
                'subject' => $step['subject'],
                'body' => $step['body'] ?? '',
                'bodyformat' => $step['bodyformat'] ?? FORMAT_HTML,
            ];
            if (isset($existing[$i + 1])) {
                $DB->update_record('local_remindstudio_step', ['id' => $existing[$i + 1]] + $row);
            } else {
                $DB->insert_record('local_remindstudio_step', $row);
            }
        }
        $DB->delete_records_select('local_remindstudio_step', 'ruleid = ? AND sortorder > ?', [$id, count($steps)]);
        $transaction->allow_commit();
        return self::get($id);
    }

    /**
     * The rule as data and steps, as create() takes them.
     *
     * @return array [data, steps]
     */
    public function export(): array {
        $data = [
            'courseid' => $this->courseid,
            'name' => $this->name,
            'enabled' => (int) $this->enabled,
            'triggertype' => $this->triggertype,
            'roles' => $this->roles,
            'conditions' => $this->conditions,
            'exits' => $this->exits,
            'priority' => $this->priority,
            'essential' => (int) $this->essential,
            'dryrununtil' => $this->dryrununtil,
        ];
        $steps = array_map(fn(stdClass $step) => [
            'delay' => (int) $step->delay,
            'subject' => $step->subject,
            'body' => $step->body,
            'bodyformat' => (int) $step->bodyformat,
            'actions' => json_decode($step->actions, true) ?: [],
        ], $this->get_steps());
        return [$data, $steps];
    }

    /**
     * Turn the rule on or off.
     *
     * @param bool $enabled
     */
    public function set_enabled(bool $enabled): void {
        global $DB;
        $DB->update_record('local_remindstudio_rule', ['id' => $this->id, 'enabled' => (int) $enabled, 'timemodified' => time()]);
    }

    /**
     * All the rules of a course, highest priority first.
     *
     * @param int $courseid
     * @return self[]
     */
    public static function get_for_course(int $courseid): array {
        global $DB;
        $records = $DB->get_records('local_remindstudio_rule', ['courseid' => $courseid], 'priority DESC, id ASC');
        return array_map(fn(stdClass $record) => new self($record), array_values($records));
    }

    /**
     * Load a rule.
     *
     * @param int $id
     * @return self
     */
    public static function get(int $id): self {
        global $DB;
        return new self($DB->get_record('local_remindstudio_rule', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * The enabled rules of a course, highest priority first.
     *
     * @param int $courseid
     * @return self[]
     */
    public static function get_enabled_for_course(int $courseid): array {
        global $DB;
        $records = $DB->get_records('local_remindstudio_rule', ['courseid' => $courseid, 'enabled' => 1], 'priority DESC, id ASC');
        $rules = array_map(fn(stdClass $record) => new self($record), array_values($records));
        foreach (self::get_inherited($courseid)->get_rules() as $inherited) {
            if ($inherited->rule->enabled && $inherited->active) {
                $rules[] = $inherited->rule;
            }
        }
        usort($rules, fn(self $a, self $b) => [$b->priority, $a->id] <=> [$a->priority, $b->id]);
        return $rules;
    }

    /**
     * The site rules other plugins apply to a course.
     *
     * @param int $courseid
     * @return hook\course_rules
     */
    public static function get_inherited(int $courseid): hook\course_rules {
        $hook = new hook\course_rules($courseid);
        if ($courseid) {
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
        }
        return $hook;
    }

    /**
     * The same rule applied to a course: for site rules.
     *
     * @param int $courseid
     * @return self
     */
    public function with_course(int $courseid): self {
        $record = clone $this->record;
        $record->courseid = $courseid;
        return new self($record, true);
    }

    /**
     * The steps, in order.
     *
     * @return stdClass[] Indexed from 0.
     */
    public function get_steps(): array {
        global $DB;
        return array_values($DB->get_records('local_remindstudio_step', ['ruleid' => $this->id], 'sortorder ASC'));
    }

    /**
     * The role ids the rule is for.
     *
     * @return int[]
     */
    public function get_roles(): array {
        return $this->roles ?: array_map('intval', array_keys(get_archetype_roles('student')));
    }

    /**
     * Whether the rule only logs what it would send.
     *
     * @param int $now
     * @return bool
     */
    public function is_dryrun(int $now): bool {
        return $this->dryrununtil > $now;
    }

    /**
     * Delete the rule, its steps, where learners are in it and its log.
     */
    public function delete(): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        foreach (['local_remindstudio_log', 'local_remindstudio_track', 'local_remindstudio_step'] as $table) {
            $DB->delete_records($table, ['ruleid' => $this->id]);
        }
        $DB->delete_records('local_remindstudio_rule', ['id' => $this->id]);
        $transaction->allow_commit();
    }
}
