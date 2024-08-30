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
 * Copy base graph to a selected user.
 *
 * @package     mod_learninggraph
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

// Ensure the user is logged in and has required capabilities.
require_login();
$context = context_module::instance($cmid);
require_capability('mod/learninggraph:teacher', $context);

$cm = get_coursemodule_from_id('learninggraph', $cmid, 0, false, MUST_EXIST);

$basegraph = $DB->get_field('learninggraph', 'graphdata', array('id' => $cm->instance), MUST_EXIST);

if (!$basegraph) {
    throw new moodle_exception('base_graph_not_found', 'mod_learninggraph');
}

// Check if the user already has a graph.
$existinggraph = $DB->get_record('learninggraph_student_graphs', array('cmid' => $cmid, 'userid' => $userid));

if ($existinggraph) {
    $existinggraph->graphdata = $basegraph;
    $existinggraph->timemodified = time();
    $DB->update_record('learninggraph_student_graphs', $existinggraph);
} else {
    // Insert a new graph record.
    $newgraph = new stdClass();
    $newgraph->id = $cm->instance;
    $newgraph->cmid = $cmid;
    $newgraph->userid = $userid;
    $newgraph->graphdata = $basegraph;
    $newgraph->timecreated = time();
    $newgraph->timemodified = time();
    $DB->insert_record('learninggraph_student_graphs', $newgraph);
}

// Redirect back to the module page with a success message.
redirect(new moodle_url('/mod/learninggraph/view.php', array('id' => $cmid)), get_string('base_graph_copied', 'mod_learninggraph'), null, \core\output\notification::NOTIFY_SUCCESS);
