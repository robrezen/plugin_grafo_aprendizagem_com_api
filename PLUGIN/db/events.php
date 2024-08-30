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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     mod_learninggraph
 * @category    event
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 // List of observers.
 $observers = array(

	array(
		'eventname'   => '\mod_quiz\event\attempt_submitted',
		'callback'    => 'mod_learninggraph_observer::quiz_attempt_submitted'
	),
	array(
		'eventname'   => '\core\event\question_created',
		'callback'    => 'mod_learninggraph_observer::insert_new_question'
	),
	array(
		'eventname'   => '\core\event\question_updated',
		'callback'    => 'mod_learninggraph_observer::update_question'
	),
	array(
		'eventname'   => '\core\event\question_deleted',
		'callback'    => 'mod_learninggraph_observer::delete_question'
	),
	array(
		'eventname'   => '\core\event\user_enrolment_created',
		'callback'    => 'mod_learninggraph_observer::user_enrolment_created'
	),
	array(
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'mod_learninggraph_observer::user_enrolment_deleted',
    )
);
 