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

namespace local_remindstudio\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_remindstudio\condition\base as condition;
use local_remindstudio\placeholders;
use local_remindstudio\registry;
use local_remindstudio\rule;

/**
 * The rule editor: who, when, the messages, and when a learner stops getting them.
 *
 * Each built-in condition has its own settings fields, shown only when that condition is chosen in a row.
 * Custom data: activities (cmid => name, tracked ones), roles (id => name), conditioncount, stepcount, dryrununtil.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends \moodleform {
    /** @var array Settings of each built-in condition and their kind of field. */
    public const PARAMS = [
        'neveraccessed' => [],
        'inactive' => ['days' => 'int'],
        'enrolleddays' => ['op' => 'op', 'days' => 'int'],
        'progress' => ['op' => 'op', 'value' => 'int'],
        'behindpace' => ['points' => 'int'],
        'deadline' => ['when' => 'when', 'days' => 'int'],
        'activitycompletion' => ['cmid' => 'cm', 'complete' => 'yesno'],
        'coursecompletion' => ['complete' => 'yesno'],
    ];

    /** @var array Default values of int fields. */
    private const DEFAULTS = ['days' => 7, 'value' => 50, 'points' => 20];

    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata;
        $str = fn(string $id) => get_string($id, 'local_remindstudio');

        $mform->addElement('header', 'general', $str('rulegeneral'));
        $mform->addElement('text', 'name', $str('rulename'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addElement('advcheckbox', 'enabled', $str('ruleenabled'));
        $mform->setDefault('enabled', 1);
        $mform->addElement('select', 'triggertype', $str('trigger'), [
            rule::TRIGGER_SCAN => $str('trigger_scan'),
            rule::TRIGGER_ENROLLED => $str('trigger_enrolled'),
            rule::TRIGGER_COURSECOMPLETED => $str('trigger_coursecompleted'),
        ]);
        $mform->addHelpButton('triggertype', 'trigger', 'local_remindstudio');
        $mform->addElement('autocomplete', 'roles', $str('roles'), $custom['roles'], ['multiple' => true]);
        $mform->addElement('select', 'priority', $str('priority'), array_combine(range(0, 10), range(0, 10)));
        $mform->addHelpButton('priority', 'priority', 'local_remindstudio');
        $mform->addElement('advcheckbox', 'essential', $str('essential'));
        $mform->addHelpButton('essential', 'essential', 'local_remindstudio');
        $dryrun = [0 => $str('dryrun_off')];
        if ($custom['dryrununtil'] > time()) {
            $dryrun[-1] = get_string('dryrun_keep', 'local_remindstudio', userdate($custom['dryrununtil']));
        }
        foreach ([1, 3, 7] as $days) {
            $dryrun[$days] = get_string('dryrun_days', 'local_remindstudio', $days);
        }
        $mform->addElement('select', 'dryrun', $str('dryrun'), $dryrun);
        $mform->addHelpButton('dryrun', 'dryrun', 'local_remindstudio');

        $mform->addElement('header', 'who', $str('rulewho'));
        $mform->addElement('select', 'match', $str('match'), ['all' => $str('match_all'), 'any' => $str('match_any')]);
        $this->add_conditions($custom);

        $mform->addElement('header', 'stop', $str('rulestop'));
        $mform->addElement('advcheckbox', 'exit_accessed', $str('exit_accessed'));
        $mform->addElement('advcheckbox', 'exit_coursecompleted', $str('exit_coursecompleted'));
        $mform->addElement('select', 'exit_cmid', $str('exit_cmid'), [0 => get_string('none')] + $custom['activities']);

        $mform->addElement('header', 'messages', $str('rulemessages'));
        $mform->addElement(
            'static',
            'placeholders',
            $str('placeholders'),
            implode(' ', array_map(fn($name) => '{' . $name . '}', placeholders::NAMES))
        );
        $mform->addHelpButton('placeholders', 'placeholders', 'local_remindstudio');
        $this->add_steps($custom);

        $this->add_action_buttons();
    }

    /**
     * The repeated condition rows.
     *
     * @param array $custom
     */
    private function add_conditions(array $custom): void {
        $mform = $this->_form;
        $types = ['' => get_string('choosedots')];
        foreach (array_keys(self::PARAMS) as $type) {
            $types[$type] = registry::condition($type)->get_name();
        }
        $elements = [$mform->createElement('select', 'ctype', get_string('conditionno', 'local_remindstudio'), $types)];
        $options = ['ctype' => ['type' => PARAM_ALPHANUMEXT]];
        foreach (self::PARAMS as $type => $params) {
            foreach ($params as $key => $kind) {
                $name = "c_{$type}_{$key}";
                $label = get_string('param_' . $key, 'local_remindstudio');
                $elements[] = match ($kind) {
                    'int' => $mform->createElement('text', $name, $label, ['size' => 4]),
                    'op' => $mform->createElement('select', $name, $label, condition::operators()),
                    'when' => $mform->createElement('select', $name, $label, [
                        'before' => get_string('when_before', 'local_remindstudio'),
                        'after' => get_string('when_after', 'local_remindstudio'),
                    ]),
                    'cm' => $mform->createElement('select', $name, $label, $custom['activities']),
                    'yesno' => $mform->createElement('select', $name, $label, [0 => get_string('no'), 1 => get_string('yes')]),
                };
                $options[$name] = [
                    'type' => in_array($kind, ['op', 'when'], true) ? PARAM_RAW_TRIMMED : PARAM_INT,
                    'hideif' => ['ctype', 'neq', $type],
                ];
                if (isset(self::DEFAULTS[$key])) {
                    $options[$name]['default'] = self::DEFAULTS[$key];
                }
            }
        }
        $this->repeat_elements(
            $elements,
            $custom['conditioncount'],
            $options,
            'conditioncount',
            'addcondition',
            1,
            get_string('addcondition', 'local_remindstudio'),
            true
        );
    }

    /**
     * The repeated steps.
     *
     * @param array $custom
     */
    private function add_steps(array $custom): void {
        $mform = $this->_form;
        $str = fn(string $id) => get_string($id, 'local_remindstudio');
        $elements = [
            $mform->createElement('static', 'stephead', $str('stepno'), ''),
            $mform->createElement('duration', 'sdelay', $str('stepdelay'), ['defaultunit' => DAYSECS]),
            $mform->createElement('text', 'ssubject', $str('stepsubject'), ['size' => 60]),
            $mform->createElement('editor', 'sbody', $str('stepbody'), ['rows' => 6]),
        ];
        foreach (registry::actions() as $type => $action) {
            $elements[] = $mform->createElement('advcheckbox', 'saction_' . $type, '', $action->get_name());
        }
        $options = [
            'sdelay' => ['default' => 0],
            'ssubject' => ['type' => PARAM_TEXT],
            'saction_messagelearner' => ['default' => 1],
        ];
        $this->repeat_elements(
            $elements,
            $custom['stepcount'],
            $options,
            'stepcount',
            'addstep',
            1,
            $str('addstep'),
            true
        );
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach ($data['ctype'] ?? [] as $i => $type) {
            if ($type !== '') {
                try {
                    registry::condition($type)->validate($this->condition_config($data, $i, $type));
                } catch (\moodle_exception $e) {
                    $errors["ctype[$i]"] = get_string('error_conditionsettings', 'local_remindstudio');
                }
            }
        }
        $steps = 0;
        foreach ($data['ssubject'] ?? [] as $i => $subject) {
            if (trim($subject) === '') {
                continue;
            }
            $steps++;
            $unknown = placeholders::unknown($subject . ' ' . ($data['sbody'][$i]['text'] ?? ''));
            if ($unknown) {
                $errors["ssubject[$i]"] = get_string('error_unknownplaceholder', 'local_remindstudio', implode(', ', $unknown));
            }
            if (!self::step_actions($data, $i)) {
                $errors["ssubject[$i]"] = get_string('error_noaction', 'local_remindstudio');
            }
        }
        if (!$steps) {
            $errors['ssubject[0]'] = get_string('error_nosteps', 'local_remindstudio');
        } else if ($steps > rule::MAX_STEPS) {
            $errors['ssubject[0]'] = get_string('error_toomanysteps', 'local_remindstudio', rule::MAX_STEPS);
        }
        return $errors;
    }

    /**
     * The rule data and steps from the submitted form, as rule::create() takes them.
     *
     * @param \stdClass $formdata
     * @param int $courseid
     * @return array [data, steps]
     */
    public function to_rule(\stdClass $formdata, int $courseid): array {
        $data = (array) $formdata;
        $items = [];
        foreach ($data['ctype'] ?? [] as $i => $type) {
            if ($type !== '') {
                $items[] = ['type' => $type] + $this->condition_config($data, $i, $type);
            }
        }
        $dryrun = (int) $data['dryrun'];
        $steps = [];
        foreach ($data['ssubject'] ?? [] as $i => $subject) {
            if (trim($subject) === '') {
                continue;
            }
            $steps[] = [
                'delay' => (int) $data['sdelay'][$i],
                'subject' => trim($subject),
                'body' => $data['sbody'][$i]['text'],
                'bodyformat' => (int) $data['sbody'][$i]['format'],
                'actions' => self::step_actions($data, $i),
            ];
        }
        return [[
            'courseid' => $courseid,
            'name' => trim($data['name']),
            'enabled' => (int) $data['enabled'],
            'triggertype' => $data['triggertype'],
            'roles' => $data['roles'] ?? [],
            'conditions' => ['op' => $data['match'], 'items' => $items],
            'exits' => array_filter([
                'accessed' => (int) $data['exit_accessed'],
                'coursecompleted' => (int) $data['exit_coursecompleted'],
                'cmid' => (int) $data['exit_cmid'],
            ]),
            'priority' => (int) $data['priority'],
            'essential' => (int) $data['essential'],
            'dryrununtil' => $dryrun < 0 ? $this->_customdata['dryrununtil'] : ($dryrun ? time() + $dryrun * DAYSECS : 0),
        ], $steps];
    }

    /**
     * Form values from rule data and steps, for set_data().
     *
     * @param array $data As rule::create() takes it.
     * @param array $steps As rule::create() takes them.
     * @return array
     */
    public static function to_formdata(array $data, array $steps): array {
        $form = [
            'name' => $data['name'],
            'enabled' => $data['enabled'] ?? 1,
            'triggertype' => $data['triggertype'] ?? rule::TRIGGER_SCAN,
            'roles' => $data['roles'] ?? [],
            'priority' => $data['priority'] ?? 0,
            'essential' => $data['essential'] ?? 0,
            'dryrun' => ($data['dryrununtil'] ?? 0) > time() ? -1 : 0,
            'match' => $data['conditions']['op'] ?? 'all',
            'exit_accessed' => $data['exits']['accessed'] ?? 0,
            'exit_coursecompleted' => $data['exits']['coursecompleted'] ?? 0,
            'exit_cmid' => $data['exits']['cmid'] ?? 0,
        ];
        foreach (self::form_conditions($data['conditions'] ?? []) as $i => $node) {
            $form['ctype'][$i] = $node['type'];
            foreach (self::PARAMS[$node['type']] as $key => $kind) {
                $form["c_{$node['type']}_{$key}"][$i] = $node[$key] ?? '';
            }
        }
        foreach (array_values($steps) as $i => $step) {
            $form['sdelay'][$i] = $step['delay'] ?? 0;
            $form['ssubject'][$i] = $step['subject'];
            $form['sbody'][$i] = ['text' => $step['body'] ?? '', 'format' => $step['bodyformat'] ?? FORMAT_HTML];
            $actions = $step['actions'] ?? ['messagelearner'];
            foreach (array_keys(registry::actions()) as $type) {
                $form['saction_' . $type][$i] = (int) in_array($type, $actions);
            }
        }
        return $form;
    }

    /**
     * The actions ticked in a step.
     *
     * @param array $data Submitted values.
     * @param int $i Step row.
     * @return string[]
     */
    private static function step_actions(array $data, int $i): array {
        $actions = [];
        foreach (array_keys(registry::actions()) as $type) {
            if (!empty($data['saction_' . $type][$i])) {
                $actions[] = $type;
            }
        }
        return $actions;
    }

    /**
     * The conditions the form can show: the built-in ones at the top level of the tree.
     *
     * @param array $tree
     * @return array[]
     */
    public static function form_conditions(array $tree): array {
        return array_values(array_filter(
            $tree['items'] ?? [],
            fn($node) => isset($node['type'], self::PARAMS[$node['type']])
        ));
    }

    /**
     * The settings of the condition in a row.
     *
     * @param array $data Submitted values.
     * @param int $i Row.
     * @param string $type
     * @return array
     */
    private function condition_config(array $data, int $i, string $type): array {
        $config = [];
        foreach (self::PARAMS[$type] ?? [] as $key => $kind) {
            $value = $data["c_{$type}_{$key}"][$i] ?? '';
            $config[$key] = in_array($kind, ['op', 'when'], true) ? (string) $value : (int) $value;
        }
        return $config;
    }
}
