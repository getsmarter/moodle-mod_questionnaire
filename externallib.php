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
 * Questionnaire module external API
 *
 * @package    mod_questionnaire
 * @category   external
 * @copyright  2018 Igor Sazonov <sovletig@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/questionnaire/lib.php');

/**
 * Questionnaire module external functions
 *
 * @package    mod_questionnaire
 * @category   external
 * @copyright  2018 Igor Sazonov <sovletig@yandex.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_questionnaire_external extends \external_api {

    /**
     * Describes the parameters for submit_questionnaire_branching_parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function submit_questionnaire_branching_parameters() {
        return new \external_function_parameters(
            [
                'questionnaireid' => new \external_value(PARAM_INT, 'Questionnaire instance id'),
                'surveyid' => new \external_value(PARAM_INT, 'Survey id'),
                'userid' => new \external_value(PARAM_INT, 'User id'),
                'cmid' => new \external_value(PARAM_INT, 'Course module id'),
                'sec' => new \external_value(PARAM_INT, 'Section number'),
                'completed' => new \external_value(PARAM_INT, 'Completed survey or not'),
                'submit' => new \external_value(PARAM_INT, 'Submit survey or not'),
                'responses' => new \external_multiple_structure(
                    new \external_single_structure(
                        [
                            'name' => new \external_value(PARAM_RAW, 'data key'),
                            'value' => new \external_value(PARAM_RAW, 'data value')
                        ]
                    ),
                    'The data to be saved', VALUE_DEFAULT, []
                )
            ]
        );
    }

     /**
     * Submit questionnaire responses
     *
     * @param int $questionnaireid the questionnaire instance id
     * @param int $surveyid Survey id
     * @param int $userid User id
     * @param int $cmid Course module id
     * @param int $sec Section number
     * @param int $completed Completed survey 1/0
     * @param int $submit Submit survey?
     * @param array $responses the response ids
     * @return array answers information and warnings
     * @since Moodle 3.0
     */
    public static function submit_questionnaire_branching($questionnaireid, $surveyid, $userid,
        $cmid, $sec, $completed, $submit, $responses) {

        $params = self::validate_parameters(self::submit_questionnaire_branching_parameters(),
            [
                'questionnaireid' => $questionnaireid,
                'surveyid' => $surveyid,
                'userid' => $userid,
                'cmid' => $cmid,
                'sec' => $sec,
                'completed' => $completed,
                'submit' => $submit,
                'responses' => $responses
            ]
        );

        if (!$questionnaire = get_questionnaire($params['questionnaireid'])) {
            throw new \moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($questionnaire, 'questionnaire');

        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/questionnaire:submit', $context);

        $result = save_questionnaire_data_branching($questionnaireid, $surveyid, $userid, $cmid,
            $sec, $completed, $submit, $responses);

        $result['submitted'] = true;
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            unset($result['responses']);
            $result['submitted'] = false;
        }
        $result['warnings'] = [];
        return $result;
    }

    /**
     * Describes the submit_questionnaire_branching return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function submit_questionnaire_branching_returns() {
        return new \external_single_structure(
            [
                'submitted' => new \external_value(PARAM_BOOL, 'submitted', true, false, false),
                'warnings' => new \external_warnings(),
                'params' => new \external_warnings(),
            ]
        );
    }
}