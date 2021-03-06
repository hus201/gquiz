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
 * Contains class mod_gquiz\output\summary
 *
 * @package   mod_gquiz
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gquiz\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use mod_gquiz_structure;

/**
 * Class to help display gquiz summary
 *
 * @package   mod_gquiz
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary implements renderable, templatable {

    /** @var mod_gquiz_structure */
    protected $gquizstructure;

    /** @var int */
    protected $mygroupid;

    /**
     * Constructor.
     *
     * @todo MDL-71494 Final deprecation of the $extradetails parameter in Moodle 4.3
     * @param mod_gquiz_structure $gquizstructure
     * @param int $mygroupid currently selected group
     * @param bool|null $extradetails Deprecated
     */
    public function __construct($gquizstructure, $mygroupid = false, $extradetails = null) {
        if (isset($extradetails)) {
            debugging('The $extradetails parameter is deprecated.', DEBUG_DEVELOPER);
        }
        $this->gquizstructure = $gquizstructure;
        $this->mygroupid = $mygroupid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $r = new stdClass();
        $r->completedcount = $this->gquizstructure->count_completed_responses($this->mygroupid);
        $r->itemscount = count($this->gquizstructure->get_items(true));

        return $r;
    }
}
