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
 * gquiz external functions and service definitions.
 *
 * @package    mod_gquiz
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_gquiz_get_gquizs_by_courses' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_gquizs_by_courses',
        'description'   => 'Returns a list of gquizs in a provided list of courses, if no list is provided all gquizs that
                            the user can view will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_gquiz_get_gquiz_access_information' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_gquiz_access_information',
        'description'   => 'Return access information for a given gquiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_gquiz_view_gquiz' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'view_gquiz',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_current_completed_tmp' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_current_completed_tmp',
        'description'   => 'Returns the temporary completion record for the current user.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_items' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_items',
        'description'   => 'Returns the items (questions) in the given gquiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_launch_gquiz' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'launch_gquiz',
        'description'   => 'Starts or continues a gquiz submission.',
        'type'          => 'write',
        'capabilities'  => 'mod/gquiz:complete',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_page_items' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_page_items',
        'description'   => 'Get a single gquiz page items.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:complete',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_process_page' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'process_page',
        'description'   => 'Process a jump between pages.',
        'type'          => 'write',
        'capabilities'  => 'mod/gquiz:complete',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_analysis' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_analysis',
        'description'   => 'Retrieves the gquiz analysis.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:viewanalysepage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_unfinished_responses' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_unfinished_responses',
        'description'   => 'Retrieves responses from the current unfinished attempt.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_finished_responses' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_finished_responses',
        'description'   => 'Retrieves responses from the last finished attempt.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_non_respondents' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_non_respondents',
        'description'   => 'Retrieves a list of students who didn\'t submit the gquiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:viewreports',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_responses_analysis' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_responses_analysis',
        'description'   => 'Return the gquiz user responses analysis.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:viewreports',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_gquiz_get_last_completed' => array(
        'classname'     => 'mod_gquiz_external',
        'methodname'    => 'get_last_completed',
        'description'   => 'Retrieves the last completion record for the current user.',
        'type'          => 'read',
        'capabilities'  => 'mod/gquiz:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
