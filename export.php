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
 * prints the form to export the items as xml-file
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_gquiz
 */

require_once("../../config.php");
require_once("lib.php");

// get parameters
$id = required_param('id', PARAM_INT);
$action = optional_param('action', false, PARAM_ALPHA);

$url = new moodle_url('/mod/gquiz/export.php', array('id'=>$id));
if ($action !== false) {
    $url->param('action', $action);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('gquiz', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $gquiz = $DB->get_record("gquiz", array("id"=>$cm->instance))) {
    print_error('invalidcoursemodule');
}

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

require_capability('mod/gquiz:edititems', $context);

if ($action == 'exportfile') {
    if (!$exportdata = gquiz_get_xml_data($gquiz->id)) {
        print_error('nodata');
    }
    @gquiz_send_xml_data($exportdata, 'gquiz_'.$gquiz->id.'.xml');
    exit;
}

redirect('view.php?id='.$id);
exit;

function gquiz_get_xml_data($gquizid) {
    global $DB;

    $space = '     ';
    //get all items of the gquiz
    if (!$items = $DB->get_records('gquiz_item', array('gquiz'=>$gquizid), 'position')) {
        return false;
    }

    //writing the header of the xml file including the charset of the currrent used language
    $data = '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
    $data .= '<gquiz VERSION="200701" COMMENT="XML-Importfile for mod/gquiz">'."\n";
    $data .= $space.'<ITEMS>'."\n";

    //writing all the items
    foreach ($items as $item) {
        //start of item
        $data .= $space.$space.'<ITEM TYPE="'.$item->typ.'" REQUIRED="'.$item->required.'">'."\n";

        //start of itemid
        $data .= $space.$space.$space.'<ITEMID>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->id;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of itemid
        $data .= $space.$space.$space.'</ITEMID>'."\n";

        //start of itemtext
        $data .= $space.$space.$space.'<ITEMTEXT>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->name;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of itemtext
        $data .= $space.$space.$space.'</ITEMTEXT>'."\n";

        //start of itemtext
        $data .= $space.$space.$space.'<ITEMLABEL>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->label;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of itemtext
        $data .= $space.$space.$space.'</ITEMLABEL>'."\n";

        //start of presentation
        $data .= $space.$space.$space.'<PRESENTATION>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->presentation;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of presentation
        $data .= $space.$space.$space.'</PRESENTATION>'."\n";

        //start of options
        $data .= $space.$space.$space.'<OPTIONS>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->options;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of options
        $data .= $space.$space.$space.'</OPTIONS>'."\n";

        //start of dependitem
        $data .= $space.$space.$space.'<DEPENDITEM>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->dependitem;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of dependitem
        $data .= $space.$space.$space.'</DEPENDITEM>'."\n";

        //start of dependvalue
        $data .= $space.$space.$space.'<DEPENDVALUE>'."\n";
        //start of CDATA
        $data .= $space.$space.$space.$space.'<![CDATA[';
        $data .= $item->dependvalue;
        //end of CDATA
        $data .= ']]>'."\n";
        //end of dependvalue
        $data .= $space.$space.$space.'</DEPENDVALUE>'."\n";

        //end of item
        $data .= $space.$space.'</ITEM>'."\n";
    }

    //writing the footer of the xml file
    $data .= $space.'</ITEMS>'."\n";
    $data .= '</gquiz>'."\n";

    return $data;
}

function gquiz_send_xml_data($data, $filename) {
    @header('Content-Type: application/xml; charset=UTF-8');
    @header('Content-Disposition: attachment; filename="'.$filename.'"');
    print($data);
}
