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
 * gquiz external API
 *
 * @package    mod_gquiz
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

use mod_gquiz\external\gquiz_summary_exporter;
use mod_gquiz\external\gquiz_completedtmp_exporter;
use mod_gquiz\external\gquiz_item_exporter;
use mod_gquiz\external\gquiz_valuetmp_exporter;
use mod_gquiz\external\gquiz_value_exporter;
use mod_gquiz\external\gquiz_completed_exporter;

/**
 * gquiz external functions
 *
 * @package    mod_gquiz
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class mod_gquiz_external extends external_api {

    /**
     * Describes the parameters for get_gquizs_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_gquizs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of gquizs in a provided list of courses.
     * If no list is provided all gquizs that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and gquizs
     * @since Moodle 3.3
     */
    public static function get_gquizs_by_courses($courseids = array()) {
        global $PAGE;

        $warnings = array();
        $returnedgquizs = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_gquizs_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);
            $output = $PAGE->get_renderer('core');

            // Get the gquizs in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $gquizs = get_all_instances_in_courses("gquiz", $courses);
            foreach ($gquizs as $gquiz) {

                $context = context_module::instance($gquiz->coursemodule);

                // Remove fields that are not from the gquiz (added by get_all_instances_in_courses).
                unset($gquiz->coursemodule, $gquiz->context, $gquiz->visible, $gquiz->section, $gquiz->groupmode,
                        $gquiz->groupingid);

                // Check permissions.
                if (!has_capability('mod/gquiz:edititems', $context)) {
                    // Don't return the optional properties.
                    $properties = gquiz_summary_exporter::properties_definition();
                    foreach ($properties as $property => $config) {
                        if (!empty($config['optional'])) {
                            unset($gquiz->{$property});
                        }
                    }
                }
                $exporter = new gquiz_summary_exporter($gquiz, array('context' => $context));
                $returnedgquizs[] = $exporter->export($output);
            }
        }

        $result = array(
            'gquizs' => $returnedgquizs,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_gquizs_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_gquizs_by_courses_returns() {
        return new external_single_structure(
            array(
                'gquizs' => new external_multiple_structure(
                    gquiz_summary_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a gquiz.
     *
     * @param int $gquizid gquiz instance id
     * @param int $courseid courseid course where user completes the gquiz (for site gquizs only)
     * @return array containing the gquiz, gquiz course, context, course module and the course where is being completed.
     * @throws moodle_exception
     * @since  Moodle 3.3
     */
    protected static function validate_gquiz($gquizid, $courseid = 0) {
        global $DB, $USER;

        // Request and permission validation.
        $gquiz = $DB->get_record('gquiz', array('id' => $gquizid), '*', MUST_EXIST);
        list($gquizcourse, $cm) = get_course_and_cm_from_instance($gquiz, 'gquiz');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Set default completion course.
        $completioncourse = (object) array('id' => 0);
        if ($gquizcourse->id == SITEID && $courseid) {
            $completioncourse = get_course($courseid);
            self::validate_context(context_course::instance($courseid));

            $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $courseid);
            if (!$gquizcompletion->check_course_is_mapped()) {
                throw new moodle_exception('cannotaccess', 'mod_gquiz');
            }
        }

        return array($gquiz, $gquizcourse, $cm, $context, $completioncourse);
    }

    /**
     * Utility function for validating access to gquiz.
     *
     * @param  stdClass   $gquiz gquiz object
     * @param  stdClass   $course   course where user completes the gquiz (for site gquizs only)
     * @param  stdClass   $cm       course module
     * @param  stdClass   $context  context object
     * @throws moodle_exception
     * @return mod_gquiz_completion gquiz completion instance
     * @since  Moodle 3.3
     */
    protected static function validate_gquiz_access($gquiz, $course, $cm, $context, $checksubmit = false) {
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $course->id);

        if (!$gquizcompletion->can_complete()) {
            throw new required_capability_exception($context, 'mod/gquiz:complete', 'nopermission', '');
        }

        if (!$gquizcompletion->is_open()) {
            throw new moodle_exception('gquiz_is_not_open', 'gquiz');
        }

        if ($gquizcompletion->is_empty()) {
            throw new moodle_exception('no_items_available_yet', 'gquiz');
        }

        if ($checksubmit && !$gquizcompletion->can_submit()) {
            throw new moodle_exception('this_gquiz_is_already_submitted', 'gquiz');
        }
        return $gquizcompletion;
    }

    /**
     * Describes the parameters for get_gquiz_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_gquiz_access_information_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given gquiz.
     *
     * @param int $gquizid gquiz instance id
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and the access information
     * @since Moodle 3.3
     * @throws  moodle_exception
     */
    public static function get_gquiz_access_information($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array(
            'gquizid' => $gquizid,
            'courseid' => $courseid,
        );
        $params = self::validate_parameters(self::get_gquiz_access_information_parameters(), $params);

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        $result = array();
        // Capabilities first.
        $result['canviewanalysis'] = $gquizcompletion->can_view_analysis();
        $result['cancomplete'] = $gquizcompletion->can_complete();
        $result['cansubmit'] = $gquizcompletion->can_submit();
        $result['candeletesubmissions'] = has_capability('mod/gquiz:deletesubmissions', $context);
        $result['canviewreports'] = has_capability('mod/gquiz:viewreports', $context);
        $result['canedititems'] = has_capability('mod/gquiz:edititems', $context);

        // Status information.
        $result['isempty'] = $gquizcompletion->is_empty();
        $result['isopen'] = $gquizcompletion->is_open();
        $anycourse = ($course->id == SITEID);
        $result['isalreadysubmitted'] = $gquizcompletion->is_already_submitted($anycourse);
        $result['isanonymous'] = $gquizcompletion->is_anonymous();

        $result['warnings'] = [];
        return $result;
    }

    /**
     * Describes the get_gquiz_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_gquiz_access_information_returns() {
        return new external_single_structure(
            array(
                'canviewanalysis' => new external_value(PARAM_BOOL, 'Whether the user can view the analysis or not.'),
                'cancomplete' => new external_value(PARAM_BOOL, 'Whether the user can complete the gquiz or not.'),
                'cansubmit' => new external_value(PARAM_BOOL, 'Whether the user can submit the gquiz or not.'),
                'candeletesubmissions' => new external_value(PARAM_BOOL, 'Whether the user can delete submissions or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the gquiz reports or not.'),
                'canedititems' => new external_value(PARAM_BOOL, 'Whether the user can edit gquiz items or not.'),
                'isempty' => new external_value(PARAM_BOOL, 'Whether the gquiz has questions or not.'),
                'isopen' => new external_value(PARAM_BOOL, 'Whether the gquiz has active access time restrictions or not.'),
                'isalreadysubmitted' => new external_value(PARAM_BOOL, 'Whether the gquiz is already submitted or not.'),
                'isanonymous' => new external_value(PARAM_BOOL, 'Whether the gquiz is anonymous or not.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_gquiz.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function view_gquiz_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'moduleviewed' => new external_value(PARAM_BOOL, 'If we need to mark the module as viewed for completion',
                    VALUE_DEFAULT, false),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $gquizid gquiz instance id
     * @param bool $moduleviewed If we need to mark the module as viewed for completion
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function view_gquiz($gquizid, $moduleviewed = false, $courseid = 0) {

        $params = array('gquizid' => $gquizid, 'moduleviewed' => $moduleviewed, 'courseid' => $courseid);
        $params = self::validate_parameters(self::view_gquiz_parameters(), $params);
        $warnings = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        // Trigger module viewed event.
        $gquizcompletion->trigger_module_viewed();
        if ($params['moduleviewed']) {
            if (!$gquizcompletion->is_open()) {
                throw new moodle_exception('gquiz_is_not_open', 'gquiz');
            }
            // Mark activity viewed for completion-tracking.
            $gquizcompletion->set_module_viewed();
        }

        $result = array(
            'status' => true,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the view_gquiz return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function view_gquiz_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_current_completed_tmp.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_current_completed_tmp_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns the temporary completion record for the current user.
     *
     * @param int $gquizid gquiz instance id
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_current_completed_tmp($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_current_completed_tmp_parameters(), $params);
        $warnings = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        if ($completed = $gquizcompletion->get_current_completed_tmp()) {
            $exporter = new gquiz_completedtmp_exporter($completed);
            return array(
                'gquiz' => $exporter->export($PAGE->get_renderer('core')),
                'warnings' => $warnings,
            );
        }
        throw new moodle_exception('not_started', 'gquiz');
    }

    /**
     * Describes the get_current_completed_tmp return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_current_completed_tmp_returns() {
        return new external_single_structure(
            array(
                'gquiz' => gquiz_completedtmp_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_items_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns the items (questions) in the given gquiz.
     *
     * @param int $gquizid gquiz instance id
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and gquizs
     * @since Moodle 3.3
     */
    public static function get_items($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_items_parameters(), $params);
        $warnings = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);

        $gquizstructure = new mod_gquiz_structure($gquiz, $cm, $completioncourse->id);
        $returneditems = array();
        if ($items = $gquizstructure->get_items()) {
            foreach ($items as $item) {
                $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
                unset($item->itemnr);   // Added by the function, not part of the record.
                $exporter = new gquiz_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));
                $returneditems[] = $exporter->export($PAGE->get_renderer('core'));
            }
        }

        $result = array(
            'items' => $returneditems,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_items_returns() {
        return new external_single_structure(
            array(
                'items' => new external_multiple_structure(
                    gquiz_item_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for launch_gquiz.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function launch_gquiz_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Starts or continues a gquiz submission
     *
     * @param array $gquizid gquiz instance id
     * @param int $courseid course where user completes a gquiz (for site gquizs only).
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function launch_gquiz($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::launch_gquiz_parameters(), $params);
        $warnings = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        // Check we can do a new submission (or continue an existing).
        $gquizcompletion = self::validate_gquiz_access($gquiz, $completioncourse, $cm, $context, true);

        $gopage = $gquizcompletion->get_resume_page();
        if ($gopage === null) {
            $gopage = -1; // Last page.
        }

        $result = array(
            'gopage' => $gopage,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the launch_gquiz return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function launch_gquiz_returns() {
        return new external_single_structure(
            array(
                'gopage' => new external_value(PARAM_INT, 'The next page to go (-1 if we were already in the last page). 0 for first page.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_page_items_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'page' => new external_value(PARAM_INT, 'The page to get starting by 0'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get a single gquiz page items.
     *
     * @param int $gquizid gquiz instance id
     * @param int $page the page to get starting by 0
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_page_items($gquizid, $page, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'page' => $page, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_page_items_parameters(), $params);
        $warnings = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);

        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        $page = $params['page'];
        $pages = $gquizcompletion->get_pages();
        $pageitems = $pages[$page];
        $hasnextpage = $page < count($pages) - 1; // Until we complete this page we can not trust get_next_page().
        $hasprevpage = $page && ($gquizcompletion->get_previous_page($page, false) !== null);

        $returneditems = array();
        foreach ($pageitems as $item) {
            $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
            unset($item->itemnr);   // Added by the function, not part of the record.
            $exporter = new gquiz_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));
            $returneditems[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'items' => $returneditems,
            'hasprevpage' => $hasprevpage,
            'hasnextpage' => $hasnextpage,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_page_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_page_items_returns() {
        return new external_single_structure(
            array(
                'items' => new external_multiple_structure(
                    gquiz_item_exporter::get_read_structure()
                ),
                'hasprevpage' => new external_value(PARAM_BOOL, 'Whether is a previous page.'),
                'hasnextpage' => new external_value(PARAM_BOOL, 'Whether there are more pages.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function process_page_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id.'),
                'page' => new external_value(PARAM_INT, 'The page being processed.'),
                'responses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_NOTAGS, 'The response name (usually type[index]_id).'),
                            'value' => new external_value(PARAM_RAW, 'The response value.'),
                        )
                    ), 'The data to be processed.', VALUE_DEFAULT, array()
                ),
                'goprevious' => new external_value(PARAM_BOOL, 'Whether we want to jump to previous page.', VALUE_DEFAULT, false),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Process a jump between pages.
     *
     * @param array $gquizid gquiz instance id
     * @param array $page the page being processed
     * @param array $responses the responses to be processed
     * @param bool $goprevious whether we want to jump to previous page
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function process_page($gquizid, $page, $responses = [], $goprevious = false, $courseid = 0) {
        global $USER, $SESSION;

        $params = array('gquizid' => $gquizid, 'page' => $page, 'responses' => $responses, 'goprevious' => $goprevious,
            'courseid' => $courseid);
        $params = self::validate_parameters(self::process_page_parameters(), $params);
        $warnings = array();
        $siteaftersubmit = $completionpagecontents = '';

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        // Check we can do a new submission (or continue an existing).
        $gquizcompletion = self::validate_gquiz_access($gquiz, $completioncourse, $cm, $context, true);

        // Create the $_POST object required by the gquiz question engine.
        $_POST = array();
        foreach ($responses as $response) {
            // First check if we are handling array parameters.
            if (preg_match('/(.+)\[(.+)\]$/', $response['name'], $matches)) {
                $_POST[$matches[1]][$matches[2]] = $response['value'];
            } else {
                $_POST[$response['name']] = $response['value'];
            }
        }
        // Force fields.
        $_POST['id'] = $cm->id;
        $_POST['courseid'] = $courseid;
        $_POST['gopage'] = $params['page'];
        $_POST['_qf__mod_gquiz_complete_form'] = 1;

        // Determine where to go, backwards or forward.
        if (!$params['goprevious']) {
            $_POST['gonextpage'] = 1;   // Even if we are saving values we need this set.
            if ($gquizcompletion->get_next_page($params['page'], false) === null) {
                $_POST['savevalues'] = 1;   // If there is no next page, it means we are finishing the gquiz.
            }
        }

        // Ignore sesskey (deep in some APIs), the request is already validated.
        $USER->ignoresesskey = true;
        gquiz_init_gquiz_session();
        $SESSION->gquiz->is_started = true;

        $gquizcompletion->process_page($params['page'], $params['goprevious']);
        $completed = $gquizcompletion->just_completed();
        if ($completed) {
            $jumpto = 0;
            if ($gquiz->page_after_submit) {
                $completionpagecontents = $gquizcompletion->page_after_submit();
            }

            if ($gquiz->site_after_submit) {
                $siteaftersubmit = gquiz_encode_target_url($gquiz->site_after_submit);
            }
        } else {
            $jumpto = $gquizcompletion->get_jumpto();
        }

        $result = array(
            'jumpto' => $jumpto,
            'completed' => $completed,
            'completionpagecontents' => $completionpagecontents,
            'siteaftersubmit' => $siteaftersubmit,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the process_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function process_page_returns() {
        return new external_single_structure(
            array(
                'jumpto' => new external_value(PARAM_INT, 'The page to jump to.'),
                'completed' => new external_value(PARAM_BOOL, 'If the user completed the gquiz.'),
                'completionpagecontents' => new external_value(PARAM_RAW, 'The completion page contents.'),
                'siteaftersubmit' => new external_value(PARAM_RAW, 'The link (could be relative) to show after submit.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_analysis.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_analysis_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves the gquiz analysis.
     *
     * @param array $gquizid gquiz instance id
     * @param int $groupid group id, 0 means that the function will determine the user group
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_analysis($gquizid, $groupid = 0, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'groupid' => $groupid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_analysis_parameters(), $params);
        $warnings = $itemsdata = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);

        // Check permissions.
        $gquizstructure = new mod_gquiz_structure($gquiz, $cm, $completioncourse->id);
        if (!$gquizstructure->can_view_analysis()) {
            throw new required_capability_exception($context, 'mod/gquiz:viewanalysepage', 'nopermission', '');
        }

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        // Summary data.
        $summary = new mod_gquiz\output\summary($gquizstructure, $groupid);
        $summarydata = $summary->export_for_template($PAGE->get_renderer('core'));

        $checkanonymously = true;
        if ($groupid > 0 AND $gquiz->anonymous == gquiz_ANONYMOUS_YES) {
            $completedcount = $gquizstructure->count_completed_responses($groupid);
            if ($completedcount < gquiz_MIN_ANONYMOUS_COUNT_IN_GROUP) {
                $checkanonymously = false;
            }
        }

        if ($checkanonymously) {
            // Get the items of the gquiz.
            $items = $gquizstructure->get_items(true);
            foreach ($items as $item) {
                $itemobj = gquiz_get_item_class($item->typ);
                $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
                unset($item->itemnr);   // Added by the function, not part of the record.
                $exporter = new gquiz_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));

                $itemsdata[] = array(
                    'item' => $exporter->export($PAGE->get_renderer('core')),
                    'data' => $itemobj->get_analysed_for_external($item, $groupid),
                );
            }
        } else {
            $warnings[] = array(
                'item' => 'gquiz',
                'itemid' => $gquiz->id,
                'warningcode' => 'insufficientresponsesforthisgroup',
                'message' => s(get_string('insufficient_responses_for_this_group', 'gquiz'))
            );
        }

        $result = array(
            'completedcount' => $summarydata->completedcount,
            'itemscount' => $summarydata->itemscount,
            'itemsdata' => $itemsdata,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_analysis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_analysis_returns() {
        return new external_single_structure(
            array(
            'completedcount' => new external_value(PARAM_INT, 'Number of completed submissions.'),
            'itemscount' => new external_value(PARAM_INT, 'Number of items (questions).'),
            'itemsdata' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'item' => gquiz_item_exporter::get_read_structure(),
                        'data' => new external_multiple_structure(
                            new external_value(PARAM_RAW, 'The analysis data (can be json encoded)')
                        ),
                    )
                )
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_unfinished_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves responses from the current unfinished attempt.
     *
     * @param array $gquizid gquiz instance id
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_unfinished_responses_parameters(), $params);
        $warnings = $itemsdata = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        $responses = array();
        $unfinished = $gquizcompletion->get_unfinished_responses();
        foreach ($unfinished as $u) {
            $exporter = new gquiz_valuetmp_exporter($u);
            $responses[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'responses' => $responses,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_unfinished_responses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses_returns() {
        return new external_single_structure(
            array(
            'responses' => new external_multiple_structure(
                gquiz_valuetmp_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_finished_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_finished_responses_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves responses from the last finished attempt.
     *
     * @param array $gquizid gquiz instance id
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and the responses
     * @since Moodle 3.3
     */
    public static function get_finished_responses($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_finished_responses_parameters(), $params);
        $warnings = $itemsdata = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        $responses = array();
        // Load and get the responses from the last completed gquiz.
        $gquizcompletion->find_last_completed();
        $unfinished = $gquizcompletion->get_finished_responses();
        foreach ($unfinished as $u) {
            $exporter = new gquiz_value_exporter($u);
            $responses[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'responses' => $responses,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_finished_responses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_finished_responses_returns() {
        return new external_single_structure(
            array(
            'responses' => new external_multiple_structure(
                gquiz_value_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_non_respondents.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_non_respondents_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group.',
                                                VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_ALPHA, 'Sort param, must be firstname, lastname or lastaccess (default).',
                                                VALUE_DEFAULT, 'lastaccess'),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page.', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves a list of students who didn't submit the gquiz.
     *
     * @param int $gquizid gquiz instance id
     * @param int $groupid Group id, 0 means that the function will determine the user group'
     * @param str $sort sort param, must be firstname, lastname or lastaccess (default)
     * @param int $page the page of records to return
     * @param int $perpage the number of records to return per page
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and users ids
     * @since Moodle 3.3
     */
    public static function get_non_respondents($gquizid, $groupid = 0, $sort = 'lastaccess', $page = 0, $perpage = 0,
            $courseid = 0) {

        global $CFG;
        require_once($CFG->dirroot . '/mod/gquiz/lib.php');

        $params = array('gquizid' => $gquizid, 'groupid' => $groupid, 'sort' => $sort, 'page' => $page,
            'perpage' => $perpage, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_non_respondents_parameters(), $params);
        $warnings = $nonrespondents = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);
        $completioncourseid = $gquizcompletion->get_courseid();

        if ($gquiz->anonymous != gquiz_ANONYMOUS_NO || $gquiz->course == SITEID) {
            throw new moodle_exception('anonymous', 'gquiz');
        }

        // Check permissions.
        require_capability('mod/gquiz:viewreports', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        if ($params['sort'] !== 'firstname' && $params['sort'] !== 'lastname' && $params['sort'] !== 'lastaccess') {
            throw new invalid_parameter_exception('Invalid sort param, must be firstname, lastname or lastaccess.');
        }

        // Check if we are page filtering.
        if ($params['perpage'] == 0) {
            $page = $params['page'];
            $perpage = gquiz_DEFAULT_PAGE_COUNT;
        } else {
            $perpage = $params['perpage'];
            $page = $perpage * $params['page'];
        }
        $users = gquiz_get_incomplete_users($cm, $groupid, $params['sort'], $page, $perpage, true);
        foreach ($users as $user) {
            $nonrespondents[] = [
                'courseid' => $completioncourseid,
                'userid'   => $user->id,
                'fullname' => fullname($user),
                'started'  => $user->gquizstarted
            ];
        }

        $result = array(
            'users' => $nonrespondents,
            'total' => gquiz_count_incomplete_users($cm, $groupid),
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_non_respondents return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_non_respondents_returns() {
        return new external_single_structure(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'userid' => new external_value(PARAM_INT, 'The user id'),
                            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'started' => new external_value(PARAM_BOOL, 'If the user has started the attempt'),
                        )
                    )
                ),
                'total' => new external_value(PARAM_INT, 'Total number of non respondents'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_responses_analysis.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_responses_analysis_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return the gquiz user responses.
     *
     * @param int $gquizid gquiz instance id
     * @param int $groupid Group id, 0 means that the function will determine the user group
     * @param int $page the page of records to return
     * @param int $perpage the number of records to return per page
     * @param int $courseid course where user completes the gquiz (for site gquizs only)
     * @return array of warnings and users attemps and responses
     * @throws moodle_exception
     * @since Moodle 3.3
     */
    public static function get_responses_analysis($gquizid, $groupid = 0, $page = 0, $perpage = 0, $courseid = 0) {

        $params = array('gquizid' => $gquizid, 'groupid' => $groupid, 'page' => $page, 'perpage' => $perpage,
            'courseid' => $courseid);
        $params = self::validate_parameters(self::get_responses_analysis_parameters(), $params);
        $warnings = $itemsdata = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);

        // Check permissions.
        require_capability('mod/gquiz:viewreports', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        $gquizstructure = new mod_gquiz_structure($gquiz, $cm, $completioncourse->id);
        $responsestable = new mod_gquiz_responses_table($gquizstructure, $groupid);
        // Ensure responses number is correct prior returning them.
        $gquizstructure->shuffle_anonym_responses();
        $anonresponsestable = new mod_gquiz_responses_anon_table($gquizstructure, $groupid);

        $result = array(
            'attempts'          => $responsestable->export_external_structure($params['page'], $params['perpage']),
            'totalattempts'     => $responsestable->get_total_responses_count(),
            'anonattempts'      => $anonresponsestable->export_external_structure($params['page'], $params['perpage']),
            'totalanonattempts' => $anonresponsestable->get_total_responses_count(),
            'warnings'       => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_responses_analysis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_responses_analysis_returns() {
        $responsestructure = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Response id'),
                    'name' => new external_value(PARAM_RAW, 'Response name'),
                    'printval' => new external_value(PARAM_RAW, 'Response ready for output'),
                    'rawval' => new external_value(PARAM_RAW, 'Response raw value'),
                )
            )
        );

        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Completed id'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'userid' => new external_value(PARAM_INT, 'User who responded'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified for the response'),
                            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'responses' => $responsestructure
                        )
                    )
                ),
                'totalattempts' => new external_value(PARAM_INT, 'Total responses count.'),
                'anonattempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Completed id'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'number' => new external_value(PARAM_INT, 'Response number'),
                            'responses' => $responsestructure
                        )
                    )
                ),
                'totalanonattempts' => new external_value(PARAM_INT, 'Total anonymous responses count.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_last_completed.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_last_completed_parameters() {
        return new external_function_parameters (
            array(
                'gquizid' => new external_value(PARAM_INT, 'gquiz instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the gquiz (for site gquizs only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves the last completion record for the current user.
     *
     * @param int $gquizid gquiz instance id
     * @return array of warnings and the last completed record
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_last_completed($gquizid, $courseid = 0) {
        global $PAGE;

        $params = array('gquizid' => $gquizid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_last_completed_parameters(), $params);
        $warnings = array();

        list($gquiz, $course, $cm, $context, $completioncourse) = self::validate_gquiz($params['gquizid'],
            $params['courseid']);
        $gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $completioncourse->id);

        if ($gquizcompletion->is_anonymous()) {
             throw new moodle_exception('anonymous', 'gquiz');
        }
        if ($completed = $gquizcompletion->find_last_completed()) {
            $exporter = new gquiz_completed_exporter($completed);
            return array(
                'completed' => $exporter->export($PAGE->get_renderer('core')),
                'warnings' => $warnings,
            );
        }
        throw new moodle_exception('not_completed_yet', 'gquiz');
    }

    /**
     * Describes the get_last_completed return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_last_completed_returns() {
        return new external_single_structure(
            array(
                'completed' => gquiz_completed_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }
}
