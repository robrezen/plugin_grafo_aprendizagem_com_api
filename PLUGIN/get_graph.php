<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     mod_learninggraph
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/learninggraph/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid',  PARAM_INT);
$source = required_param('source', PARAM_TEXT);
$questionid = optional_param('questionid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);


require_login();
$context = context_course::instance($courseid);


switch ($source) {
    case 'lessonplangraph':
        check_user_permission($context, 'You do not have permission to view base graph.');
        $conditions = array('cmid' => $cmid);
        $graphData = get_graph($conditions, 'learninggraph_lessonplans', 'graphdata');
        break;
    case 'studentgraph':
        check_user_permission_student($context, 'You do not have permission to view student graph.');
        $conditions = array('cmid' => $cmid, 'userid' => $userid);
        $graphData = get_graph($conditions, 'learninggraph_student_graphs', 'graphdata');
        break;
    case 'instancebasegraph':
        check_user_permission($context, 'You do not have permission to view base graph.');
        $conditions = array('id' => $cmid);
        $graphData = get_graph($conditions, 'learninggraph', 'graphdata');
        break;
    case 'instanceaggregatedgraph':
        check_user_permission($context, 'You do not have permission to view aggregate graph.');
        $conditions = array('id' => $cmid);
        $graphData = get_graph($conditions, 'learninggraph', 'aggregatedgraph');
        break;
    case 'question':
        check_user_permission($context, 'You do not have permission to view question graph.');
        $conditions = array('questionid' => $questionid);
        $graphData = get_graph($conditions, 'learninggraph_quest_graphs', 'graphdata');
        break;
    default:
        break;
}

header('Content-Type: application/json');
echo json_encode($graphData);  
exit;