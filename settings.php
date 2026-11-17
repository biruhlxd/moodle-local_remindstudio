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

/**
 * Site settings for Reminder Studio.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_remindstudio',
        new lang_string('pluginname', 'local_remindstudio'),
        'local/remindstudio:managesite'
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_remindstudio/enabled',
        new lang_string('enabled', 'local_remindstudio'),
        new lang_string('enabled_desc', 'local_remindstudio'),
        1
    ));

    $hours = [];
    for ($h = 0; $h < 24; $h++) {
        $hours[$h] = sprintf('%02d:00', $h);
    }
    $settings->add(new admin_setting_configselect(
        'local_remindstudio/sendstart',
        new lang_string('sendstart', 'local_remindstudio'),
        new lang_string('sendstart_desc', 'local_remindstudio'),
        8,
        $hours
    ));
    $settings->add(new admin_setting_configselect(
        'local_remindstudio/sendend',
        new lang_string('sendend', 'local_remindstudio'),
        new lang_string('sendend_desc', 'local_remindstudio'),
        20,
        $hours
    ));

    $settings->add(new admin_setting_configtext(
        'local_remindstudio/maxpercourse',
        new lang_string('maxpercourse', 'local_remindstudio'),
        new lang_string('maxpercourse_desc', 'local_remindstudio'),
        1,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_remindstudio/maxpersite',
        new lang_string('maxpersite', 'local_remindstudio'),
        new lang_string('maxpersite_desc', 'local_remindstudio'),
        3,
        PARAM_INT
    ));

    $modes = [];
    foreach (['auto', 'open', 'selfpaced', 'fixed'] as $mode) {
        $modes[$mode] = new lang_string('pacing_' . $mode, 'local_remindstudio');
    }
    $settings->add(new admin_setting_configselect(
        'local_remindstudio/defaultpacing',
        new lang_string('defaultpacing', 'local_remindstudio'),
        new lang_string('defaultpacing_desc', 'local_remindstudio'),
        'auto',
        $modes
    ));

    $settings->add(new admin_setting_configtext(
        'local_remindstudio/logretention',
        new lang_string('logretention', 'local_remindstudio'),
        new lang_string('logretention_desc', 'local_remindstudio'),
        365,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
