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
 * Unit tests for (some of) mod/gquiz/lib.php.
 *
 * @package    mod_gquiz
 * @copyright  2016 Stephen Bourget
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/gquiz/lib.php');

/**
 * Unit tests for (some of) mod/gquiz/lib.php.
 *
 * @copyright  2016 Stephen Bourget
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_gquiz_lib_testcase extends advanced_testcase {

    public function test_gquiz_initialise() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $params['course'] = $course->id;
        $params['timeopen'] = time() - 5 * MINSECS;
        $params['timeclose'] = time() + DAYSECS;
        $params['anonymous'] = 1;
        $params['intro'] = 'Some introduction text';
        $gquiz = $this->getDataGenerator()->create_module('gquiz', $params);

        // Test different ways to construct the structure object.
        $pseudocm = get_coursemodule_from_instance('gquiz', $gquiz->id); // Object similar to cm_info.
        $cm = get_fast_modinfo($course)->instances['gquiz'][$gquiz->id]; // Instance of cm_info.

        $constructorparams = [
            [$gquiz, null],
            [null, $pseudocm],
            [null, $cm],
            [$gquiz, $pseudocm],
            [$gquiz, $cm],
        ];

        foreach ($constructorparams as $params) {
            $structure = new mod_gquiz_completion($params[0], $params[1], 0);
            $this->assertTrue($structure->is_open());
            $this->assertTrue($structure->get_cm() instanceof cm_info);
            $this->assertEquals($gquiz->cmid, $structure->get_cm()->id);
            $this->assertEquals($gquiz->intro, $structure->get_gquiz()->intro);
        }
    }

    /**
     * Tests for mod_gquiz_refresh_events.
     */
    public function test_gquiz_refresh_events() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $timeopen = time();
        $timeclose = time() + 86400;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_gquiz');
        $params['course'] = $course->id;
        $params['timeopen'] = $timeopen;
        $params['timeclose'] = $timeclose;
        $gquiz = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
        $context = context_module::instance($cm->id);

        // Normal case, with existing course.
        $this->assertTrue(gquiz_refresh_events($course->id));
        $eventparams = array('modulename' => 'gquiz', 'instance' => $gquiz->id, 'eventtype' => 'open');
        $openevent = $DB->get_record('event', $eventparams, '*', MUST_EXIST);
        $this->assertEquals($openevent->timestart, $timeopen);

        $eventparams = array('modulename' => 'gquiz', 'instance' => $gquiz->id, 'eventtype' => 'close');
        $closeevent = $DB->get_record('event', $eventparams, '*', MUST_EXIST);
        $this->assertEquals($closeevent->timestart, $timeclose);
        // In case the course ID is passed as a numeric string.
        $this->assertTrue(gquiz_refresh_events('' . $course->id));
        // Course ID not provided.
        $this->assertTrue(gquiz_refresh_events());
        $eventparams = array('modulename' => 'gquiz');
        $events = $DB->get_records('event', $eventparams);
        foreach ($events as $event) {
            if ($event->modulename === 'gquiz' && $event->instance === $gquiz->id && $event->eventtype === 'open') {
                $this->assertEquals($event->timestart, $timeopen);
            }
            if ($event->modulename === 'gquiz' && $event->instance === $gquiz->id && $event->eventtype === 'close') {
                $this->assertEquals($event->timestart, $timeclose);
            }
        }
    }

    /**
     * Test check_updates_since callback.
     */
    public function test_check_updates_since() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        // Create user.
        $student = self::getDataGenerator()->create_user();

        // User enrolment.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');

        $this->setCurrentTimeStart();
        $record = array(
            'course' => $course->id,
            'custom' => 0,
            'gquiz' => 1,
        );
        $gquiz = $this->getDataGenerator()->create_module('gquiz', $record);
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id, $course->id);
        $cm = cm_info::create($cm);

        $this->setUser($student);
        // Check that upon creation, the updates are only about the new configuration created.
        $onehourago = time() - HOURSECS;
        $updates = gquiz_check_updates_since($cm, $onehourago);
        foreach ($updates as $el => $val) {
            if ($el == 'configuration') {
                $this->assertTrue($val->updated);
                $this->assertTimeCurrent($val->timeupdated);
            } else {
                $this->assertFalse($val->updated);
            }
        }

        $record = [
            'gquiz' => $gquiz->id,
            'userid' => $student->id,
            'timemodified' => time(),
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_NO,
            'courseid' => $course->id,
        ];
        $DB->insert_record('gquiz_completed', (object)$record);
        $DB->insert_record('gquiz_completedtmp', (object)$record);

        // Check now for finished and unfinished attempts.
        $updates = gquiz_check_updates_since($cm, $onehourago);
        $this->assertTrue($updates->attemptsunfinished->updated);
        $this->assertCount(1, $updates->attemptsunfinished->itemids);

        $this->assertTrue($updates->attemptsfinished->updated);
        $this->assertCount(1, $updates->attemptsfinished->itemids);
    }

    /**
     * Test calendar event provide action open.
     */
    public function test_gquiz_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id,
                'timeopen' => $now - DAYSECS, 'timeclose' => $now + DAYSECS]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);

        $factory = new \core_calendar\action_factory();
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('answerquestions', 'gquiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * Test calendar event provide action open, viewed by a different user.
     */
    public function test_gquiz_core_calendar_provide_event_action_open_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id, 'manual');

        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id,
            'timeopen' => $now - DAYSECS, 'timeclose' => $now + DAYSECS]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);
        $factory = new \core_calendar\action_factory();

        $this->setUser($user2);

        // User2 checking their events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user2->id);
        $this->assertNull($actionevent);

        // User2 checking $user's events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user->id);
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('answerquestions', 'gquiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * Test calendar event provide action closed.
     */
    public function test_gquiz_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => $course->id,
                'timeclose' => time() - DAYSECS));
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);

        $factory = new \core_calendar\action_factory();
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        // No event on the dashboard if gquiz is closed.
        $this->assertNull($actionevent);
    }

    /**
     * Test calendar event provide action closed, viewed by a different user.
     */
    public function test_gquiz_core_calendar_provide_event_action_closed_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id, 'manual');

        $gquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => $course->id,
            'timeclose' => time() - DAYSECS));
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);
        $factory = new \core_calendar\action_factory();
        $this->setUser($user2);

        // User2 checking their events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user2->id);
        $this->assertNull($actionevent);

        // User2 checking $user's events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user->id);

        // No event on the dashboard if gquiz is closed.
        $this->assertNull($actionevent);
    }

    /**
     * Test calendar event action open in future.
     *
     * @throws coding_exception
     */
    public function test_gquiz_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id,
                'timeopen' => time() + DAYSECS]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);

        $factory = new \core_calendar\action_factory();
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('answerquestions', 'gquiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    /**
     * Test calendar event action open in future, viewed by a different user.
     *
     * @throws coding_exception
     */
    public function test_gquiz_core_calendar_provide_event_action_open_in_future_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id, 'manual');

        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id,
            'timeopen' => time() + DAYSECS]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);

        $factory = new \core_calendar\action_factory();
        $this->setUser($user2);

        // User2 checking their events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user2->id);
        $this->assertNull($actionevent);

        // User2 checking $user's events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user->id);

        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('answerquestions', 'gquiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    /**
     * Test calendar event with no time specified.
     *
     * @throws coding_exception
     */
    public function test_gquiz_core_calendar_provide_event_action_no_time_specified() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);

        $factory = new \core_calendar\action_factory();
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('answerquestions', 'gquiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * Test calendar event with no time specified, viewed by a different user.
     *
     * @throws coding_exception
     */
    public function test_gquiz_core_calendar_provide_event_action_no_time_specified_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id, 'manual');

        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);

        $factory = new \core_calendar\action_factory();
        $this->setUser($user2);

        // User2 checking their events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user2->id);
        $this->assertNull($actionevent);

        // User2 checking $user's events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user->id);

        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('answerquestions', 'gquiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * A user that can not submit gquiz should not have an action.
     */
    public function test_gquiz_core_calendar_provide_event_action_can_not_submit() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
        $context = context_module::instance($cm->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id, 'manual');

        $this->setUser($user);
        assign_capability('mod/gquiz:complete', CAP_PROHIBIT, $studentrole->id, $context);

        $factory = new \core_calendar\action_factory();
        $action = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        $this->assertNull($action);
    }

    /**
     * A user that can not submit gquiz should not have an action, viewed by a different user.
     */
    public function test_gquiz_core_calendar_provide_event_action_can_not_submit_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
        $context = context_module::instance($cm->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id, 'manual');

        assign_capability('mod/gquiz:complete', CAP_PROHIBIT, $studentrole->id, $context);
        $factory = new \core_calendar\action_factory();
        $this->setUser($user2);

        // User2 checking their events.

        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user2->id);
        $this->assertNull($actionevent);

        // User2 checking $user's events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user->id);

        $this->assertNull($actionevent);
    }

    /**
     * A user that has already submitted gquiz should not have an action.
     */
    public function test_gquiz_core_calendar_provide_event_action_already_submitted() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
        $context = context_module::instance($cm->id);

        $this->setUser($user);

        $record = [
            'gquiz' => $gquiz->id,
            'userid' => $user->id,
            'timemodified' => time(),
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_NO,
            'courseid' => 0,
        ];
        $DB->insert_record('gquiz_completed', (object) $record);

        $factory = new \core_calendar\action_factory();
        $action = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        $this->assertNull($action);
    }

    /**
     * A user that has already submitted gquiz should not have an action, viewed by a different user.
     */
    public function test_gquiz_core_calendar_provide_event_action_already_submitted_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $course = $this->getDataGenerator()->create_course();
        $gquiz = $this->getDataGenerator()->create_module('gquiz', ['course' => $course->id]);
        $event = $this->create_action_event($course->id, $gquiz->id, gquiz_EVENT_TYPE_OPEN);
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
        $context = context_module::instance($cm->id);

        $this->setUser($user);

        $record = [
            'gquiz' => $gquiz->id,
            'userid' => $user->id,
            'timemodified' => time(),
            'random_response' => 0,
            'anonymous_response' => gquiz_ANONYMOUS_NO,
            'courseid' => 0,
        ];
        $DB->insert_record('gquiz_completed', (object) $record);

        $factory = new \core_calendar\action_factory();
        $this->setUser($user2);

        // User2 checking their events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user2->id);
        $this->assertNull($actionevent);

        // User2 checking $user's events.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $user->id);

        $this->assertNull($actionevent);
    }

    public function test_gquiz_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $gquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $gquiz->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_gquiz_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $gquiz = $this->getDataGenerator()->create_module('gquiz', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $gquiz->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_gquiz_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid The course id.
     * @param int $instanceid The gquiz id.
     * @param string $eventtype The event type. eg. gquiz_EVENT_TYPE_OPEN.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new stdClass();
        $event->name = 'Calendar event';
        $event->modulename = 'gquiz';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_gquiz_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $gquiz1 = $this->getDataGenerator()->create_module('gquiz', [
            'course' => $course->id,
            'completion' => 2,
            'completionsubmit' => 1
        ]);
        $gquiz2 = $this->getDataGenerator()->create_module('gquiz', [
            'course' => $course->id,
            'completion' => 2,
            'completionsubmit' => 0
        ]);
        $cm1 = cm_info::create(get_coursemodule_from_instance('gquiz', $gquiz1->id));
        $cm2 = cm_info::create(get_coursemodule_from_instance('gquiz', $gquiz2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new stdClass();
        $moddefaults->customdata = ['customcompletionrules' => ['completionsubmit' => 1]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [get_string('completionsubmit', 'gquiz')];
        $this->assertEquals(mod_gquiz_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_gquiz_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_gquiz_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_gquiz_get_completion_active_rule_descriptions(new stdClass()), []);
    }

    /**
     * An unknown event should not have min or max restrictions.
     */
    public function test_get_valid_event_timestart_range_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $DB->update_record('gquiz', $gquiz);

        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => 'SOME UNKNOWN EVENT',
            'timestart' => $timeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list($min, $max) = mod_gquiz_core_calendar_get_valid_event_timestart_range($event, $gquiz);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * A gquiz_EVENT_TYPE_OPEN should have a max timestart equal to the activity
     * close time.
     */
    public function test_get_valid_event_timestart_range_event_type_open() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $DB->update_record('gquiz', $gquiz);

        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_OPEN,
            'timestart' => $timeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list($min, $max) = mod_gquiz_core_calendar_get_valid_event_timestart_range($event, $gquiz);
        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);
        $this->assertNotEmpty($max[1]);
    }

    /**
     * A gquiz_EVENT_TYPE_OPEN should not have a max timestamp if the activity
     * doesn't have a close date.
     */
    public function test_get_valid_event_timestart_range_event_type_open_no_close() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = 0;
        $DB->update_record('gquiz', $gquiz);

        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_OPEN,
            'timestart' => $timeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list($min, $max) = mod_gquiz_core_calendar_get_valid_event_timestart_range($event, $gquiz);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * A gquiz_EVENT_TYPE_CLOSE should have a min timestart equal to the activity
     * open time.
     */
    public function test_get_valid_event_timestart_range_event_type_close() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $DB->update_record('gquiz', $gquiz);

        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_CLOSE,
            'timestart' => $timeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list($min, $max) = mod_gquiz_core_calendar_get_valid_event_timestart_range($event, $gquiz);
        $this->assertEquals($timeopen, $min[0]);
        $this->assertNotEmpty($min[1]);
        $this->assertNull($max);
    }

    /**
     * A gquiz_EVENT_TYPE_CLOSE should not have a minimum timestamp if the activity
     * doesn't have an open date.
     */
    public function test_get_valid_event_timestart_range_event_type_close_no_open() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = 0;
        $gquiz->timeclose = $timeclose;
        $DB->update_record('gquiz', $gquiz);

        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_CLOSE,
            'timestart' => $timeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list($min, $max) = mod_gquiz_core_calendar_get_valid_event_timestart_range($event, $gquiz);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * An unkown event type should not change the gquiz instance.
     */
    public function test_mod_gquiz_core_calendar_event_timestart_updated_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $DB->update_record('gquiz', $gquiz);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_gquiz_core_calendar_event_timestart_updated($event, $gquiz);

        $gquiz = $DB->get_record('gquiz', ['id' => $gquiz->id]);
        $this->assertEquals($timeopen, $gquiz->timeopen);
        $this->assertEquals($timeclose, $gquiz->timeclose);
    }

    /**
     * A gquiz_EVENT_TYPE_OPEN event should update the timeopen property of
     * the gquiz activity.
     */
    public function test_mod_gquiz_core_calendar_event_timestart_updated_open_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $gquiz->timemodified = $timemodified;
        $DB->update_record('gquiz', $gquiz);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_gquiz_core_calendar_event_timestart_updated($event, $gquiz);

        $gquiz = $DB->get_record('gquiz', ['id' => $gquiz->id]);
        // Ensure the timeopen property matches the event timestart.
        $this->assertEquals($newtimeopen, $gquiz->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $gquiz->timeclose);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $gquiz->timemodified);
    }

    /**
     * A gquiz_EVENT_TYPE_CLOSE event should update the timeclose property of
     * the gquiz activity.
     */
    public function test_mod_gquiz_core_calendar_event_timestart_updated_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $gquiz->timemodified = $timemodified;
        $DB->update_record('gquiz', $gquiz);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_gquiz_core_calendar_event_timestart_updated($event, $gquiz);

        $gquiz = $DB->get_record('gquiz', ['id' => $gquiz->id]);
        // Ensure the timeclose property matches the event timestart.
        $this->assertEquals($newtimeclose, $gquiz->timeclose);
        // Ensure the timeopen isn't changed.
        $this->assertEquals($timeopen, $gquiz->timeopen);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $gquiz->timemodified);
    }

    /**
     * If a student somehow finds a way to update the calendar event
     * then the callback should not be executed to update the activity
     * properties as well because that would be a security issue.
     */
    public function test_student_role_cant_update_time_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $roleid = $generator->create_role();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $gquiz->timemodified = $timemodified;
        $DB->update_record('gquiz', $gquiz);

        $generator->enrol_user($user->id, $course->id, 'student');
        $generator->role_assign($roleid, $user->id, $context->id);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => $user->id,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        assign_capability('moodle/calendar:manageentries', CAP_ALLOW, $roleid, $context, true);
        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $roleid, $context, true);

        $this->setUser($user);

        mod_gquiz_core_calendar_event_timestart_updated($event, $gquiz);

        $newgquiz = $DB->get_record('gquiz', ['id' => $gquiz->id]);
        // The activity shouldn't have been updated because the user
        // doesn't have permissions to do it.
        $this->assertEquals($timeclose, $newgquiz->timeclose);
    }

    /**
     * The activity should update if a teacher modifies the calendar
     * event.
     */
    public function test_teacher_role_can_update_time_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $roleid = $generator->create_role();
        $gquizgenerator = $generator->get_plugin_generator('mod_gquiz');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $gquiz = $gquizgenerator->create_instance(['course' => $course->id]);
        $gquiz->timeopen = $timeopen;
        $gquiz->timeclose = $timeclose;
        $gquiz->timemodified = $timemodified;
        $DB->update_record('gquiz', $gquiz);

        $generator->enrol_user($user->id, $course->id, 'teacher');
        $generator->role_assign($roleid, $user->id, $context->id);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => $user->id,
            'modulename' => 'gquiz',
            'instance' => $gquiz->id,
            'eventtype' => gquiz_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        assign_capability('moodle/calendar:manageentries', CAP_ALLOW, $roleid, $context, true);
        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleid, $context, true);

        $this->setUser($user);

        $sink = $this->redirectEvents();

        mod_gquiz_core_calendar_event_timestart_updated($event, $gquiz);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $newgquiz = $DB->get_record('gquiz', ['id' => $gquiz->id]);
        // The activity should have been updated because the user
        // has permissions to do it.
        $this->assertEquals($newtimeclose, $newgquiz->timeclose);
        // A course_module_updated event should be fired if the module
        // was successfully modified.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create an gquiz.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_gquiz');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'timeopen' => $time + 200,
            'timeclose' => $time + 2000,
        );
        $generator->create_instance($params);
    }
}
