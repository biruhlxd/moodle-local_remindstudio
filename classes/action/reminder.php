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

namespace local_remindstudio\action;

use local_remindstudio\placeholders;
use stdClass;

/**
 * One reminder for one learner: who, where, and the step's message with the placeholders filled in.
 *
 * @package    local_remindstudio
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reminder {
    /**
     * Constructor.
     *
     * @param stdClass $user The learner.
     * @param stdClass $course
     * @param stdClass $step The step record.
     * @param string $subject Plain text.
     * @param string $html Formatted body.
     * @param string $text Plain text body.
     */
    public function __construct(
        /** @var stdClass The learner. */
        public readonly stdClass $user,
        /** @var stdClass The course. */
        public readonly stdClass $course,
        /** @var stdClass The step record. */
        public readonly stdClass $step,
        /** @var string Plain text subject. */
        public readonly string $subject,
        /** @var string Formatted body. */
        public readonly string $html,
        /** @var string Plain text body. */
        public readonly string $text,
    ) {
    }

    /**
     * Write the step's message for a learner, in their language.
     *
     * @param stdClass $step
     * @param stdClass $user
     * @param stdClass $course
     * @param stdClass|null $pace The learner's row in local_remindstudio_pace, if any.
     * @param int $now
     * @return self
     */
    public static function create(stdClass $step, stdClass $user, stdClass $course, ?stdClass $pace, int $now): self {
        $previous = force_current_language($user->lang);
        try {
            $context = \context_course::instance($course->id);
            $subject = placeholders::replace($step->subject, placeholders::values($user, $course, $pace, false, $now));
            $body = placeholders::replace((string) $step->body, placeholders::values($user, $course, $pace, true, $now));
            $html = format_text($body, $step->bodyformat, ['context' => $context]);
            return new self($user, $course, $step, $subject, $html, html_to_text($html));
        } finally {
            force_current_language($previous);
        }
    }
}
