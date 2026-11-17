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

namespace local_remindstudio\engine;

use local_remindstudio\condition\sql_context;
use local_remindstudio\condition\tree;
use local_remindstudio\optout;
use local_remindstudio\pace\calculator;
use local_remindstudio\pace\course_settings;
use local_remindstudio\rule;
use local_remindstudio\task\send_batch;
use stdClass;

/**
 * Checks rules and queues the reminders that are due.
 *
 * Each learner has a track per rule: which step comes next and when. A scan, per rule:
 * 1. finds the learners who match (audience and conditions) in one query;
 * 2. clears the tracks of learners who no longer match, so they can start again later if they match again;
 * 3. stops the tracks of learners who met an exit condition;
 * 4. starts tracks for learners who match and have none (scan rules only; other rules start on an event);
 * 5. queues the steps that are due, within the sending limits and the send window, and moves the tracks on.
 * A step is logged once per track (unique index), so running a scan twice never sends twice.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scanner {
    /** @var int Reminders sent per batch task. */
    public const BATCH_SIZE = 200;

    /**
     * Constructor.
     *
     * @param int $now
     */
    public function __construct(
        /** @var int The time to scan at. */
        private readonly int $now,
    ) {
    }

    /**
     * Scan every course with enabled rules, starting after the course scanned last, until the time limit.
     *
     * @param int $timelimit Seconds, 0 for none.
     */
    public function scan_all(int $timelimit = 0): void {
        global $DB;
        if (!get_config('local_remindstudio', 'enabled')) {
            return;
        }
        $courseids = $DB->get_fieldset_sql("
            SELECT DISTINCT r.courseid
              FROM {local_remindstudio_rule} r
              JOIN {course} c ON c.id = r.courseid
             WHERE r.enabled = 1 AND c.id <> :siteid
          ORDER BY r.courseid", ['siteid' => SITEID]);
        $cursor = (int) get_config('local_remindstudio', 'scancursor');
        $after = array_filter($courseids, fn($id) => $id > $cursor);
        $started = time();
        foreach (array_merge($after, array_diff($courseids, $after)) as $courseid) {
            $this->scan_course((int) $courseid);
            set_config('scancursor', $courseid, 'local_remindstudio');
            if ($timelimit && time() - $started >= $timelimit) {
                break;
            }
        }
    }

    /**
     * Scan the enabled rules of a course and queue the reminders that are due.
     *
     * @param int $courseid
     * @return int[] Ids of the log rows queued for sending.
     */
    public function scan_course(int $courseid): array {
        if (course_settings::get($courseid)->paused) {
            return [];
        }
        (new calculator($this->now))->update_if_stale($courseid);
        $logids = [];
        foreach (rule::get_enabled_for_course($courseid) as $rule) {
            $logids = array_merge($logids, $this->scan_rule($rule));
        }
        foreach (array_chunk($logids, self::BATCH_SIZE) as $chunk) {
            send_batch::queue($chunk);
        }
        return $logids;
    }

    /**
     * Start a learner on a rule that starts on an event, unless they are already on it.
     *
     * The next scan checks the audience and conditions before the first step is sent.
     *
     * @param rule $rule
     * @param int $userid
     */
    public function start_for_event(rule $rule, int $userid): void {
        global $DB;
        $steps = $rule->get_steps();
        if (!$steps) {
            return;
        }
        $where = ['ruleid' => $rule->id, 'courseid' => $rule->courseid, 'userid' => $userid];
        if (
            $DB->record_exists_select(
                'local_remindstudio_track',
                'ruleid = :ruleid AND courseid = :courseid AND userid = :userid AND status <> :cleared',
                $where + ['cleared' => 'cleared']
            )
        ) {
            return;
        }
        $last = (int) $DB->get_field('local_remindstudio_track', 'MAX(occurrence)', $where);
        $this->start_track($rule, $userid, $last + 1, $steps[0]);
    }

    /**
     * Scan one rule.
     *
     * @param rule $rule
     * @return int[] Ids of the log rows queued for sending.
     */
    private function scan_rule(rule $rule): array {
        global $DB;
        $steps = $rule->get_steps();
        if (!$steps) {
            return [];
        }
        $where = ['ruleid' => $rule->id, 'courseid' => $rule->courseid];
        if (!$rule->is_dryrun($this->now)) {
            // The dry run is over: everyone starts afresh.
            $DB->delete_records('local_remindstudio_track', $where + ['dryrun' => 1]);
        }

        $matching = $this->matching_users($rule);
        $exited = $this->exited_tracks($rule);
        $tracks = $DB->get_records_select(
            'local_remindstudio_track',
            'ruleid = :ruleid AND courseid = :courseid AND status <> :cleared',
            $where + ['cleared' => 'cleared'],
            'id'
        );
        $due = [];
        $ontrack = [];
        foreach ($tracks as $track) {
            $ontrack[$track->userid] = true;
            if (!isset($matching[$track->userid])) {
                $DB->set_field('local_remindstudio_track', 'status', 'cleared', ['id' => $track->id]);
            } else if ($track->status !== 'active') {
                continue;
            } else if (isset($exited[$track->id])) {
                $DB->set_field('local_remindstudio_track', 'status', 'exited', ['id' => $track->id]);
            } else if ($track->timenextdue <= $this->now) {
                $due[] = $track;
            }
        }

        if ($rule->triggertype === rule::TRIGGER_SCAN) {
            $last = $DB->get_records_sql_menu('
                SELECT userid, MAX(occurrence)
                  FROM {local_remindstudio_track}
                 WHERE ruleid = :ruleid AND courseid = :courseid
              GROUP BY userid', $where);
            foreach ($matching as $userid) {
                if (!isset($ontrack[$userid])) {
                    $track = $this->start_track($rule, $userid, (int) ($last[$userid] ?? 0) + 1, $steps[0]);
                    if ($track->timenextdue <= $this->now) {
                        $due[] = $track;
                    }
                }
            }
        }
        return $this->dispatch($rule, $steps, $due);
    }

    /**
     * Put a learner at the first step of a rule.
     *
     * @param rule $rule
     * @param int $userid
     * @param int $occurrence
     * @param stdClass $firststep
     * @return stdClass The track.
     */
    private function start_track(rule $rule, int $userid, int $occurrence, stdClass $firststep): stdClass {
        global $DB;
        $track = (object) [
            'ruleid' => $rule->id,
            'courseid' => $rule->courseid,
            'userid' => $userid,
            'occurrence' => $occurrence,
            'nextstep' => 1,
            'timenextdue' => $this->now + (int) $firststep->delay,
            'timestarted' => $this->now,
            'timelaststep' => 0,
            'status' => 'active',
            'dryrun' => (int) $rule->is_dryrun($this->now),
            'waitreason' => null,
        ];
        $track->id = $DB->insert_record('local_remindstudio_track', $track);
        return $track;
    }

    /**
     * The learners the rule is for who match its conditions now.
     *
     * @param rule $rule
     * @return int[] User ids, keyed by user id.
     */
    public function matching_users(rule $rule): array {
        global $DB;
        $context = \context_course::instance($rule->courseid);
        [$esql, $params] = get_enrolled_sql($context, '', 0, true);
        [$rolesql, $roleparams] = $DB->get_in_or_equal($rule->get_roles(), SQL_PARAMS_NAMED, 'rsrole');
        $ctx = new sql_context($rule->courseid, $this->now);
        $conditions = tree::to_sql($rule->conditions, $ctx);
        $optout = $rule->essential ? '' : 'AND NOT ' . optout::sql($ctx->param($rule->courseid), $ctx->param($this->now));
        $ids = $DB->get_fieldset_sql(
            "
            SELECT u.id
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
         LEFT JOIN {local_remindstudio_pace} p ON p.userid = u.id AND p.courseid = :rspacecourse
             WHERE u.deleted = 0 AND u.suspended = 0
                   AND EXISTS (SELECT 1 FROM {role_assignments} ra
                                WHERE ra.userid = u.id AND ra.contextid = :rscontext AND ra.roleid $rolesql)
                   AND $conditions $optout",
            $params + $roleparams + $ctx->params + ['rspacecourse' => $rule->courseid, 'rscontext' => $context->id]
        );
        return $ids ? array_combine($ids, array_map('intval', $ids)) : [];
    }

    /**
     * The active tracks whose learner met an exit condition.
     *
     * @param rule $rule
     * @return true[] By track id.
     */
    private function exited_tracks(rule $rule): array {
        global $DB;
        $parts = [];
        $params = ['ruleid' => $rule->id, 'courseid' => $rule->courseid, 'active' => 'active'];
        if (!empty($rule->exits['accessed'])) {
            // Opened the course after the last reminder.
            $parts[] = 't.timelaststep > 0 AND EXISTS (SELECT 1 FROM {user_lastaccess} ula
                        WHERE ula.userid = t.userid AND ula.courseid = t.courseid AND ula.timeaccess > t.timelaststep)';
        }
        if (!empty($rule->exits['coursecompleted'])) {
            $parts[] = 'EXISTS (SELECT 1 FROM {course_completions} cc
                        WHERE cc.userid = t.userid AND cc.course = t.courseid AND cc.timecompleted > 0)';
        }
        if (!empty($rule->exits['cmid'])) {
            // Completion states 1 and 2 are COMPLETION_COMPLETE and COMPLETION_COMPLETE_PASS.
            $parts[] = 'EXISTS (SELECT 1 FROM {course_modules_completion} cmc
                        WHERE cmc.userid = t.userid AND cmc.coursemoduleid = :exitcmid AND cmc.completionstate IN (1, 2))';
            $params['exitcmid'] = (int) $rule->exits['cmid'];
        }
        if (!$parts) {
            return [];
        }
        $ids = $DB->get_fieldset_sql('
            SELECT t.id
              FROM {local_remindstudio_track} t
             WHERE t.ruleid = :ruleid AND t.courseid = :courseid AND t.status = :active
                   AND ((' . implode(') OR (', $parts) . '))', $params);
        return array_fill_keys($ids, true);
    }

    /**
     * Queue the due steps that the limits and send window allow, and move the tracks on.
     *
     * @param rule $rule
     * @param stdClass[] $steps
     * @param stdClass[] $due Tracks.
     * @return int[] Ids of the log rows queued for sending.
     */
    private function dispatch(rule $rule, array $steps, array $due): array {
        global $DB;
        if (!$due) {
            return [];
        }
        $dryrun = $rule->is_dryrun($this->now);
        $userids = array_unique(array_column($due, 'userid'));
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id, timezone');
        $counts = $dryrun ? [] : $this->sent_counts($rule->courseid, $userids);
        $config = get_config('local_remindstudio');
        $logids = [];
        foreach ($due as $track) {
            $step = $steps[$track->nextstep - 1] ?? null;
            if (!$step) {
                $DB->set_field('local_remindstudio_track', 'status', 'done', ['id' => $track->id]);
                continue;
            }
            $count = $counts[$track->userid] ?? (object) ['incourse' => 0, 'onsite' => 0];
            $reason = $dryrun ? null : $this->wait_reason($users[$track->userid], $count, $config);
            if ($reason) {
                if ($track->waitreason !== $reason) {
                    $DB->set_field('local_remindstudio_track', 'waitreason', $reason, ['id' => $track->id]);
                }
                continue;
            }

            $next = $steps[$track->nextstep] ?? null;
            $transaction = $DB->start_delegated_transaction();
            $logid = $DB->insert_record('local_remindstudio_log', [
                'ruleid' => $rule->id,
                'stepid' => $step->id,
                'trackid' => $track->id,
                'courseid' => $rule->courseid,
                'userid' => $track->userid,
                'status' => $dryrun ? 'dryrun' : 'queued',
                'timecreated' => $this->now,
                'timesent' => 0,
            ]);
            $DB->update_record('local_remindstudio_track', [
                'id' => $track->id,
                'nextstep' => $track->nextstep + 1,
                'timenextdue' => $next ? $this->now + (int) $next->delay : 0,
                'timelaststep' => $this->now,
                'status' => $next ? 'active' : 'done',
                'waitreason' => null,
            ]);
            $transaction->allow_commit();
            if (!$dryrun) {
                $logids[] = (int) $logid;
                $count->incourse++;
                $count->onsite++;
                $counts[$track->userid] = $count;
            }
        }
        return $logids;
    }

    /**
     * Reminders each learner was sent in the last 24 hours, in this course and on the site.
     *
     * @param int $courseid
     * @param int[] $userids
     * @return stdClass[] incourse and onsite, by user id.
     */
    private function sent_counts(int $courseid, array $userids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        return $DB->get_records_sql("
            SELECT userid, SUM(CASE WHEN courseid = :courseid THEN 1 ELSE 0 END) AS incourse, COUNT(1) AS onsite
              FROM {local_remindstudio_log}
             WHERE userid $insql AND status IN ('queued', 'sent') AND timecreated > :since
          GROUP BY userid", $params + ['courseid' => $courseid, 'since' => $this->now - DAYSECS]);
    }

    /**
     * Why a reminder has to wait, if it does.
     *
     * @param stdClass $user id and timezone.
     * @param stdClass $count From sent_counts().
     * @param stdClass $config The plugin settings.
     * @return string|null capcourse, capsite or quiethours.
     */
    private function wait_reason(stdClass $user, stdClass $count, stdClass $config): ?string {
        if (!empty($config->maxpercourse) && $count->incourse >= $config->maxpercourse) {
            return 'capcourse';
        }
        if (!empty($config->maxpersite) && $count->onsite >= $config->maxpersite) {
            return 'capsite';
        }
        $start = (int) ($config->sendstart ?? 0);
        $end = (int) ($config->sendend ?? 0);
        if ($start !== $end) {
            $time = new \DateTime('@' . $this->now);
            $time->setTimezone(\core_date::get_user_timezone_object($user));
            $hour = (int) $time->format('G');
            $inside = $start < $end ? $hour >= $start && $hour < $end : $hour >= $start || $hour < $end;
            if (!$inside) {
                return 'quiethours';
            }
        }
        return null;
    }
}
