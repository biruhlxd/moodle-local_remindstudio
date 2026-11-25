@local @local_remindstudio @javascript
Feature: Teachers manage the reminder rules of a course
  In order to nudge learners at the right time
  As a teacher
  I need to add, turn off, delete and pause reminder rules

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Reminders" in current page administration

  Scenario: A teacher adds a rule from a recipe
    Then I should see "No reminder rules yet."
    And I set the field "Add a rule" to "Welcome"
    And the field "Rule" matches value "Welcome"
    And I press "Save changes"
    And I should see "Rule saved."
    And I should see "Welcome" in the "generaltable" "table"
    And I should not see "No reminder rules yet."

  Scenario: A teacher turns a rule off and deletes it
    Given I set the field "Add a rule" to "Welcome"
    And I press "Save changes"
    When I click on "Turn off" "link" in the "Welcome" "table_row"
    Then I should see "Off" in the "Welcome" "table_row"
    And I click on "Delete" "link" in the "Welcome" "table_row"
    And I should see "Delete the rule \"Welcome\"?"
    And I press "Continue"
    And I should see "No reminder rules yet."

  Scenario: A teacher pauses and resumes the reminders of a course
    When I press "Pause this course's reminders"
    Then I should see "Reminders in this course are paused."
    And I press "Resume this course's reminders"
    And I should not see "Reminders in this course are paused."

  Scenario: Learners do not see the reminders page
    Given I log out
    And I log in as "student1"
    When I am on "Course 1" course homepage
    Then "Reminders" "link" should not exist in the "region-main" "region"
