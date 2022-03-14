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

namespace mod_gquiz\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_gquiz
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_date_test extends \restore_date_testcase {

    public function test_restore_dates() {
        global $DB, $USER;

        $time = 10000;
        list($course, $gquiz) = $this->create_course_and_module('gquiz', ['timeopen' => $time, 'timeclose' => $time]);

        // Create response.
        $response = new \stdClass();
        $response->gquiz = $gquiz->id;
        $response->userid = $USER->id;
        $response->anonymous_response = gquiz_ANONYMOUS_NO;
        $response->timemodified = $time;
        $completedid = $DB->insert_record('gquiz_completed', $response);
        $response = $DB->get_record('gquiz_completed', array('id' => $completedid), '*', MUST_EXIST);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newgquiz = $DB->get_record('gquiz', ['course' => $newcourseid]);
        $newresponse = $DB->get_record('gquiz_completed', ['gquiz' => $newgquiz->id]);

        $this->assertFieldsNotRolledForward($gquiz, $newgquiz, ['timemodified']);
        $props = ['timeopen', 'timeclose'];
        $this->assertFieldsRolledForward($gquiz, $newgquiz, $props);
        $this->assertEquals($response->timemodified, $newresponse->timemodified);
    }

    /**
     * Test that dependency for items is restored correctly.
     */
    public function test_restore_item_dependency() {
        global $DB;
        // Create a course and a gquiz activity.
        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => $course));
        $gquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');

        // Create a couple of items which depend on each other.
        $item1 = $gquizgenerator->create_item_numeric($gquiz);
        $item2 = $gquizgenerator->create_item_numeric($gquiz, array('dependitem' => $item1->id));
        $DB->set_field('gquiz_item', 'dependitem', $item2->id, ['id' => $item1->id]);

        // Create one more item with fake/broken dependitem.
        $item3 = $gquizgenerator->create_item_numeric($gquiz, array('dependitem' => 123456));

        // Backup and restore the course.
        $restoredcourseid = $this->backup_and_restore($course);
        $restoredgquiz = $DB->get_record('gquiz', ['course' => $restoredcourseid]);

        // Restored item1 and item2 are expected to be dependent the same way as the original ones.
        $restoreditem1 = $DB->get_record('gquiz_item', ['gquiz' => $restoredgquiz->id, 'name' => $item1->name]);
        $restoreditem2 = $DB->get_record('gquiz_item', ['gquiz' => $restoredgquiz->id, 'name' => $item2->name]);
        $this->assertEquals($restoreditem2->id, $restoreditem1->dependitem);
        $this->assertEquals($restoreditem1->id, $restoreditem2->dependitem);

        // Restored item3 is expected to have an empty dependitem.
        $restoreditem3 = $DB->get_record('gquiz_item', ['gquiz' => $restoredgquiz->id, 'name' => $item3->name]);
        $this->assertEquals(0, $restoreditem3->dependitem);
    }
}