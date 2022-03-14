<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * gquiz module external functions tests
 *
 * @package    mod_gquiz
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

namespace mod_gquiz\external;

use externallib_advanced_testcase;
use gquiz_item_multichoice;
use mod_gquiz_external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/gquiz/lib.php');

/**
 * gquiz module external functions tests
 *
 * @package    mod_gquiz
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class external_test extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->gquiz = $this->getDataGenerator()->create_module('gquiz',
            array('course' => $this->course->id, 'email_notification' => 1));
        $this->context = \context_module::instance($this->gquiz->cmid);
        $this->cm = get_coursemodule_from_instance('gquiz', $this->gquiz->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');
    }

    /**
     * Helper method to add items to an existing gquiz.
     *
     * @param \stdClass $gquiz gquiz instance
     * @param integer $pagescount the number of pages we want in the gquiz
     * @return array list of items created
     */
    public function populate_gquiz($gquiz, $pagescount = 1) {
        $gquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');
        $itemscreated = [];

        // Create at least one page.
        $itemscreated[] = $gquizgenerator->create_item_label($gquiz);
        $itemscreated[] = $gquizgenerator->create_item_info($gquiz);
        $itemscreated[] = $gquizgenerator->create_item_numeric($gquiz);

        // Check if we want more pages.
        for ($i = 1; $i < $pagescount; $i++) {
            $itemscreated[] = $gquizgenerator->create_item_pagebreak($gquiz);
            $itemscreated[] = $gquizgenerator->create_item_multichoice($gquiz);
            $itemscreated[] = $gquizgenerator->create_item_multichoicerated($gquiz);
            $itemscreated[] = $gquizgenerator->create_item_textarea($gquiz);
            $itemscreated[] = $gquizgenerator->create_item_textfield($gquiz);
            $itemscreated[] = $gquizgenerator->create_item_numeric($gquiz);
        }
        return $itemscreated;
    }


    /**
     * Test test_mod_gquiz_get_gquizs_by_courses
     */
    public function test_mod_gquiz_get_gquizs_by_courses() {
        global $DB;

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second gquiz.
        $record = new \stdClass();
        $record->course = $course2->id;
        $gquiz2 = self::getDataGenerator()->create_module('gquiz', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $this->student->id, $this->studentrole->id);

        self::setUser($this->student);

        $returndescription = mod_gquiz_external::get_gquizs_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles', 'anonymous',
            'multiple_submit', 'autonumbering', 'page_after_submitformat', 'publish_stats', 'completionsubmit');

        $properties = gquiz_summary_exporter::read_properties_definition();

        // Add expected coursemodule and data.
        $gquiz1 = $this->gquiz;
        $gquiz1->coursemodule = $gquiz1->cmid;
        $gquiz1->introformat = 1;
        $gquiz1->introfiles = [];

        $gquiz2->coursemodule = $gquiz2->cmid;
        $gquiz2->introformat = 1;
        $gquiz2->introfiles = [];

        foreach ($expectedfields as $field) {
            if (!empty($properties[$field]) && $properties[$field]['type'] == PARAM_BOOL) {
                $gquiz1->{$field} = (bool) $gquiz1->{$field};
                $gquiz2->{$field} = (bool) $gquiz2->{$field};
            }
            $expected1[$field] = $gquiz1->{$field};
            $expected2[$field] = $gquiz2->{$field};
        }

        $expectedgquizs = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_gquiz_external::get_gquizs_by_courses(array($course2->id, $this->course->id));
        $result = \external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedgquizs, $result['gquizs']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_gquiz_external::get_gquizs_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedgquizs, $result['gquizs']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected gquizs.
        $enrol->unenrol_user($instance2, $this->student->id);
        array_shift($expectedgquizs);

        // Call the external function without passing course id.
        $result = mod_gquiz_external::get_gquizs_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedgquizs, $result['gquizs']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_gquiz_external::get_gquizs_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($this->teacher);

        $additionalfields = array('email_notification', 'site_after_submit', 'page_after_submit', 'timeopen', 'timeclose',
            'timemodified', 'pageaftersubmitfiles');

        $gquiz1->pageaftersubmitfiles = [];

        foreach ($additionalfields as $field) {
            if (!empty($properties[$field]) && $properties[$field]['type'] == PARAM_BOOL) {
                $gquiz1->{$field} = (bool) $gquiz1->{$field};
            }
            $expectedgquizs[0][$field] = $gquiz1->{$field};
        }
        $expectedgquizs[0]['page_after_submitformat'] = 1;

        $result = mod_gquiz_external::get_gquizs_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedgquizs, $result['gquizs']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_gquiz_external::get_gquizs_by_courses(array($this->course->id));
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedgquizs, $result['gquizs']);
    }

    /**
     * Test get_gquiz_access_information function with basic defaults for student.
     */
    public function test_get_gquiz_access_information_student() {

        self::setUser($this->student);
        $result = mod_gquiz_external::get_gquiz_access_information($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_gquiz_access_information_returns(), $result);

        $this->assertFalse($result['canviewanalysis']);
        $this->assertFalse($result['candeletesubmissions']);
        $this->assertFalse($result['canviewreports']);
        $this->assertFalse($result['canedititems']);
        $this->assertTrue($result['cancomplete']);
        $this->assertTrue($result['cansubmit']);
        $this->assertTrue($result['isempty']);
        $this->assertTrue($result['isopen']);
        $this->assertTrue($result['isanonymous']);
        $this->assertFalse($result['isalreadysubmitted']);
    }

    /**
     * Test get_gquiz_access_information function with basic defaults for teacher.
     */
    public function test_get_gquiz_access_information_teacher() {

        self::setUser($this->teacher);
        $result = mod_gquiz_external::get_gquiz_access_information($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_gquiz_access_information_returns(), $result);

        $this->assertTrue($result['canviewanalysis']);
        $this->assertTrue($result['canviewreports']);
        $this->assertTrue($result['canedititems']);
        $this->assertTrue($result['candeletesubmissions']);
        $this->assertFalse($result['cancomplete']);
        $this->assertTrue($result['cansubmit']);
        $this->assertTrue($result['isempty']);
        $this->assertTrue($result['isopen']);
        $this->assertTrue($result['isanonymous']);
        $this->assertFalse($result['isalreadysubmitted']);

        // Add some items to the gquiz and check is not empty any more.
        self::populate_gquiz($this->gquiz);
        $result = mod_gquiz_external::get_gquiz_access_information($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_gquiz_access_information_returns(), $result);
        $this->assertFalse($result['isempty']);
    }

    /**
     * Test view_gquiz invalid id.
     */
    public function test_view_gquiz_invalid_id() {
        // Test invalid instance id.
        $this->expectException('\moodle_exception');
        mod_gquiz_external::view_gquiz(0);
    }
    /**
     * Test view_gquiz not enrolled user.
     */
    public function test_view_gquiz_not_enrolled_user() {
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        $this->expectException('\moodle_exception');
        mod_gquiz_external::view_gquiz(0);
    }
    /**
     * Test view_gquiz no capabilities.
     */
    public function test_view_gquiz_no_capabilities() {
        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is allowed for students by default.
        assign_capability('mod/gquiz:view', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->expectException('\moodle_exception');
        mod_gquiz_external::view_gquiz(0);
    }
    /**
     * Test view_gquiz.
     */
    public function test_view_gquiz() {
        // Test user with full capabilities.
        $this->setUser($this->student);
        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $result = mod_gquiz_external::view_gquiz($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::view_gquiz_returns(), $result);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);
        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_gquiz\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodledata = new \moodle_url('/mod/gquiz/view.php', array('id' => $this->cm->id));
        $this->assertEquals($moodledata, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test get_current_completed_tmp.
     */
    public function test_get_current_completed_tmp() {
        global $DB;

        // Force non anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_NO, array('id' => $this->gquiz->id));
        // Add a completed_tmp record.
        $record = [
            'gquiz' => $this->gquiz->id,
            'userid' => $this->student->id,
            'guestid' => '',
            'timemodified' => time() - DAYSECS,
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_NO,
            'courseid' => $this->course->id,
        ];
        $record['id'] = $DB->insert_record('gquiz_completedtmp', (object) $record);

        // Test user with full capabilities.
        $this->setUser($this->student);

        $result = mod_gquiz_external::get_current_completed_tmp($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_current_completed_tmp_returns(), $result);
        $this->assertEquals($record['id'], $result['gquiz']['id']);
    }

    /**
     * Test get_items.
     */
    public function test_get_items() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Add questions to the gquiz, we are adding 2 pages of questions.
        $itemscreated = self::populate_gquiz($this->gquiz, 2);

        $result = mod_gquiz_external::get_items($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_items_returns(), $result);
        $this->assertCount(count($itemscreated), $result['items']);
        $index = 1;
        foreach ($result['items'] as $key => $item) {
            if (is_numeric($itemscreated[$key])) {
                continue; // Page break.
            }
            // Cannot compare directly the exporter and the db object (exporter have more fields).
            $this->assertEquals($itemscreated[$key]->id, $item['id']);
            $this->assertEquals($itemscreated[$key]->typ, $item['typ']);
            $this->assertEquals($itemscreated[$key]->name, $item['name']);
            $this->assertEquals($itemscreated[$key]->label, $item['label']);

            if ($item['hasvalue']) {
                $this->assertEquals($index, $item['itemnumber']);
                $index++;
            }
        }
    }

    /**
     * Test launch_gquiz.
     */
    public function test_launch_gquiz() {
        global $DB;

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Add questions to the gquiz, we are adding 2 pages of questions.
        $itemscreated = self::populate_gquiz($this->gquiz, 2);

        // First try a gquiz we didn't attempt.
        $result = mod_gquiz_external::launch_gquiz($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::launch_gquiz_returns(), $result);
        $this->assertEquals(0, $result['gopage']);

        // Now, try a gquiz that we attempted.
        // Force non anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_NO, array('id' => $this->gquiz->id));
        // Add a completed_tmp record.
        $record = [
            'gquiz' => $this->gquiz->id,
            'userid' => $this->student->id,
            'guestid' => '',
            'timemodified' => time() - DAYSECS,
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_NO,
            'courseid' => $this->course->id,
        ];
        $record['id'] = $DB->insert_record('gquiz_completedtmp', (object) $record);

        // Add a response to the gquiz for each question type with possible values.
        $response = [
            'course_id' => $this->course->id,
            'item' => $itemscreated[1]->id, // First item is the info question.
            'completed' => $record['id'],
            'tmp_completed' => $record['id'],
            'value' => 'A',
        ];
        $DB->insert_record('gquiz_valuetmp', (object) $response);
        $response = [
            'course_id' => $this->course->id,
            'item' => $itemscreated[2]->id, // Second item is the numeric question.
            'completed' => $record['id'],
            'tmp_completed' => $record['id'],
            'value' => 5,
        ];
        $DB->insert_record('gquiz_valuetmp', (object) $response);

        $result = mod_gquiz_external::launch_gquiz($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::launch_gquiz_returns(), $result);
        $this->assertEquals(1, $result['gopage']);
    }

    /**
     * Test get_page_items.
     */
    public function test_get_page_items() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Add questions to the gquiz, we are adding 2 pages of questions.
        $itemscreated = self::populate_gquiz($this->gquiz, 2);

        // Retrieve first page.
        $result = mod_gquiz_external::get_page_items($this->gquiz->id, 0);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_page_items_returns(), $result);
        $this->assertCount(3, $result['items']);    // The first page has 3 items.
        $this->assertTrue($result['hasnextpage']);
        $this->assertFalse($result['hasprevpage']);

        // Retrieve second page.
        $result = mod_gquiz_external::get_page_items($this->gquiz->id, 1);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_page_items_returns(), $result);
        $this->assertCount(5, $result['items']);    // The second page has 5 items (page break doesn't count).
        $this->assertFalse($result['hasnextpage']);
        $this->assertTrue($result['hasprevpage']);
    }

    /**
     * Test process_page.
     */
    public function test_process_page() {
        global $DB;

        // Test user with full capabilities.
        $this->setUser($this->student);
        $pagecontents = 'You finished it!';
        $DB->set_field('gquiz', 'page_after_submit', $pagecontents, array('id' => $this->gquiz->id));

        // Add questions to the gquiz, we are adding 2 pages of questions.
        $itemscreated = self::populate_gquiz($this->gquiz, 2);

        $data = [];
        foreach ($itemscreated as $item) {

            if (empty($item->hasvalue)) {
                continue;
            }

            switch ($item->typ) {
                case 'textarea':
                case 'textfield':
                    $value = 'Lorem ipsum';
                    break;
                case 'numeric':
                    $value = 5;
                    break;
                case 'multichoice':
                    $value = '1';
                    break;
                case 'multichoicerated':
                    $value = '1';
                    break;
                case 'info':
                    $value = format_string($this->course->shortname, true, array('context' => $this->context));
                    break;
                default:
                    $value = '';
            }
            $data[] = ['name' => $item->typ . '_' . $item->id, 'value' => $value];
        }

        // Process first page.
        $firstpagedata = [$data[0], $data[1]];
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $firstpagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertEquals(1, $result['jumpto']);
        $this->assertFalse($result['completed']);

        // Now, process the second page. But first we are going back to the first page.
        $secondpagedata = [$data[2], $data[3], $data[4], $data[5], $data[6]];
        $result = mod_gquiz_external::process_page($this->gquiz->id, 1, $secondpagedata, true);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertFalse($result['completed']);
        $this->assertEquals(0, $result['jumpto']);  // We jumped to the first page.
        // Check the values were correctly saved.
        $tmpitems = $DB->get_records('gquiz_valuetmp');
        $this->assertCount(7, $tmpitems);   // 2 from the first page + 5 from the second page.

        // Go forward again (sending the same data).
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $firstpagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertEquals(1, $result['jumpto']);
        $this->assertFalse($result['completed']);
        $tmpitems = $DB->get_records('gquiz_valuetmp');
        $this->assertCount(7, $tmpitems);   // 2 from the first page + 5 from the second page.

        // And finally, save everything! We are going to modify one previous recorded value.
        $messagessink = $this->redirectMessages();
        $data[2]['value'] = 2; // 2 is value of the option 'b'.
        $secondpagedata = [$data[2], $data[3], $data[4], $data[5], $data[6]];
        $result = mod_gquiz_external::process_page($this->gquiz->id, 1, $secondpagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);
        $this->assertTrue(strpos($result['completionpagecontents'], $pagecontents) !== false);
        // Check all the items were saved.
        $items = $DB->get_records('gquiz_value');
        $this->assertCount(7, $items);
        // Check if the one we modified was correctly saved.
        $itemid = $itemscreated[4]->id;
        $itemsaved = $DB->get_field('gquiz_value', 'value', array('item' => $itemid));
        $mcitem = new gquiz_item_multichoice();
        $itemval = $mcitem->get_printval($itemscreated[4], (object) ['value' => $itemsaved]);
        $this->assertEquals('b', $itemval);

        // Check that the answers are saved for course 0.
        foreach ($items as $item) {
            $this->assertEquals(0, $item->course_id);
        }
        $completed = $DB->get_record('gquiz_completed', []);
        $this->assertEquals(0, $completed->courseid);

        // Test notifications sent.
        $messages = $messagessink->get_messages();
        $messagessink->close();
        // Test customdata.
        $customdata = json_decode($messages[0]->customdata);
        $this->assertEquals($this->gquiz->id, $customdata->instance);
        $this->assertEquals($this->gquiz->cmid, $customdata->cmid);
        $this->assertObjectHasAttribute('notificationiconurl', $customdata);
    }

    /**
     * Test process_page for a site gquiz.
     */
    public function test_process_page_site_gquiz() {
        global $DB;
        $pagecontents = 'You finished it!';
        $this->gquiz = $this->getDataGenerator()->create_module('gquiz',
            array('course' => SITEID, 'page_after_submit' => $pagecontents));

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Add questions to the gquiz, we are adding 2 pages of questions.
        $itemscreated = self::populate_gquiz($this->gquiz, 2);

        $data = [];
        foreach ($itemscreated as $item) {

            if (empty($item->hasvalue)) {
                continue;
            }

            switch ($item->typ) {
                case 'textarea':
                case 'textfield':
                    $value = 'Lorem ipsum';
                    break;
                case 'numeric':
                    $value = 5;
                    break;
                case 'multichoice':
                    $value = '1';
                    break;
                case 'multichoicerated':
                    $value = '1';
                    break;
                case 'info':
                    $value = format_string($this->course->shortname, true, array('context' => $this->context));
                    break;
                default:
                    $value = '';
            }
            $data[] = ['name' => $item->typ . '_' . $item->id, 'value' => $value];
        }

        // Process first page.
        $firstpagedata = [$data[0], $data[1]];
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $firstpagedata, false, $this->course->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertEquals(1, $result['jumpto']);
        $this->assertFalse($result['completed']);

        // Process second page.
        $data[2]['value'] = 2; // 2 is value of the option 'b';
        $secondpagedata = [$data[2], $data[3], $data[4], $data[5], $data[6]];
        $result = mod_gquiz_external::process_page($this->gquiz->id, 1, $secondpagedata, false, $this->course->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);
        $this->assertTrue(strpos($result['completionpagecontents'], $pagecontents) !== false);
        // Check all the items were saved.
        $items = $DB->get_records('gquiz_value');
        $this->assertCount(7, $items);
        // Check if the one we modified was correctly saved.
        $itemid = $itemscreated[4]->id;
        $itemsaved = $DB->get_field('gquiz_value', 'value', array('item' => $itemid));
        $mcitem = new gquiz_item_multichoice();
        $itemval = $mcitem->get_printval($itemscreated[4], (object) ['value' => $itemsaved]);
        $this->assertEquals('b', $itemval);

        // Check that the answers are saved for the correct course.
        foreach ($items as $item) {
            $this->assertEquals($this->course->id, $item->course_id);
        }
        $completed = $DB->get_record('gquiz_completed', []);
        $this->assertEquals($this->course->id, $completed->courseid);
    }

    /**
     * Test get_analysis.
     */
    public function test_get_analysis() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Create a very simple gquiz.
        $gquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');
        $numericitem = $gquizgenerator->create_item_numeric($this->gquiz);
        $textfielditem = $gquizgenerator->create_item_textfield($this->gquiz);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 5],
            ['name' => $textfielditem->typ .'_'. $textfielditem->id, 'value' => 'abc'],
        ];
        // Process the gquiz, there is only one page so the gquiz will be completed.
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);

        // Retrieve analysis.
        $this->setUser($this->teacher);
        $result = mod_gquiz_external::get_analysis($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_analysis_returns(), $result);
        $this->assertEquals(1, $result['completedcount']);  // 1 gquiz completed.
        $this->assertEquals(2, $result['itemscount']);  // 2 items in the gquiz.
        $this->assertCount(2, $result['itemsdata']);
        $this->assertCount(1, $result['itemsdata'][0]['data']); // There are 1 response per item.
        $this->assertCount(1, $result['itemsdata'][1]['data']);
        // Check we receive the info the students filled.
        foreach ($result['itemsdata'] as $data) {
            if ($data['item']['id'] == $numericitem->id) {
                $this->assertEquals(5, $data['data'][0]);
            } else {
                $this->assertEquals('abc', $data['data'][0]);
            }
        }

        // Create another user / response.
        $anotherstudent = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->setUser($anotherstudent);

        // Process the gquiz, there is only one page so the gquiz will be completed.
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);

        // Retrieve analysis.
        $this->setUser($this->teacher);
        $result = mod_gquiz_external::get_analysis($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_analysis_returns(), $result);
        $this->assertEquals(2, $result['completedcount']);  // 2 gquiz completed.
        $this->assertEquals(2, $result['itemscount']);
        $this->assertCount(2, $result['itemsdata'][0]['data']); // There are 2 responses per item.
        $this->assertCount(2, $result['itemsdata'][1]['data']);
    }

    /**
     * Test get_unfinished_responses.
     */
    public function test_get_unfinished_responses() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Create a very simple gquiz.
        $gquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');
        $numericitem = $gquizgenerator->create_item_numeric($this->gquiz);
        $textfielditem = $gquizgenerator->create_item_textfield($this->gquiz);
        $gquizgenerator->create_item_pagebreak($this->gquiz);
        $labelitem = $gquizgenerator->create_item_label($this->gquiz);
        $numericitem2 = $gquizgenerator->create_item_numeric($this->gquiz);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 5],
            ['name' => $textfielditem->typ .'_'. $textfielditem->id, 'value' => 'abc'],
        ];
        // Process the gquiz, there are two pages so the gquiz will be unfinished yet.
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertFalse($result['completed']);

        // Retrieve the unfinished responses.
        $result = mod_gquiz_external::get_unfinished_responses($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_unfinished_responses_returns(), $result);
        // Check that ids and responses match.
        foreach ($result['responses'] as $r) {
            if ($r['item'] == $numericitem->id) {
                $this->assertEquals(5, $r['value']);
            } else {
                $this->assertEquals($textfielditem->id, $r['item']);
                $this->assertEquals('abc', $r['value']);
            }
        }
    }

    /**
     * Test get_finished_responses.
     */
    public function test_get_finished_responses() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Create a very simple gquiz.
        $gquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');
        $numericitem = $gquizgenerator->create_item_numeric($this->gquiz);
        $textfielditem = $gquizgenerator->create_item_textfield($this->gquiz);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 5],
            ['name' => $textfielditem->typ .'_'. $textfielditem->id, 'value' => 'abc'],
        ];

        // Process the gquiz, there is only one page so the gquiz will be completed.
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);

        // Retrieve the responses.
        $result = mod_gquiz_external::get_finished_responses($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_finished_responses_returns(), $result);
        // Check that ids and responses match.
        foreach ($result['responses'] as $r) {
            if ($r['item'] == $numericitem->id) {
                $this->assertEquals(5, $r['value']);
            } else {
                $this->assertEquals($textfielditem->id, $r['item']);
                $this->assertEquals('abc', $r['value']);
            }
        }
    }

    /**
     * Test get_non_respondents (student trying to get this information).
     */
    public function test_get_non_respondents_no_permissions() {
        $this->setUser($this->student);
        $this->expectException('\moodle_exception');
        mod_gquiz_external::get_non_respondents($this->gquiz->id);
    }

    /**
     * Test get_non_respondents from an anonymous gquiz.
     */
    public function test_get_non_respondents_from_anonymous_gquiz() {
        $this->setUser($this->student);
        $this->expectException('\moodle_exception');
        $this->expectExceptionMessage(get_string('anonymous', 'gquiz'));
        mod_gquiz_external::get_non_respondents($this->gquiz->id);
    }

    /**
     * Test get_non_respondents.
     */
    public function test_get_non_respondents() {
        global $DB;

        // Force non anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_NO, array('id' => $this->gquiz->id));

        // Create another student.
        $anotherstudent = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->setUser($anotherstudent);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Create a very simple gquiz.
        $gquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');
        $numericitem = $gquizgenerator->create_item_numeric($this->gquiz);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 5],
        ];

        // Process the gquiz, there is only one page so the gquiz will be completed.
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);

        // Retrieve the non-respondent users.
        $this->setUser($this->teacher);
        $result = mod_gquiz_external::get_non_respondents($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_non_respondents_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(1, $result['users']);
        $this->assertEquals($anotherstudent->id, $result['users'][0]['userid']);

        // Create another student.
        $anotherstudent2 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent2->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->setUser($anotherstudent2);
        $this->setUser($this->teacher);
        $result = mod_gquiz_external::get_non_respondents($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_non_respondents_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['users']);

        // Test pagination.
        $result = mod_gquiz_external::get_non_respondents($this->gquiz->id, 0, 'lastaccess', 0, 1);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_non_respondents_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(1, $result['users']);
    }

    /**
     * Helper function that completes the gquiz for two students.
     */
    protected function complete_basic_gquiz() {
        global $DB;

        $generator = $this->getDataGenerator();
        // Create separated groups.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);
        $DB->set_field('course', 'groupmodeforce', 1);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $this->teacherrole->id, $this->context);
        accesslib_clear_all_caches_for_unit_testing();

        $group1 = $generator->create_group(array('courseid' => $this->course->id));
        $group2 = $generator->create_group(array('courseid' => $this->course->id));

        // Create another students.
        $anotherstudent1 = self::getDataGenerator()->create_user();
        $anotherstudent2 = self::getDataGenerator()->create_user();
        $generator->enrol_user($anotherstudent1->id, $this->course->id, $this->studentrole->id, 'manual');
        $generator->enrol_user($anotherstudent2->id, $this->course->id, $this->studentrole->id, 'manual');

        $generator->create_group_member(array('groupid' => $group1->id, 'userid' => $this->student->id));
        $generator->create_group_member(array('groupid' => $group1->id, 'userid' => $this->teacher->id));
        $generator->create_group_member(array('groupid' => $group1->id, 'userid' => $anotherstudent1->id));
        $generator->create_group_member(array('groupid' => $group2->id, 'userid' => $anotherstudent2->id));

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Create a very simple gquiz.
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $numericitem = $gquizgenerator->create_item_numeric($this->gquiz);
        $textfielditem = $gquizgenerator->create_item_textfield($this->gquiz);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 5],
            ['name' => $textfielditem->typ .'_'. $textfielditem->id, 'value' => 'abc'],
        ];

        // Process the gquiz, there is only one page so the gquiz will be completed.
        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);

        $this->setUser($anotherstudent1);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 10],
            ['name' => $textfielditem->typ .'_'. $textfielditem->id, 'value' => 'def'],
        ];

        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);

        $this->setUser($anotherstudent2);

        $pagedata = [
            ['name' => $numericitem->typ .'_'. $numericitem->id, 'value' => 10],
            ['name' => $textfielditem->typ .'_'. $textfielditem->id, 'value' => 'def'],
        ];

        $result = mod_gquiz_external::process_page($this->gquiz->id, 0, $pagedata);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::process_page_returns(), $result);
        $this->assertTrue($result['completed']);
    }

    /**
     * Test get_responses_analysis for anonymous gquiz.
     */
    public function test_get_responses_analysis_anonymous() {
        self::complete_basic_gquiz();

        // Retrieve the responses analysis.
        $this->setUser($this->teacher);
        $result = mod_gquiz_external::get_responses_analysis($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_responses_analysis_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(0, $result['totalattempts']);
        $this->assertEquals(2, $result['totalanonattempts']);   // Only see my groups.

        foreach ($result['attempts'] as $attempt) {
            $this->assertEmpty($attempt['userid']); // Is anonymous.
        }
    }

    /**
     * Test get_responses_analysis for non-anonymous gquiz.
     */
    public function test_get_responses_analysis_non_anonymous() {
        global $DB;

        // Force non anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_NO, array('id' => $this->gquiz->id));

        self::complete_basic_gquiz();
        // Retrieve the responses analysis.
        $this->setUser($this->teacher);
        $result = mod_gquiz_external::get_responses_analysis($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_responses_analysis_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(2, $result['totalattempts']);
        $this->assertEquals(0, $result['totalanonattempts']);   // Only see my groups.

        foreach ($result['attempts'] as $attempt) {
            $this->assertNotEmpty($attempt['userid']);  // Is not anonymous.
        }
    }

    /**
     * Test get_last_completed for gquiz anonymous not completed.
     */
    public function test_get_last_completed_anonymous_not_completed() {
        global $DB;

        // Force anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_YES, array('id' => $this->gquiz->id));

        // Test user with full capabilities that didn't complete the gquiz.
        $this->setUser($this->student);

        $this->expectExceptionMessage(get_string('anonymous', 'gquiz'));
        $this->expectException('\moodle_exception');
        mod_gquiz_external::get_last_completed($this->gquiz->id);
    }

    /**
     * Test get_last_completed for gquiz anonymous and completed.
     */
    public function test_get_last_completed_anonymous_completed() {
        global $DB;

        // Force anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_YES, array('id' => $this->gquiz->id));
        // Add one completion record..
        $record = [
            'gquiz' => $this->gquiz->id,
            'userid' => $this->student->id,
            'timemodified' => time() - DAYSECS,
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_YES,
            'courseid' => $this->course->id,
        ];
        $record['id'] = $DB->insert_record('gquiz_completed', (object) $record);

        // Test user with full capabilities.
        $this->setUser($this->student);

        $this->expectExceptionMessage(get_string('anonymous', 'gquiz'));
        $this->expectException('\moodle_exception');
        mod_gquiz_external::get_last_completed($this->gquiz->id);
    }

    /**
     * Test get_last_completed for gquiz not anonymous and completed.
     */
    public function test_get_last_completed_not_anonymous_completed() {
        global $DB;

        // Force non anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_NO, array('id' => $this->gquiz->id));
        // Add one completion record..
        $record = [
            'gquiz' => $this->gquiz->id,
            'userid' => $this->student->id,
            'timemodified' => time() - DAYSECS,
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_NO,
            'courseid' => $this->course->id,
        ];
        $record['id'] = $DB->insert_record('gquiz_completed', (object) $record);

        // Test user with full capabilities.
        $this->setUser($this->student);
        $result = mod_gquiz_external::get_last_completed($this->gquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_last_completed_returns(), $result);
        $this->assertEquals($record, $result['completed']);
    }

    /**
     * Test get_last_completed for gquiz not anonymous and not completed.
     */
    public function test_get_last_completed_not_anonymous_not_completed() {
        global $DB;

        // Force anonymous.
        $DB->set_field('gquiz', 'anonymous', gquiz_ANONYMOUS_NO, array('id' => $this->gquiz->id));

        // Test user with full capabilities that didn't complete the gquiz.
        $this->setUser($this->student);

        $this->expectExceptionMessage(get_string('not_completed_yet', 'gquiz'));
        $this->expectException('\moodle_exception');
        mod_gquiz_external::get_last_completed($this->gquiz->id);
    }

    /**
     * Test get_gquiz_access_information for site gquiz.
     */
    public function test_get_gquiz_access_information_for_site_gquiz() {

        $sitegquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => SITEID));
        $this->setUser($this->student);
        // Access the site gquiz via the site activity.
        $result = mod_gquiz_external::get_gquiz_access_information($sitegquiz->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_gquiz_access_information_returns(), $result);
        $this->assertTrue($result['cancomplete']);
        $this->assertTrue($result['cansubmit']);

        // Access the site gquiz via course where I'm enrolled.
        $result = mod_gquiz_external::get_gquiz_access_information($sitegquiz->id, $this->course->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_gquiz_access_information_returns(), $result);
        $this->assertTrue($result['cancomplete']);
        $this->assertTrue($result['cansubmit']);

        // Access the site gquiz via course where I'm not enrolled.
        $othercourse = $this->getDataGenerator()->create_course();

        $this->expectException('\moodle_exception');
        mod_gquiz_external::get_gquiz_access_information($sitegquiz->id, $othercourse->id);
    }

    /**
     * Test get_gquiz_access_information for site gquiz mapped.
     */
    public function test_get_gquiz_access_information_for_site_gquiz_mapped() {
        global $DB;

        $sitegquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => SITEID));
        $this->setUser($this->student);
        $DB->insert_record('gquiz_sitecourse_map', array('gquizid' => $sitegquiz->id, 'courseid' => $this->course->id));

        // Access the site gquiz via course where I'm enrolled and mapped.
        $result = mod_gquiz_external::get_gquiz_access_information($sitegquiz->id, $this->course->id);
        $result = \external_api::clean_returnvalue(mod_gquiz_external::get_gquiz_access_information_returns(), $result);
        $this->assertTrue($result['cancomplete']);
        $this->assertTrue($result['cansubmit']);

        // Access the site gquiz via course where I'm enrolled but not mapped.
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $othercourse->id, $this->studentrole->id, 'manual');

        $this->expectException('\moodle_exception');
        $this->expectExceptionMessage(get_string('cannotaccess', 'mod_gquiz'));
        mod_gquiz_external::get_gquiz_access_information($sitegquiz->id, $othercourse->id);
    }
}
