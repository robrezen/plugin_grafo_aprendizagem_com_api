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
 * Library of interface functions and constants.
 *
 * @package     mod_learninggraph
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('LEARNINGGRAPH_STATUS_UNPROCESSED', 'unprocessed');
define('LEARNINGGRAPH_STATUS_PROCESSING', 'processing');
define('LEARNINGGRAPH_STATUS_PROCESSED', 'processed');
define('LEARNINGGRAPH_STATUS_ERROR', 'error');
define('LEARNINGGRAPH_DEFAULT_GRAPHDATA', '{"nodes":{},"edges":[]}');

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function learninggraph_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return false;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_learninggraph into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_learninggraph_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function learninggraph_add_instance($data) {
    global $DB;
    
    $data->timecreated = time();
    $data->cmid = $data->coursemodule;
 
    $newInstanceId = $DB->insert_record("learninggraph", $data, true);

    if (isset($data->lesson_plaintext['text'])) {
        learninggraph_save_lesson_plan($data->cmid, $data->lesson_plaintext['text'], $data->lesson_format);
    }
 
    $context = context_course::instance($data->course);
    $students = get_enrolled_users($context, 'mod/learninggraph:student');
    $data->id = $newInstanceId;
    $data->graphdata = json_encode(['nodes' => new stdClass(), 'edges' => []]);

    foreach ($students as $student) {
        learninggraph_insert_student_graph($data, $student->id);
    }
    return $newInstanceId;
}

/**
 * Updates an instance of the mod_learninggraph in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_learninggraph_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function learninggraph_update_instance($data) : int {
    global $DB;
    $data->id = $data->instance;
    $data->introformat = 1;
    $data->timemodified = time();
    learninggraph_save_lesson_plan($data->coursemodule, $data->lesson_plaintext['text'], $data->lesson_format);
    return $DB->update_record("learninggraph", $data);
}

/**
 * Removes an instance of the mod_learninggraph from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function learninggraph_delete_instance($id) {
    global $DB;
    $learninggraph = $DB->get_record('learninggraph', array('id' => $id));
    if (!$learninggraph) {
        return false;
    }

    $cmid = $learninggraph->cmid;

    $res_api_lesson = $DB->delete_records('learninggraph_lessonplans', array('cmid' => $cmid), IGNORE_MISSING);
    $res_status = $DB->delete_records('learninggraph_api_status', array('cmid' => $cmid), IGNORE_MISSING);
    $res_students = $DB->delete_records('learninggraph_student_graphs', array('cmid' => $cmid), IGNORE_MISSING);
    $res_learning = $DB->delete_records('learninggraph', array('cmid' => $cmid), IGNORE_MISSING);


    if ($res_api_lesson && $res_status && $res_students && $res_learning) {
        return true;
    }
    return false;
}

/**
 * Get the existing lesson plan text from the database.
 *
 * @param int $cmid Course Module ID
 * @return stdClass|false Existing lesson plan or false if not found
 */
function get_existing_plan_text($instance) {
    global $DB;
    return $DB->get_record('learninggraph_lessonplans', array('id' => $instance), '*', IGNORE_MISSING);
}

/**
 * Save the lesson plan to the database.
 *
 * @param int $cmid Course Module ID
 * @param string $planText Plan text to be saved
 * @param int $planFormat Format of the plan text
 * @return bool True on success, false on failure
 */
function learninggraph_save_lesson_plan($cmid, $planText, $planFormat) {
    global $DB;

    $table = 'learninggraph_lessonplans';
    $existingPlan = $DB->get_record($table, array('cmid' => $cmid));

    $record = new stdClass();
    $record->cmid = $cmid;
    $record->plan_text = $planText;
    $record->plan_format = $planFormat;
    
    if ($existingPlan) {
        $record->timemodified = time();
        $record->id = $existingPlan->id;
        $updated = $DB->update_record($table, $record);
        if (!$updated) {
            throw new Exception(get_string('errorsavinglessonplan', 'mod_learninggraph'));
        }
    } else {
        $record->timecreated = time();
        $newPlanId = $DB->insert_record($table, $record, true);
        if (!$newPlanId) {
            throw new Exception(get_string('errorsavinglessonplan', 'mod_learninggraph'));
        }
    }
    return true; 
}

/**
 * Uodate the base graph in the database
 *
 * @param int $cmid Course Module ID
 * @param string $base_graph Base graph data
 * @return stdClass|false Existing base graph or false if not found
 */
function learninggraph_save_base_graph($cmid, $base_graph) {
    global $DB;
    $table = 'learninggraph';

    $existingGraph = $DB->get_record($table, array('cmid' => $cmid));

    $record = new stdClass();
    $record->id = $cmid;
    $record->base_graph = $base_graph;
    
    if ($existingGraph) {
        $record->timemodified = time();
        $updated = $DB->update_record($table, $record);
        if (!$updated) {
            throw new Exception(get_string('errorsavinggraph', 'mod_learninggraph'));
        }
    } else {
        //something is wrong, because the lesson plan should already exist
        throw new Exception(get_string('errorsavinggraph', 'mod_learninggraph'));
    }
    return true; 
}

/**
 * Save the graph to the database.
 *
 * @param int $id ID of the record
 * @param string $graph Graph data
 * @param string $table Table name
 * @return bool True on success, false on failure
 */
function learninggraph_save_graph($conditions, $graph, $table, $aggregategraph = False) {
    global $DB;
    $existingGraph = $DB->get_record($table, $conditions);
    
    if ($existingGraph) {
        $record = new stdClass();
        $record->id = $existingGraph->id;
        if (!$aggregategraph) {
            $record->graphdata = json_encode($graph);
        } else {
            $record->aggregatedgraph = json_encode($graph);
        }
        $record->timemodified = time();
        $updated = $DB->update_record($table, $record);
        if (!$updated) {
            throw new Exception('Error saving graph');
        }
    } else {
        throw new Exception('Graph not found' . json_encode($conditions));
    }
}


/**
 * get status of the learning graph
 * 
 * @param int $cmid Course Module ID
 * @param
 */
function learninggraph_api_status($cmid, $status) {
    global $DB;

    $record = new \stdClass();
    $record->cmid = $cmid;
    $record->status = $status;

    $existing_record = $DB->get_record('learninggraph_api_status', array('cmid' => $cmid));
    if ($existing_record) {
        $record->timemodified = time();
        $record->id = $existing_record->id;
        $result = $DB->update_record('learninggraph_api_status', $record);
    } else {
        $record->timecreated = time();
        $result = $DB->insert_record('learninggraph_api_status', $record);
    }
    return $result;
}

/*
 * Get all questions curse module
 *
 * @param int $cmid Course Module ID
 * @return array Array of question objects
 */
function learninggraph_get_course_questions($cmid) {
    global $DB;
    $context = context_course::instance($cmid);
    $categories = $DB->get_records_sql('SELECT * FROM {question_categories} WHERE contextid = ?', [$context->id]);

    if (empty($categories)) {
        return [];
    }

    $categoryids = array_keys($categories);

    list($qcsql, $qcparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'qc');

    $qcparams['readystatus'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

    $sql = "SELECT q.id, q.questiontext, q.qtype, lgg.status AS graphstatus
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        LEFT JOIN {learninggraph_quest_graphs} lgg ON q.id = lgg.questionid
        WHERE qbe.questioncategoryid {$qcsql}
            AND q.parent = 0
            AND qv.status = :readystatus";
    return $DB->get_records_sql($sql, $qcparams);
}


/**
 * Insert a new question in the learning graph.
 *
 * @
 * @return bool 
 */
function learninggraph_insert_question($questionid,$courseid) {
    global $DB;
    $exists = $DB->record_exists('learninggraph_quest_graphs', ['questionid' => $questionid]);
    if (!$exists) {
        $record = new stdClass();
        $record->questionid = $questionid;
        $record->courseid = $courseid;
        $record->status = LEARNINGGRAPH_STATUS_UNPROCESSED;
        $record->graphdata = LEARNINGGRAPH_DEFAULT_GRAPHDATA;
        $record->timecreated = time();
        $record->timemodified = time();
        return $DB->insert_record('learninggraph_quest_graphs', $record, false);
    }
    return false;
}

/**
 * Delete a question from the learning graph.
 *
 * @param int $questionid Question ID
 * @return bool True on success, false on failure
 */
function learninggraph_delele_question($questionid) {
    global $DB;
    $result = $DB->delete_records('learninggraph_quest_graphs', ['questionid' => $questionid]);
    return $result;
}

/**
 * Get the list of questions from the database.
 *
 * @param string $questionids Comma separated list of question IDs
 * @return array Array of question objects
 */
function learninggraph_get_questions_list($questionids) {
    global $DB;
    if (!is_array($questionids)) {
        $questionids = explode(',', $questionids);
    }

    list($in_sql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');

    $sql = "SELECT q.id, q.questiontext
            FROM {question} q
            WHERE q.id $in_sql";

    $records = $DB->get_records_sql($sql, $params);

    $questions = [];
    foreach ($records as $record) {
        $questions[] = [
            'id' => $record->id,
            'questiontext' => strip_tags($record->questiontext)
        ];
    }

    return $questions;
}

/**
 * Update the status of the questions in the learning graph.
 *
 * @param string $questionids Comma separated list of question IDs
 * @param string $status New status
 */
function learninggraph_update_questions_status($questionids, $status) {
    global $DB;
    if (!is_array($questionids)) {
        $questionids = explode(',', $questionids);
    }

    list($in_sql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
    
    $sql = "UPDATE {learninggraph_quest_graphs} SET status = :status WHERE questionid $in_sql";
    $params['status'] = $status;
    
    $DB->execute($sql, $params);
}


/**
 * Get the autosending setting for a learning graph instance.
 *
 * @param int $instance Learning graph instance ID
 * @return int Autosending setting
 */
function learninggraph_get_autosending($instance) {
    global $DB;
    return $DB->get_field('learninggraph', 'autosending', ['id' => $instance]);
}


/**
 * Update the student graph in the database.
 *
 * @param int $cmid Course Module ID
 * @param int $userid User ID
 * @param string $baseGraph Base graph data
 */
function update_student_graph($cmid, $userid, $baseGraph) {
    global $DB;

    $studentGraph = new \stdClass();
    $studentGraph->cmid = $cmid;
    $studentGraph->userid = $userid;
    $studentGraph->graph_data = $baseGraph;
    $studentGraph->timecreated = time();
    $studentGraph->timemodified = $studentGraph->timecreated;

    $existingRecord = $DB->get_record('learninggraph_student_graphs', array('cmid' => $cmid, 'userid' => $userid));
    if ($existingRecord) {
        $studentGraph->id = $existingRecord->id;
        $DB->update_record('learninggraph_student_graphs', $studentGraph);
    } else {
        $DB->insert_record('learninggraph_student_graphs', $studentGraph);
    }
}


/**
 * Check if the learning graph instance is set to auto send.
 *
 * @param object $context Context object
 * @return bool True if the instance is set to auto send, false otherwise
 */
function autoupdate_question($context) {
    global $DB;

    if ($context->contextlevel == CONTEXT_COURSE) {
        $courseid = $context->instanceid;
    } elseif ($context->contextlevel == CONTEXT_MODULE) {
        $cm = get_coursemodule_from_id(null, $context->instanceid);
        if ($cm) {
            $courseid = $cm->course;
        } else {
            return false;
        }
    } else {
        return false;
    }

    // if some learning graph instance is set to auto send, return true
    $moduleinstances = $DB->get_records('learninggraph', ['course' => $courseid]);
    foreach ($moduleinstances as $moduleinstance) {
        if ($moduleinstance->autosending != 0) {
            return true;
        }
    }
    return false;
}


/**
 * Insert a new record in the learninggraph_student_graphs table.
 *
 * @param object $instance Learning graph instance
 * @param int $userid User ID
 */
function learninggraph_insert_student_graph($instance, $userid) {
    global $DB;

    $record = new stdClass();
    $record->cmid = $instance->cmid;
    $record->userid = $userid;
    $record->graphdata = $instance->graphdata;
    $record->timecreated = time();
    $record->timemodified = time();  
    $DB->insert_record('learninggraph_student_graphs', $record);
}


/**
 * Get all instances of learninggraph in a particular course.
 *
 * @param int $courseid ID of the course
 * @return array Array of objects containing instance records
 */
function learninggraph_get_instances($courseid) {
    global $DB;
    return $DB->get_records('learninggraph', array('course' => $courseid));
}


/**
 * Check if the user has permission to get questions or graphs.
 * @param object $context Context object
 * @param string $msg Error message
*/
function check_user_permission($context, $msg) { 
    if (!has_capability('mod/learninggraph:fetchquestionsandgetgraph', $context)) {
        throw new moodle_exception('errornoaccess', 'error', '', null, $msg);
    }
}

/**
 * Check if the user has permission to view the student graph.
 * @param object $context Context object
 * @param string $msg Error message
*/
function check_user_permission_student($context, $msg) {
    if (!has_capability('mod/learninggraph:student', $context)) {
        throw new moodle_exception('errornoaccess', 'error', '', null, $msg);
    }
}

/**
 * Get the base graph from the database.
 *
 * @param int $id ID of the record
 * @param string $tablename Table name
 * @param string $field Field name
 * @return array Array of nodes and edges
 */
function get_graph($conditions, $tablename, $field) {
    global $DB;
    $graph = $DB->get_field($tablename, $field, $conditions);
    if (!$graph || empty($graph)) {
        return ['nodes' => new stdClass(), 'edges' => []];
    }
    return json_decode($graph, true);
}


function convert_response_to_graph($response) {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error decoding saved graph JSON: ' . json_last_error_msg());
    }

    $nodes = $data[0];
    $edges = $data[1];

    if (empty($nodes) || empty($edges)) {
        throw new Exception('Invalid graph data');
    }

    $graphData = [
        'nodes' => [],
        'edges' => []
    ];

    foreach ($nodes as $node => $properties) {
        $graphData["nodes"][$node] = [
            "importance" => $properties["importance"],
            "level" => $properties["level"]
        ];
    }

    foreach ($edges as $edge) {
        $graphData["edges"][] = [
            "head" => $edge[0]["head"],
            "type" => $edge[0]["type"],
            "tail" => $edge[0]["tail"],
            "weight" => $edge[1]
        ];
    }
    return $graphData;
}

function convert_graph_to_response($savedGraph) {
    if (!isset($savedGraph['nodes']) || !isset($savedGraph['edges'])) {
        throw new Exception('Invalid graph data format'. json_encode($savedGraph));
    }

    $nodes = $savedGraph['nodes'];

    $edges = [];
    foreach ($savedGraph['edges'] as $edge) {
        if (isset($edge['head']) && isset($edge['type']) && isset($edge['tail']) && isset($edge['weight'])) {
            $edges[] = [
                [
                    "head" => $edge["head"],
                    "type" => $edge["type"],
                    "tail" => $edge["tail"]
                ],
                $edge["weight"]
            ];
        } else {
            error_log('Invalid edge format: ' . print_r($edge, true));
        }
    }

    return json_encode([$nodes, $edges]);
}

function graph_is_empty($graph) {
    if (empty($graph->nodes) || empty($graph->edges)) {
        return true;
    }
    return false;
}

function get_not_empty_graphs($courseid) {
    global $DB;
    $questionsgraph = $DB->get_records('learninggraph_quest_graphs', array('courseid' => $courseid), '', 'id, graphdata');
    print_r($questionsgraph);
    if (empty($questionsgraph)) {
        return [];
    }
    $notempty = [];
    foreach ($questionsgraph as $question) {
        if (!graph_is_empty($question)) {
            $notempty[] = $question->graphdata;
        }
    }
    return $notempty;
}
