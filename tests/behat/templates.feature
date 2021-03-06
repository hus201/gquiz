@mod @mod_gquiz
Feature: Saving, using and deleting gquiz templates
  In order to quickly create gquizs
  As a manager
  I need to be able to create gquiz templates

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher  | Teacher   | 1        |
      | manager  | Manager   | 1        |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | teacher  | C2     | editingteacher |
    And the following "system role assigns" exist:
      | user    | course               | role    |
      | manager | Acceptance test site | manager |
    And the following "activities" exist:
      | activity   | name                         | course | idnumber    |
      | gquiz   | Learning experience course 1 | C1     | gquiz1   |
      | gquiz   | Another gquiz in course 1 | C1     | gquiz2   |
      | gquiz   | Learning experience course 2 | C2     | gquiz3   |
    And I am on the "Learning experience course 1" "gquiz activity" page logged in as teacher
    And I click on "Edit questions" "link" in the "[role=main]" "css_element"
    And I add a "Multiple choice" question to the gquiz with:
      | Question         | this is a multiple choice 1 |
      | Label            | multichoice1                |
      | Multiple choice type | Multiple choice - single answer |
      | Multiple choice values | option a\noption b\noption c  |
    And I log out

  Scenario: Teacher can save template and re-use it in the same course only
    # Go to gquiz templates and make sure none exist yet
    When I am on the "Learning experience course 1" "gquiz activity" page logged in as teacher
    And I follow "Templates"
    Then I should see "No templates available yet"
    And "Use a template" "field" should not exist
    And "Public" "field" should not exist
    And I follow "Delete template..."
    And "No templates available yet" "text" should exist in the ".coursetemplates" "css_element"
    And ".publictemplates" "css_element" should not exist
    And I press "Back"
    # Save as a course template
    And I set the field "Name" to "My first template"
    And I press "Save as new template"
    And I should see "Template saved"
    And the "Use a template" select box should contain "My first template"
    # Create a gquiz from this template in the same course
    And I am on the "Another gquiz in course 1" "gquiz activity" page
    And I follow "Templates"
    And I set the field "Use a template" to "My first template"
    And I press "Use this template"
    And I should see "this is a multiple choice 1"
    And I press "Save changes"
    And I follow "Edit questions"
    And I should see "this is a multiple choice 1"
    # Make sure this template is not available in another course
    And I am on the "Learning experience course 2" "gquiz activity" page
    And I follow "Templates"
    And "Use a template" "field" should not exist
    And I should see "No templates available yet"

  Scenario: Teacher can append template to existing questions or remove them
    # Save gquiz as a course template
    When I am on the "Learning experience course 1" "gquiz activity" page logged in as teacher
    And I follow "Templates"
    And I set the field "Name" to "My first template"
    And I press "Save as new template"
    # Add questions to another gquiz
    And I am on the "Another gquiz in course 1" "gquiz activity" page
    And I click on "Edit questions" "link" in the "[role=main]" "css_element"
    And I add a "Multiple choice" question to the gquiz with:
      | Question         | What is your favourite subject |
      | Label            | subjectchoice                  |
      | Multiple choice type | Multiple choice - single answer   |
      | Multiple choice values | Maths\bScience\nEnglish\nOther  |
    # Import template appending items
    And I follow "Templates"
    And I set the field "Use a template" to "My first template"
    And I press "Use this template"
    And I set the field "Append new items" to "1"
    And I press "Save changes"
    And I follow "Edit questions"
    Then "What is your favourite subject" "text" should appear before "this is a multiple choice 1" "text"
    # Import template replacing items
    And I follow "Templates"
    And I set the field "Use a template" to "My first template"
    And I press "Use this template"
    And I set the field "Delete old items" to "1"
    And I press "Save changes"
    And I follow "Edit questions"
    And I should not see "What is your favourite subject"
    And I should see "this is a multiple choice 1"

  Scenario: Manager can save template as public and it will be available in any course
    When I am on the "Learning experience course 1" "gquiz activity" page logged in as manager
    And I follow "Templates"
    And I set the field "Name" to "My first template"
    And I set the field "Public" to "1"
    And I press "Save as new template"
    And I log out
    And I am on the "Learning experience course 2" "gquiz activity" page logged in as teacher
    And I follow "Templates"
    And I set the field "Use a template" to "My first template"
    And I press "Use this template"
    Then I should see "this is a multiple choice 1"
    And I press "Save changes"
    And I follow "Edit questions"
    And I should see "this is a multiple choice 1"

  Scenario: Teacher can delete course templates but can not delete public templates
    # Save gquiz as both public and course template
    When I am on the "Learning experience course 1" "gquiz activity" page logged in as manager
    And I follow "Templates"
    And I set the field "Name" to "My public template"
    And I set the field "Public" to "1"
    And I press "Save as new template"
    And I set the field "Name" to "My course template"
    And I press "Save as new template"
    And I log out
    # Login as teacher and try to delete templates
    And I am on the "Another gquiz in course 1" "gquiz activity" page logged in as teacher
    And I follow "Templates"
    And I follow "Delete template..."
    Then I should not see "My public template"
    And ".publictemplates" "css_element" should not exist
    And "My course template" "text" should exist in the ".coursetemplates" "css_element"
    And I click on "Delete" "link" in the "My course template" "table_row"
    And I should see "Template deleted"
    And "My course template" "text" should not exist in the ".coursetemplates" "css_element"
    And "No templates available yet" "text" should exist in the ".coursetemplates" "css_element"
    And I press "Back"
    And the "Use a template" select box should not contain "My course template"
    And the "Use a template" select box should contain "My public template"

  @javascript
  Scenario: Manager can delete both course and public templates
    # Save gquiz as both public and course template
    When I am on the "Learning experience course 1" "gquiz activity" page logged in as manager
    And I click on "Templates" "link" in the "[role=main]" "css_element"
    And I set the field "Name" to "My public template"
    And I set the field "Public" to "1"
    And I press "Save as new template"
    And I set the field "Name" to "My course template"
    And I press "Save as new template"
    # Delete course template
    And I follow "Delete template..."
    Then "My public template" "text" should exist in the ".publictemplates" "css_element"
    And "My course template" "text" should exist in the ".coursetemplates" "css_element"
    And I click on "Delete" "link" in the "My course template" "table_row"
    And I should see "Are you sure you want to delete this template?"
    And I press "Yes"
    And I should see "Template deleted"
    And "My course template" "text" should not exist in the ".coursetemplates" "css_element"
    And "No templates available yet" "text" should exist in the ".coursetemplates" "css_element"
    # Delete public template
    And "My public template" "text" should exist in the ".publictemplates" "css_element"
    And I click on "Delete" "link" in the "My public template" "table_row"
    And I should see "Are you sure you want to delete this template?"
    And I press "Yes"
    And I should see "Template deleted"
    And "My public template" "text" should not exist in the ".publictemplates" "css_element"
    And "No templates available yet" "text" should exist in the ".publictemplates" "css_element"
    And I press "Back"
    And "Use a template" "field" should not exist
    And I should see "No templates available yet"
