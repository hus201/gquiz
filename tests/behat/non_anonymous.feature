@mod @mod_gquiz
Feature: Non anonymous gquiz
  In order to collect gquizs
  As an teacher
  I need to be able to create and collect gquizs

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | user1    | Username  | 1        |
      | user2    | Username  | 2        |
      | teacher  | Teacher   | 3        |
      | manager  | Manager   | 4        |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user  | course | role    |
      | user1 | C1     | student |
      | user2 | C1     | student |
      | teacher | C1   | editingteacher |
    And the following "system role assigns" exist:
      | user    | course               | role    |
      | manager | Acceptance test site | manager |
    And the following "activities" exist:
      | activity   | name            | course               | idnumber  | anonymous | publish_stats | section |
      | gquiz   | Site gquiz   | Acceptance test site | gquiz0 | 2         | 1             | 1       |
      | gquiz   | Course gquiz | C1                   | gquiz1 | 2         | 1             | 0       |
    When I log in as "manager"
    And I am on site homepage
    And I follow "Site gquiz"
    And I click on "Edit questions" "link" in the "[role=main]" "css_element"
    And I add a "Multiple choice" question to the gquiz with:
      | Question                       | Do you like our site?              |
      | Label                          | multichoice2                       |
      | Multiple choice type           | Multiple choice - single answer    |
      | Hide the "Not selected" option | Yes                                |
      | Multiple choice values         | Yes of course\nNot at all\nI don't know |
    And I log out

  Scenario: Guests can see non anonymous gquiz on front page but can not complete
    When I follow "Site gquiz"
    Then I should not see "Answer the questions"
    And I follow "Preview"
    And I should see "Do you like our site?"
    And I press "Continue"

  Scenario: Complete non anonymous gquiz on the front page as an authenticated user
    And I log in as "user1"
    And I am on site homepage
    And I follow "Site gquiz"
    And I follow "Preview"
    And I should see "Do you like our site?"
    And I press "Continue"
    And I follow "Answer the questions"
    And I should see "Do you like our site?"
    And I set the following fields to these values:
      | Yes of course | 1 |
    And I press "Submit your answers"
    And I should not see "Submitted answers"
    And I press "Continue"

  @javascript
  Scenario: Complete non anonymous gquiz and view analysis on the front page as an authenticated user
    And I log in as "admin"
    And I set the following system permissions of "Authenticated user on frontpage" role:
      | capability                   | permission |
      | mod/gquiz:viewanalysepage | Allow      |
    And I log out
    And I log in as "user1"
    And I am on site homepage
    When I follow "Site gquiz"
    And I follow "Answer the questions"
    And I should see "Do you like our site?"
    And I set the following fields to these values:
      | Yes of course | 1 |
    And I press "Submit your answers"
    And I log out
    And I log in as "user2"
    And I am on site homepage
    When I follow "Site gquiz"
    And I follow "Answer the questions"
    And I set the following fields to these values:
      | Not at all | 1 |
    And I press "Submit your answers"
    And I follow "Submitted answers"
    And I should see "Submitted answers: 2"
    And I should see "Questions: 1"
    # And I should not see "multichoice2" # TODO MDL-29303 do not show labels to users who can not edit gquiz
    And I show chart data for the "multichoice2" gquiz
    And I should see "Do you like our site?"
    And I should see "1 (50.00 %)" in the "Yes of course" "table_row"
    And I should see "1 (50.00 %)" in the "Not at all" "table_row"
    And I should not see "Show responses"
    And I log out
    And I log in as "manager"
    And I am on site homepage
    And I follow "Site gquiz"
    And I navigate to "Show responses" in current page administration
    And I should see "Username"
    And I should see "Non anonymous entries (2)"
    And I should not see "Anonymous entries"
    And I click on "," "link" in the "Username 1" "table_row"
    And I should see "(Username 1)"
    And I should see "Yes of course"
    And I follow "Back"
    And I log out

  @javascript
  Scenario: Non anonymous gquiz in a course
    When I log in as "teacher"
    And I am on "Course 1" course homepage
    And I follow "Course gquiz"
    And I click on "Edit questions" "link" in the "[role=main]" "css_element"
    And I add a "Multiple choice" question to the gquiz with:
      | Question                       | Do you like this course?           |
      | Label                          | multichoice1                       |
      | Multiple choice type           | Multiple choice - single answer    |
      | Hide the "Not selected" option | Yes                                |
      | Multiple choice values         | Yes of course\nNot at all\nI don't know |
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "Course gquiz"
    And I follow "Answer the questions"
    And I should see "Do you like this course?"
    And I set the following fields to these values:
      | Yes of course | 1 |
    And I press "Submit your answers"
    And I log out
    And I log in as "user2"
    And I am on "Course 1" course homepage
    And I follow "Course gquiz"
    And I follow "Answer the questions"
    And I should see "Do you like this course?"
    And I set the following fields to these values:
      | Not at all | 1 |
    And I press "Submit your answers"
    And I follow "Submitted answers"
    And I should see "Submitted answers: 2"
    And I should see "Questions: 1"
    # And I should not see "multichoice2" # TODO MDL-29303
    And I show chart data for the "multichoice1" gquiz
    And I should see "Do you like this course?"
    And I should see "1 (50.00 %)" in the "Yes of course" "table_row"
    And I should see "1 (50.00 %)" in the "Not at all" "table_row"
    And I log out
    And I log in as "teacher"
    And I am on "Course 1" course homepage
    And I follow "Course gquiz"
    And I follow "Preview"
    And I should see "Do you like this course?"
    And I press "Continue"
    And I should not see "Answer the questions"
    And I navigate to "Show responses" in current page administration
    And I should see "Non anonymous entries (2)"
    And I should not see "Anonymous"
    And I click on "," "link" in the "Username 1" "table_row"
    And I should see "(Username 1)"
    And I should see "Yes of course"
    And I should not see "Prev"
    And I follow "Next"
    And I should see "(Username 2)"
    And I should not see "Next"
    And I should see "Prev"
    And I click on "Back" "link" in the "region-main" "region"
    # Delete non anonymous response
    And I click on "Delete entry" "link" in the "Username 1" "table_row"
    And I press "Yes"
    And I should see "Non anonymous entries (1)"
    And I should not see "Username 1"
    And I should see "Username 2"
    And I log out