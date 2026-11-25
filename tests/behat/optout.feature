@local @local_remindstudio
Feature: Learners snooze or stop the reminders of a course
  In order to get fewer messages
  As a learner
  I need to snooze or stop a course's reminders from the link in a reminder

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: A learner snoozes and turns reminders back on without logging in
    When I open the reminder opt-out link of "student1" for "C1"
    Then I should see "You get reminders from Course 1."
    And I press "Snooze for a week"
    And I should see "Your choice is saved."
    And I should see "You have snoozed the reminders of Course 1 until"
    And I press "Turn reminders back on"
    And I should see "You get reminders from Course 1."

  Scenario: A learner stops the reminders of a course
    When I open the reminder opt-out link of "student1" for "C1"
    And I press "Stop these reminders"
    Then I should see "You have stopped the reminders of Course 1."
    And I should see "Essential reminders, such as compliance deadlines, are still sent."
    And I should see "Turn reminders back on"
