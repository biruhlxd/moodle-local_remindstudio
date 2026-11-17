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
 * English strings for local_remindstudio.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_copyteachers'] = 'Send a copy to the teachers';
$string['action_messagelearner'] = 'Send to the learner';
$string['condition_activitycompletion'] = 'Activity completion';
$string['condition_behindpace'] = 'Behind pace';
$string['condition_coursecompletion'] = 'Course completion';
$string['condition_deadline'] = 'Finish date';
$string['condition_enrolleddays'] = 'Days since enrolment';
$string['condition_inactive'] = 'Inactive';
$string['condition_neveraccessed'] = 'Never opened the course';
$string['condition_progress'] = 'Progress';
$string['copyintro'] = 'This is a copy of a reminder sent to {$a}.';
$string['copysubject'] = '{$a->learner}: {$a->subject}';
$string['defaultpacing'] = 'Default pacing';
$string['defaultpacing_desc'] = 'How courses are paced unless a course sets its own. "Automatic" uses fixed dates when the course has an end date and no deadline otherwise.';
$string['describe_activitycomplete'] = 'have completed {$a}';
$string['describe_activitynotcomplete'] = 'have not completed {$a}';
$string['describe_behindpace'] = 'are at least {$a} percentage points behind where they should be';
$string['describe_coursecomplete'] = 'have completed the course';
$string['describe_coursenotcomplete'] = 'have not completed the course';
$string['describe_deadlineafter'] = 'passed their finish date at least {$a} days ago';
$string['describe_deadlinebefore'] = 'have their finish date within {$a} days';
$string['describe_enrolleddays'] = 'were enrolled {$a->op} {$a->days} days ago';
$string['describe_inactive'] = 'have not opened the course for {$a} days';
$string['describe_neveraccessed'] = 'have never opened the course';
$string['describe_progress'] = 'have progress {$a->op} {$a->value}%';
$string['enabled'] = 'Send reminders';
$string['enabled_desc'] = 'Turn off to stop all reminders on the site at once. Rules are kept.';
$string['error_sendfailed'] = 'The message could not be sent.';
$string['error_unknownaction'] = 'Unknown action: {$a}';
$string['error_unknowncondition'] = 'Unknown condition: {$a}';
$string['error_unknownplaceholder'] = 'Unknown placeholder: {$a}';
$string['event_remindersent'] = 'Reminder sent';
$string['logretention'] = 'Keep the log for (days)';
$string['logretention_desc'] = 'Records of sent reminders older than this are deleted. 0 keeps them for ever.';
$string['maxpercourse'] = 'Reminders per learner per course per day';
$string['maxpercourse_desc'] = 'When more rules are due, the one with the highest priority goes first and the others wait. 0 means no limit.';
$string['maxpersite'] = 'Reminders per learner per day on the site';
$string['maxpersite_desc'] = 'Across all courses. 0 means no limit.';
$string['messageprovider:reminder'] = 'Reminders about your courses';
$string['messageprovider:remindercopy'] = 'Copies of reminders sent to learners';
$string['missingactivity'] = 'an activity that no longer exists';
$string['notknown'] = 'not known';
$string['pacing_auto'] = 'Automatic';
$string['pacing_fixed'] = 'Fixed dates';
$string['pacing_open'] = 'No deadline';
$string['pacing_selfpaced'] = 'Self-paced';
$string['pluginname'] = 'Reminder Studio';
$string['privacy:metadata:core_message'] = 'Reminders are sent as Moodle notifications.';
$string['privacy:metadata:log'] = 'The reminders sent to each learner.';
$string['privacy:metadata:log:status'] = 'Whether the reminder was sent.';
$string['privacy:metadata:log:timesent'] = 'When the reminder was sent.';
$string['privacy:metadata:log:userid'] = 'The learner.';
$string['privacy:metadata:override'] = 'Finish dates given to individual learners (extensions).';
$string['privacy:metadata:override:expectedend'] = 'The learner\'s finish date.';
$string['privacy:metadata:override:note'] = 'A note about the extension.';
$string['privacy:metadata:override:userid'] = 'The learner.';
$string['privacy:metadata:pace'] = 'Each learner\'s dates and progress in a course.';
$string['privacy:metadata:pace:expectedend'] = 'When the learner is expected to finish.';
$string['privacy:metadata:pace:expectedpct'] = 'The progress the learner should have made by now.';
$string['privacy:metadata:pace:progresspct'] = 'The learner\'s progress.';
$string['privacy:metadata:pace:timeenrolled'] = 'When the learner was enrolled.';
$string['privacy:metadata:pace:userid'] = 'The learner.';
$string['privacy:metadata:track'] = 'Where each learner is in each reminder rule.';
$string['privacy:metadata:track:nextstep'] = 'The next reminder of the rule.';
$string['privacy:metadata:track:status'] = 'Whether the learner is still in the rule.';
$string['privacy:metadata:track:userid'] = 'The learner.';
$string['reason_gone'] = 'The course or the rule no longer exists.';
$string['reason_userinactive'] = 'The learner\'s account is suspended or deleted.';
$string['remindstudio:managecourse'] = 'Manage reminder rules in a course';
$string['remindstudio:managesite'] = 'Manage Reminder Studio for the site';
$string['remindstudio:receivecopy'] = 'Receive copies of reminders sent to learners';
$string['remindstudio:viewcourse'] = 'See reminders and learners\' pace in a course';
$string['sendend'] = 'Send reminders until';
$string['sendend_desc'] = 'In each learner\'s time zone. Reminders due later wait until the next morning. Make it the same as the start to send at any time.';
$string['sendstart'] = 'Send reminders from';
$string['sendstart_desc'] = 'In each learner\'s time zone.';
$string['task_cleanuplog'] = 'Delete old Reminder Studio records';
$string['task_scanrules'] = 'Check reminder rules';
$string['task_sendbatch'] = 'Send reminders';
