@qbank @qbank_importquestions @_file_upload
Feature: Import questions from a file with a long multi-byte filename
  In order to import questions regardless of the file name
  As a teacher
  I need the import to work when the filename is longer than 255 bytes (but under 255 characters)

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |

  @javascript
  Scenario: Import a Moodle XML file saved under a 147-character Cyrillic name
    Given I am on the "Course 1" "core_question > course question import" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/truefalse/tests/fixtures/testquestion.moodle.xml" file to "Import" filemanager as:
      | Save as | ВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТестаВопросТеста.xml |
    When I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    And I press "Continue"
    And I should see "true-false-001"
