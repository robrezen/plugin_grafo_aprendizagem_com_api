
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

require(__DIR__ . '/../../config.php');

$questionIds = required_param('questionids', PARAM_SEQUENCE);

$questions = explode(',', $questionIds);
$status = [];

foreach ($questions as $questionId) {
    $question = $DB->get_record('learninggraph_quest_graphs', ['questionid' => $questionId], 'status');
    if (!$question) {
        $status[] = 'not_found';
        continue;
    }
    $status[] = $question->status;
}

echo json_encode(['status' => $status]);