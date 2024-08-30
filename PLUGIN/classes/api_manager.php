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
 * Plugin administration pages are defined here.
 *
 * @package     mod_learninggraph
 * @category    admin
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__.'/../lib.php');
require_once($CFG->libdir . '/questionlib.php');

defined('MOODLE_INTERNAL') || die();

class api_manager {

    private function get_endpoint($endpoint) {
        return 'http://localhost:8000/' . $endpoint;
    }

    private function sanitize_text($lessonplan) {
        return strip_tags($lessonplan);
    }

    private function check_error($response) {
        if ($response->error) {
            throw new Exception('Curl Error: ' . $response->error);
        }
    }

    private function api_body_newgraph($text, $cmid, $destinationtype=null, $id=null) {
        return [
            'text' => $text,
            'cm_id'=> $cmid,
            'id' => $id,
            'destination_type' => $destinationtype];     
    }

    private function api_body_mergegraphs($graphBase, $graphsToMerge, $cmid, $destinationtype, $prioritytonegativelevel=true) {
        return [
            'graphBase' => $graphBase,
            'graphsToMerge'=> $graphsToMerge,
            'cm_id' => $cmid,
            'priorityToNegativeLevel' => $prioritytonegativelevel,
            'destination_type' => $destinationtype];
    }

    private function api_body_update_level_and_merge($graphBase, $questionDetails, $cmid, $destinationtype, $userid) {
        return [
            'graphBase' => $graphBase,
            'question_details'=> $questionDetails,
            'cm_id' => $cmid,
            'user_id' => $userid,
            'destination_type' => $destinationtype];
    }

    private function set_url_params($url, $params = []) {
        $url .= '?';
        foreach ($params as $key => $value) {
            $url .= $key . '=' . $value . '&';
        }
        return rtrim($url, '&');
    }

    private function create_curl($jsondata) {
        $header = array('Content-Type: application/json', 'Content-Length: ' . strlen($jsondata));
        $curl = new \curl();
        $curl->setHeader($header);
        $curl->setopt(CURLOPT_CONNECTTIMEOUT, 10); //preciso colocar nas congigurações globais dps
        $curl->setopt(CURLOPT_TIMEOUT, 30);
        return $curl;
    }

    private function enconde_data($data) {
        $jsondata = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log('JSON encoding error: ' . json_last_error_msg(), 'error', 'mod_learninggraph');
            throw new Exception('JSON encoding error: ' . json_last_error_msg());
        }
        return $jsondata;
    }

    public function _post_data($data, $endpoint) {
        $jsondata = self::enconde_data($data);
        $curl = self::create_curl($jsondata);
        $api_endpoint = self::get_endpoint($endpoint);

        $curl->post($api_endpoint, $jsondata);

        self::check_error($curl);  
    
        if ($curl->info['http_code'] != 200) {
            throw new Exception('Request error: ' . $curl->response['HTTP/1.1']);
        }
        return $curl;
    }

    public function _put_data($data, $endpoint) {
        $jsondata = self::enconde_data($data);
        $curl = self::create_curl($jsondata);
        $api_endpoint = self::get_endpoint($endpoint);

        $curl->put($api_endpoint, $jsondata);

        self::check_error($curl);  
    
        if ($curl->info['http_code'] != 200) {
            throw new Exception('Request error: ' . $curl->response['HTTP/1.1']);
        }
        return $curl;
    }

    public function send_questions_to_api($instance, $questionids) {
        try {
            $questions = learninggraph_get_questions_list($questionids);
            foreach ($questions as $question) {
                self::_post_data(self::api_body_newgraph($question['questiontext'], $instance, 'questiongraph', $question['id']), 'NewGraph');
            }
            learninggraph_update_questions_status($questionids, LEARNINGGRAPH_STATUS_PROCESSING);
        } catch (Exception $e) {
            learninggraph_update_questions_status($questionids, LEARNINGGRAPH_STATUS_ERROR);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function send_lessonplan_to_api($instance) {
        try {
            $lessonplan = get_existing_plan_text($instance)->plan_text;
            $sanitized_lessonplan = self::sanitize_text($lessonplan);

            if (empty($sanitized_lessonplan)) {
                throw new Exception(get_string('lesson_plan_required', 'mod_learninggraph'));
            }
            self::_post_data(self::api_body_newgraph($sanitized_lessonplan, $instance, 'lessonplangraph'), 'NewGraph'); 
            return ['status' => 'success', 'message' => 'Lesson plan sent to API'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function merge_graph($basegraph, $graphs, $cmid, $destinationtype, $prioritytonegativelevel=true) {
        try {
            self::_put_data(self::api_body_mergegraphs($basegraph, $graphs, $cmid, $destinationtype, $prioritytonegativelevel), 'MergeGraphs');
        } catch (Exception $e) {
            // to do notify teacher
        }
    }

    public function update_level_and_merge($questionDetails, $cmid, $userid, $event) {
        try {
            echo json_encode($event);
            $conditions = array('cmid' => $cmid, 'userid' => $userid);
            $basegraph = convert_graph_to_response(get_graph($conditions, 'learninggraph_student_graphs', 'graphdata'));
            self::_put_data(self::api_body_update_level_and_merge($basegraph, $questionDetails, $cmid, 'studentgraph', $userid), 'UpdateLevels');
        } catch (Exception $e) {
            // to do notify teacher
        }
    }

    public function process_base_and_aggregated_graphs($instance, $graphtoadd) {
        $basegraph = get_graph(array('id' => $instance), 'learninggraph', 'graphdata');
        self::merge_graph(convert_graph_to_response($basegraph), array($graphtoadd), $instance, 'basegraph');

        $aggregatedgraph = get_graph(array('id' => $instance), 'learninggraph', 'aggregatedgraph');
        self::merge_graph(convert_graph_to_response($aggregatedgraph), array($graphtoadd), $instance, 'aggregategraph');
    }
}
