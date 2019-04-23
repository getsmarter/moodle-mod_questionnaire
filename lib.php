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

// Library of functions and constants for module questionnaire.

/**
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('QUESTIONNAIRE_RESETFORM_RESET', 'questionnaire_reset_data_');
define('QUESTIONNAIRE_RESETFORM_DROP', 'questionnaire_drop_questionnaire_');

function questionnaire_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * @return array all other caps used in module
 */
function questionnaire_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

function get_questionnaire($questionnaireid) {
    global $DB;
    return $DB->get_record('questionnaire', array('id' => $questionnaireid));
}

function questionnaire_add_instance($questionnaire) {
    // Given an object containing all the necessary data,
    // (defined by the form in mod.html) this function
    // will create a new instance and return the id number
    // of the new instance.
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');
    require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

    // Check the realm and set it to the survey if it's set.

    if (empty($questionnaire->sid)) {
        // Create a new survey.
        $course = get_course($questionnaire->course);
        $cm = new stdClass();
        $qobject = new questionnaire(0, $questionnaire, $course, $cm);

        if ($questionnaire->create == 'new-0') {
            $sdata = new stdClass();
            $sdata->name = $questionnaire->name;
            $sdata->realm = 'private';
            $sdata->title = $questionnaire->name;
            $sdata->subtitle = '';
            $sdata->info = '';
            $sdata->theme = ''; // Theme is deprecated.
            $sdata->thanks_page = '';
            $sdata->thank_head = '';
            $sdata->thank_body = '';
            $sdata->email = '';
            $sdata->feedbacknotes = '';
            $sdata->courseid = $course->id;
            if (!($sid = $qobject->survey_update($sdata))) {
                print_error('couldnotcreatenewsurvey', 'questionnaire');
            }
        } else {
            $copyid = explode('-', $questionnaire->create);
            $copyrealm = $copyid[0];
            $copyid = $copyid[1];
            if (empty($qobject->survey)) {
                $qobject->add_survey($copyid);
                $qobject->add_questions($copyid);
            }
            // New questionnaires created as "use public" should not create a new survey instance.
            if ($copyrealm == 'public') {
                $sid = $copyid;
            } else {
                $sid = $qobject->sid = $qobject->survey_copy($course->id);
                // All new questionnaires should be created as "private".
                // Even if they are *copies* of public or template questionnaires.
                $DB->set_field('questionnaire_survey', 'realm', 'private', array('id' => $sid));
            }
            // If the survey has dependency data, need to set the questionnaire to allow dependencies.
            if ($DB->count_records('questionnaire_dependency', ['surveyid' => $sid]) > 0) {
                $questionnaire->navigate = 1;
            }
        }
        $questionnaire->sid = $sid;
    }

    $questionnaire->timemodified = time();

    // May have to add extra stuff in here.
    if (empty($questionnaire->useopendate)) {
        $questionnaire->opendate = 0;
    }
    if (empty($questionnaire->useclosedate)) {
        $questionnaire->closedate = 0;
    }

    if ($questionnaire->resume == '1') {
        $questionnaire->resume = 1;
    } else {
        $questionnaire->resume = 0;
    }

    if (!$questionnaire->id = $DB->insert_record("questionnaire", $questionnaire)) {
        return false;
    }

    questionnaire_set_events($questionnaire);

    $completiontimeexpected = !empty($questionnaire->completionexpected) ? $questionnaire->completionexpected : null;
    \core_completion\api::update_completion_date_event($questionnaire->coursemodule, 'questionnaire',
        $questionnaire->id, $completiontimeexpected);

    return $questionnaire->id;
}

// Given an object containing all the necessary data,
// (defined by the form in mod.html) this function
// will update an existing instance with new data.
function questionnaire_update_instance($questionnaire) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

    // Check the realm and set it to the survey if its set.
    if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
        $DB->set_field('questionnaire_survey', 'realm', $questionnaire->realm, array('id' => $questionnaire->sid));
    }

    $questionnaire->timemodified = time();
    $questionnaire->id = $questionnaire->instance;

    // May have to add extra stuff in here.
    if (empty($questionnaire->useopendate)) {
        $questionnaire->opendate = 0;
    }
    if (empty($questionnaire->useclosedate)) {
        $questionnaire->closedate = 0;
    }

    if ($questionnaire->resume == '1') {
        $questionnaire->resume = 1;
    } else {
        $questionnaire->resume = 0;
    }

    // Get existing grade item.
    questionnaire_grade_item_update($questionnaire);

    questionnaire_set_events($questionnaire);

    $completiontimeexpected = !empty($questionnaire->completionexpected) ? $questionnaire->completionexpected : null;
    \core_completion\api::update_completion_date_event($questionnaire->coursemodule, 'questionnaire',
        $questionnaire->id, $completiontimeexpected);

    return $DB->update_record("questionnaire", $questionnaire);
}

// Given an ID of an instance of this module,
// this function will permanently delete the instance
// and any data that depends on it.
function questionnaire_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

    if (! $questionnaire = $DB->get_record('questionnaire', array('id' => $id))) {
        return false;
    }

    $result = true;

    if ($events = $DB->get_records('event', array("modulename" => 'questionnaire', "instance" => $questionnaire->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
    }

    if (! $DB->delete_records('questionnaire', array('id' => $questionnaire->id))) {
        $result = false;
    }

    if ($survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid))) {
        // If this survey is owned by this course, delete all of the survey records and responses.
        if ($survey->courseid == $questionnaire->course) {
            $result = $result && questionnaire_delete_survey($questionnaire->sid, $questionnaire->id);
        }
    }

    return $result;
}

// Return a small object with summary information about what a
// user has done with a given particular instance of this module
// Used for user activity reports.
// $return->time = the time they did it
// $return->info = a short text description.
/**
 * $course and $mod are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_user_outline($course, $user, $mod, $questionnaire) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

    $result = new stdClass();
    if ($responses = questionnaire_get_user_responses($questionnaire->id, $user->id, true)) {
        $n = count($responses);
        if ($n == 1) {
            $result->info = $n.' '.get_string("response", "questionnaire");
        } else {
            $result->info = $n.' '.get_string("responses", "questionnaire");
        }
        $lastresponse = array_pop($responses);
        $result->time = $lastresponse->submitted;
    } else {
        $result->info = get_string("noresponses", "questionnaire");
    }
    return $result;
}

// Print a detailed representation of what a  user has done with
// a given particular instance of this module, for user activity reports.
/**
 * $course and $mod are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_user_complete($course, $user, $mod, $questionnaire) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

    if ($responses = questionnaire_get_user_responses($questionnaire->id, $user->id, false)) {
        foreach ($responses as $response) {
            if ($response->complete == 'y') {
                echo get_string('submitted', 'questionnaire').' '.userdate($response->submitted).'<br />';
            } else {
                echo get_string('attemptstillinprogress', 'questionnaire').' '.userdate($response->submitted).'<br />';
            }
        }
    } else {
        print_string('noresponses', 'questionnaire');
    }

    return true;
}

// Given a course and a time, this module should find recent activity
// that has occurred in questionnaire activities and print it out.
// Return true if there was output, or false is there was none.
/**
 * $course, $isteacher and $timestart are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_print_recent_activity($course, $isteacher, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

// Must return an array of grades for a given instance of this module,
// indexed by user.  It also returns a maximum allowed grade.
/**
 * $questionnaireid is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_grades($questionnaireid) {
    return null;
}

/**
 * Return grade for given user or all users.
 *
 * @param int $questionnaireid id of assignment
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function questionnaire_get_user_grades($questionnaire, $userid=0) {
    global $DB;
    $params = array();
    $usersql = '';
    if (!empty($userid)) {
        $usersql = "AND u.id = ?";
        $params[] = $userid;
    }

    $sql = "SELECT r.id, u.id AS userid, r.grade AS rawgrade, r.submitted AS dategraded, r.submitted AS datesubmitted
            FROM {user} u, {questionnaire_response} r
            WHERE u.id = r.userid AND r.questionnaireid = $questionnaire->id AND r.complete = 'y' $usersql";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $assignment null means all assignments
 * @param int $userid specific user only, 0 mean all
 *
 * $nullifnone is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_update_grades($questionnaire=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($questionnaire != null) {
        if ($graderecs = questionnaire_get_user_grades($questionnaire, $userid)) {
            $grades = array();
            foreach ($graderecs as $v) {
                if (!isset($grades[$v->userid])) {
                    $grades[$v->userid] = new stdClass();
                    if ($v->rawgrade == -1) {
                        $grades[$v->userid]->rawgrade = null;
                    } else {
                        $grades[$v->userid]->rawgrade = $v->rawgrade;
                    }
                    $grades[$v->userid]->userid = $v->userid;
                } else if (isset($grades[$v->userid]) && ($v->rawgrade > $grades[$v->userid]->rawgrade)) {
                    $grades[$v->userid]->rawgrade = $v->rawgrade;
                }
            }
            questionnaire_grade_item_update($questionnaire, $grades);
        } else {
            questionnaire_grade_item_update($questionnaire);
        }

    } else {
        $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
                  FROM {questionnaire} q, {course_modules} cm, {modules} m
                 WHERE m.name='questionnaire' AND m.id=cm.module AND cm.instance=q.id";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $questionnaire) {
                if ($questionnaire->grade != 0) {
                    questionnaire_update_grades($questionnaire);
                } else {
                    questionnaire_grade_item_update($questionnaire);
                }
            }
            $rs->close();
        }
    }
}

/**
 * Create grade item for given questionnaire
 *
 * @param object $questionnaire object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function questionnaire_grade_item_update($questionnaire, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($questionnaire->courseid)) {
        $questionnaire->courseid = $questionnaire->course;
    }

    if ($questionnaire->cmidnumber != '') {
        $params = array('itemname' => $questionnaire->name, 'idnumber' => $questionnaire->cmidnumber);
    } else {
        $params = array('itemname' => $questionnaire->name);
    }

    if ($questionnaire->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $questionnaire->grade;
        $params['grademin']  = 0;

    } else if ($questionnaire->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$questionnaire->grade;

    } else if ($questionnaire->grade == 0) { // No Grade..be sure to delete the grade item if it exists.
        $grades = null;
        $params = array('deleted' => 1);

    } else {
        $params = null; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/questionnaire', $questionnaire->courseid, 'mod', 'questionnaire',
                    $questionnaire->id, 0, $grades, $params);
}

/**
 * This function returns if a scale is being used by one questionnaire
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 * @param $questionnaireid int
 * @param $scaleid int
 * @return boolean True if the scale is used by any questionnaire
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_scale_used ($questionnaireid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of questionnaire
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any questionnaire
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Get questionnaire data
 * for mobile only kurvin hendricks
 *
 * @global object $DB
 * @param int $cmid
 * @param int|bool $userid
 * @return array
 * @throws moodle_exception
 */
function get_questionnaire_data($cmid, $userid = false) {
    global $DB, $USER;
    if ($q = get_coursemodule_from_id('questionnaire', $cmid)) {
        if (!$questionnaire = get_questionnaire($q->instance)) {
            throw new \moodle_exception("invalidcoursemodule", "error");
        }
    }
    $resumedsql = 'SELECT id FROM '
    . '{questionnaire_response} '
    . ' WHERE questionnaireid = ? AND userid = ? AND complete = ? AND submitted <= ?';
    $params = ['userid' => $userid, 'questionnaireid' => $q->instance, 'complete' => 'y'];
    $time = time();
    $ret = [
        'questionnaire' => [
            'id' => $questionnaire->id,
            'name' => format_string($questionnaire->name),
            'intro' => $questionnaire->intro,
            'userid' => intval($userid ? $userid : $USER->id),
            'questionnaireid' => intval($questionnaire->sid),
            'autonumpages' => in_array($questionnaire->autonum, [1, 2]),
            'autonumquestions' => in_array($questionnaire->autonum, [1, 3])
        ],
        'response' => [
            'id' => 0,
            'questionnaireid' => 0,
            'submitted' => 0,
            'complete' => 'n',
            'grade' => 0,
            'userid' => 0,
            'fullname' => '',
            'userdate' => '',
        ],
        'answered' => [],
        'fields' => [],
        'responses' => [],
        'questionscount' => 0,
        'pagescount' => 1,
        'resumed' => $DB->get_records_sql($resumedsql,
            [$q->instance, $USER->id, 'n', ($time - (60 * 10))]),
        'completed' => $DB->record_exists('questionnaire_response', $params),
    ];
    $sql = 'SELECT qq.*,qqt.response_table FROM '
        . '{questionnaire_question} qq LEFT JOIN {questionnaire_question_type} qqt '
        . 'ON qq.type_id = qqt.typeid WHERE qq.surveyid = ? AND qq.deleted = ? '
        . 'ORDER BY qq.position';
    if ($questions = $DB->get_records_sql($sql, [$questionnaire->sid, 'n'])) {
        require_once('classes/question/base.php');
        $pagenum = 1;
        $context = \context_module::instance($cmid);
        $qnum = 0;
        foreach ($questions as $question) {
            $ret['questionscount']++;
            $qnum++;
            $fieldkey = 'response_'.$question->type_id.'_'.$question->id;
            $options = ['noclean' => true, 'para' => false, 'filter' => true,
                'context' => $context, 'overflowdiv' => true];
            if ($question->type_id != QUESPAGEBREAK) {
                $ret['questionsinfo'][$pagenum][$question->id] =
                $ret['fields'][$fieldkey] = [
                    'id' => $question->id,
                    'surveyid' => $question->surveyid,
                    'name' => $question->name,
                    'type_id' => $question->type_id,
                    'length' => $question->length,
                    'content' => ($ret['questionnaire']['autonumquestions'] ? '' : '') . format_text(file_rewrite_pluginfile_urls(
                            $question->content, 'pluginfile.php', $context->id,
                            'mod_questionnaire', 'question', $question->id),
                            FORMAT_HTML, $options),
                    'content_stripped' => strip_tags($question->content),
                    'required' => $question->required,
                    'deleted' => $question->deleted,
                    'response_table' => $question->response_table,
                    'fieldkey' => $fieldkey,
                    'precise' => $question->precise,
                    'qnum' => $qnum,
                    'errormessage' => get_string('required') . ': ' . $question->name,
                ];
            }
            $std = new \stdClass();
            $std->id = $std->choice_id = 0;
            $std->question_id = $question->id;
            $std->content = '';
            $std->value = null;
            switch ($question->type_id) {
                case QUESYESNO: // Yes/No bool.
                    $stdyes = new \stdClass();
                    $stdyes->id = 1;
                    $stdyes->choice_id = 'y';
                    $stdyes->question_id = $question->id;
                    $stdyes->value = null;
                    $stdyes->content = get_string('yes');
                    $stdyes->isbool = true;
                    if ($ret['questionsinfo'][$pagenum][$question->id]['required']) {
                        $stdyes->value = 'y';
                        $stdyes->firstone = true;
                    }
                    $ret['questions'][$pagenum][$question->id][1] = $stdyes;
                    $stdno = new \stdClass();
                    $stdno->id = 0;
                    $stdno->choice_id = 'n';
                    $stdno->question_id = $question->id;
                    $stdno->value = null;
                    $stdno->content = get_string('no');
                    $stdno->isbool = true;
                    $ret['questions'][$pagenum][$question->id][0] = $stdno;
                    $ret['questionsinfo'][$pagenum][$question->id]['isbool'] = true;
                    break;
                case QUESTEXT: // Text.
                case QUESESSAY: // Essay.
                    $ret['questions'][$pagenum][$question->id][0] = $std;
                    $ret['questionsinfo'][$pagenum][$question->id]['istextessay'] = true;
                    break;
                case QUESRADIO: // Radiobutton.
                    $ret['questionsinfo'][$pagenum][$question->id]['isradiobutton'] = true;
                    $excludes = [];
                    if ($items = $DB->get_records('questionnaire_quest_choice',
                    ['question_id' => $question->id])) {

                        foreach ($items as $item) {
                            if (!in_array($item->id, $excludes)) {
                                $item->choice_id = $item->id;
                                if ($item->value == null) {
                                    $item->value = '';
                                }
                                $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                if ($question->type_id != 8) {
                                    if ($ret['questionsinfo'][$pagenum][$question->id]['required']) {
                                        if (!isset($ret['questionsinfo'][$pagenum][$question->id]['firstone'])) {
                                            $ret['questionsinfo'][$pagenum][$question->id]['firstone'] = true;
                                            $ret['questions'][$pagenum][$question->id][$item->id]->value = intval($item->choice_id);
                                            $ret['questions'][$pagenum][$question->id][$item->id]->firstone = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                case QUESCHECK: // Checkbox.
                    $ret['questionsinfo'][$pagenum][$question->id]['ischeckbox'] = true;
                    $excludes = [];
                    if ($items = $DB->get_records('questionnaire_quest_choice',
                    ['question_id' => $question->id])) {

                        foreach ($items as $item) {
                            if (!in_array($item->id, $excludes)) {
                                $item->choice_id = $item->id;
                                if ($item->value == null) {
                                    $item->value = '';
                                }
                                $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                if ($question->type_id != QUESRATE) {
                                    if ($ret['questionsinfo'][$pagenum][$question->id]['required']) {
                                        if (!isset($ret['questionsinfo'][$pagenum][$question->id]['firstone'])) {
                                            $ret['questionsinfo'][$pagenum][$question->id]['firstone'] = true;
                                            $ret['questions'][$pagenum][$question->id][$item->id]->firstone = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                case QUESDROP: // Select.
                    $ret['questionsinfo'][$pagenum][$question->id]['isselect'] = true;
                    $excludes = [];
                    if ($items = $DB->get_records('questionnaire_quest_choice',
                    ['question_id' => $question->id])) {

                        foreach ($items as $item) {
                            if (!in_array($item->id, $excludes)) {
                                $item->choice_id = $item->id;
                                if ($item->value == null) {
                                    $item->value = '';
                                }
                                $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                if ($question->type_id != 9) {
                                    if ($ret['questionsinfo'][$pagenum][$question->id]['required']) {
                                        if (!isset($ret['questionsinfo'][$pagenum][$question->id]['firstone'])) {
                                            $ret['questionsinfo'][$pagenum][$question->id]['firstone'] = true;
                                            $ret['questions'][$pagenum][$question->id][$item->id]->value = intval($item->choice_id);
                                            $ret['questions'][$pagenum][$question->id][$item->id]->firstone = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                case QUESRATE: // Rate 1-NN.
                    $excludes = [];
                    if ($items = $DB->get_records('questionnaire_quest_choice',
                        ['question_id' => $question->id])) {
                            $ret['questionsinfo'][$pagenum][$question->id]['israte'] = true;
                            $vals = $extracontents = [];
                        foreach ($items as $item) {
                            $item->na = false;
                            if ($question->precise == 0) {
                                $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                if ($ret['questionsinfo'][$pagenum][$question->id]['required'] == 'y') {
                                    $ret['questions'][$pagenum][$question->id][$item->id]->min
                                        = $ret['questions'][$pagenum][$question->id][$item->id]->minstr = 1;
                                } else {
                                    $ret['questions'][$pagenum][$question->id][$item->id]->min
                                        = $ret['questions'][$pagenum][$question->id][$item->id]->minstr = 0;
                                }
                                $ret['questions'][$pagenum][$question->id][$item->id]->max
                                    = $ret['questions'][$pagenum][$question->id][$item->id]->maxstr
                                    = intval($question->length);
                            } else if ($question->precise == 1) {
                                $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                if ($ret['questionsinfo'][$pagenum][$question->id]['required'] == 'y') {
                                    $ret['questions'][$pagenum][$question->id][$item->id]->min
                                        = $ret['questions'][$pagenum][$question->id][$item->id]->minstr = 1;
                                } else {
                                    $ret['questions'][$pagenum][$question->id][$item->id]->min
                                        = $ret['questions'][$pagenum][$question->id][$item->id]->minstr = 0;
                                }
                                $ret['questions'][$pagenum][$question->id][$item->id]->max = intval($question->length) + 1;
                                $ret['questions'][$pagenum][$question->id][$item->id]->na = true;
                            } else if ($question->precise > 1) {
                                $excludes[$item->id] = $item->id;
                                if ($item->value == null) {
                                    if ($arr = explode('|', $item->content)) {
                                        if (count($arr) == 2) {
                                            $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                            $ret['questions'][$pagenum][$question->id][$item->id]->content = '';
                                            $ret['questions'][$pagenum][$question->id][$item->id]->minstr = $arr[0];
                                            $ret['questions'][$pagenum][$question->id][$item->id]->maxstr = $arr[1];
                                        }
                                    }
                                } else {
                                    $val = intval($item->value);
                                    $vals[$val] = $val;
                                    $extracontents[] = $item->content;
                                }
                            }
                        }
                        if ($vals) {
                            if ($q = $ret['questions'][$pagenum][$question->id]) {
                                foreach (array_keys($q) as $itemid) {
                                    $ret['questions'][$pagenum][$question->id][$itemid]->min = min($vals);
                                    $ret['questions'][$pagenum][$question->id][$itemid]->max = max($vals);
                                }
                            }
                        }
                        if ($extracontents) {
                            $extracontents = array_unique($extracontents);
                            $extrahtml = '<br><ul>';
                            foreach ($extracontents as $extracontent) {
                                $extrahtml .= '<li>'.$extracontent.'</li>';
                            }
                            $extrahtml .= '</ul>';
                            $ret['questionsinfo'][$pagenum][$question->id]['content']
                                .= format_text($extrahtml, FORMAT_HTML, $options);
                        }
                        foreach ($items as $item) {
                            if (!in_array($item->id, $excludes)) {
                                $item->choice_id = $item->id;
                                if ($item->value == null) {
                                    $item->value = '';
                                }
                                $ret['questions'][$pagenum][$question->id][$item->id] = $item;
                                if ($question->type_id != QUESRATE) {
                                    if ($ret['questionsinfo'][$pagenum][$question->id]['required']) {
                                        if (!isset($ret['questionsinfo'][$pagenum][$question->id]['firstone'])) {
                                            $ret['questionsinfo'][$pagenum][$question->id]['firstone'] = true;
                                            $ret['questions'][$pagenum][$question->id][$item->id]->value = intval($item->choice_id);
                                            $ret['questions'][$pagenum][$question->id][$item->id]->firstone = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                case QUESPAGEBREAK:
                    $ret['questionscount']--;
                    $ret['pagescount']++;
                    $pagenum++;
                    $qnum--;
                    break;
            }
            $ret['questionsinfo'][$pagenum][$question->id]['qnum'] = $qnum;
            if ($ret['questionnaire']['autonumquestions']) {
                $ret['questionsinfo'][$pagenum][$question->id]['content'] =
                    $qnum.'. '.$ret['questionsinfo'][$pagenum][$question->id]['content'];
                $ret['questionsinfo'][$pagenum][$question->id]['content_stripped'] =
                    $qnum.'. '.$ret['questionsinfo'][$pagenum][$question->id]['content_stripped'];
            }
        }
        if ($userid) {
            if ($response = $DB->get_record_sql('SELECT qr.* FROM {questionnaire_response} qr '
                . 'LEFT JOIN {user} u ON qr.userid = u.id WHERE qr.questionnaireid = ? '
                . 'AND qr.userid = ?', [$questionnaire->id, $userid])) {
                $ret['response'] = (array) $response;
                $ret['response']['submitted_userdate'] = '';
                if (isset($ret['response']['submitted']) && !empty($ret['response']['submitted'])) {
                    $ret['response']['submitted_userdate'] = userdate($ret['response']['submitted']);
                }
                $ret['response']['fullname'] = fullname($DB->get_record('user', ['id' => $userid]));
                $ret['response']['userdate'] = userdate($ret['response']['submitted']);

                foreach ($ret['questionsinfo'] as $pagenum => $data1) {
                    foreach ($data1 as $questionid => $data2) {
                        $ret['answered'][$questionid] = false;
                        if (isset($data2['response_table']) && !empty($data2['response_table'])) {
                            if ($values = $DB->get_records_sql('SELECT * FROM {questionnaire_'
                                . $data2['response_table'] . '} WHERE response_id = ? AND question_id = ?',
                                [$response->id, $questionid])) {
                                foreach ($values as $value) {
                                    switch($data2['type_id']) {
                                        case QUESYESNO: // Yes/No bool.
                                            if (isset($ret['questions'][$pagenum][$questionid])) {
                                                if (isset($value->choice_id) && !empty($value->choice_id)) {
                                                    $ret['answered'][$questionid] = true;
                                                    if ($value->choice_id == 'y') {
                                                        $ret['questions'][$pagenum][$questionid][1]->value = 'y';
                                                        $ret['responses']['response_'.$data2['type_id'].'_'.$questionid] = 'y';
                                                    } else {
                                                        $ret['questions'][$pagenum][$questionid][0]->value = 'n';
                                                        $ret['responses']['response_'.$data2['type_id'].'_'.$questionid] = 'n';
                                                    }
                                                }
                                            }
                                            break;
                                        case QUESTEXT: // Text.
                                            if (isset($value->response) && !empty($value->response)) {
                                                $ret['answered'][$questionid] = true;
                                                $ret['questions'][$pagenum][$questionid][0]->value = $value->response;
                                                $ret['responses']['response_'.$data2['type_id'].'_'.$questionid] = $value->response;
                                            }
                                            break;
                                        case QUESESSAY: // Essay.
                                            if (isset($value->response) && !empty($value->response)) {
                                                $ret['answered'][$questionid] = true;
                                                $ret['questions'][$pagenum][$questionid][0]->value = $value->response;
                                                $ret['responses']['response_'.$data2['type_id'].'_'.$questionid] = $value->response;
                                            }
                                            break;
                                        case QUESRADIO: // Radiobutton.
                                            if ($value = $DB->get_records_sql('SELECT * FROM {questionnaire_'
                                                . $data2['response_table'] . '} WHERE response_id = ? AND question_id = ?',
                                                [$response->id, $questionid])) {
                                                foreach ($value as $row) {
                                                    foreach ($ret['questions'][$pagenum][$questionid] as $k => $item) {
                                                        if ($item->id == $row->choice_id) {
                                                            $ret['answered'][$questionid] = true;
                                                            $ret['questions'][$pagenum][$questionid][$k]->value = intval($item->id);
                                                            $ret['responses']['response_'.$data2['type_id']
                                                                .'_'.$questionid] = intval($item->id);
                                                        }
                                                    }
                                                }
                                            }
                                            break;
                                        case QUESCHECK: // Checkbox.
                                            if ($value = $DB->get_records_sql('SELECT * FROM {questionnaire_'
                                                . $data2['response_table'] . '} WHERE response_id = ? AND question_id = ?',
                                                [$response->id, $questionid])) {
                                                foreach ($value as $row) {
                                                    foreach ($ret['questions'][$pagenum][$questionid] as $k => $item) {
                                                        if ($item->id == $row->choice_id) {
                                                            $ret['answered'][$questionid] = true;
                                                            $ret['questions'][$pagenum][$questionid][$k]->value = intval($item->id);
                                                            $ret['responses']['response_'.$data2['type_id']
                                                                .'_'.$questionid] = intval($item->id);
                                                        }
                                                    }
                                                }
                                            }
                                        case QUESDROP: // Select.
                                            if ($value = $DB->get_records_sql('SELECT * FROM {questionnaire_'
                                                . $data2['response_table'] . '} WHERE response_id = ? AND question_id = ?',
                                                [$response->id, $questionid])) {
                                                foreach ($value as $row) {
                                                    foreach ($ret['questions'][$pagenum][$questionid] as $k => $item) {
                                                        if ($item->id == $row->choice_id) {
                                                            $ret['answered'][$questionid] = true;
                                                            $ret['questions'][$pagenum][$questionid][$k]->value = intval($item->id);
                                                            $ret['responses']['response_'.$data2['type_id']
                                                                .'_'.$questionid] = intval($item->id);
                                                        }
                                                    }
                                                }
                                            }
                                            break;
                                        case QUESRATE: // Rate 1-NN.
                                            if ($value = $DB->get_records_sql('SELECT * FROM {questionnaire_'
                                                . $data2['response_table'] . '} WHERE response_id = ? AND question_id = ?',
                                                [$response->id, $questionid])) {
                                                foreach ($value as $row) {
                                                    if ($questionid == $row->question_id) {
                                                        $ret['answered'][$questionid] = true;
                                                        $v = $row->rankvalue + 1;
                                                        if ($ret['questionsinfo'][$pagenum][$questionid]['precise'] == 1) {
                                                            if ($row->rankvalue == -1) {
                                                                $v = $ret['questions'][$pagenum][$questionid][$row->choice_id]->max;
                                                            }
                                                        }
                                                        $ret['questions'][$pagenum][$questionid][$row->choice_id]->value
                                                            = $ret['responses']['response_'.$data2['type_id']
                                                            .'_'.$questionid.'_'.$row->choice_id] = $v;
                                                        $ret['questions'][$pagenum][$questionid][$row->choice_id]->choice_id
                                                            = $row->choice_id;
                                                    }
                                                }
                                            }
                                        default:
                                            break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $ret;
}
function save_questionnaire_data($questionnaireid, $surveyid, $userid, $cmid, $sec, $completed, $submit, array $responses) {
    global $DB, $CFG;
    $ret = [
        'responses' => [],
        'warnings' => []
    ];
    if (!$completed) {
        require_once('questionnaire.class.php');
        $cm = get_coursemodule_from_id('questionnaire', $cmid);
        $questionnaire = new \questionnaire($questionnaireid, null,
            $DB->get_record('course', ['id' => $cm->course]), $cm);
        $rid = $questionnaire->delete_insert_response(
            $DB->get_field('questionnaire_response', 'id',
                ['questionnaireid' => $surveyid, 'complete' => 'n',
                    'userid' => $userid]), $sec, $userid);
        $questionnairedata = get_questionnaire_data($cmid, $userid);
        $pagequestions = isset($questionnairedata['questions'][$sec]) ? $questionnairedata['questions'][$sec] : [];
        if (!empty($pagequestions)) {
            $pagequestionsids = array_keys($pagequestions);
            $missingquestions = $warningmessages = [];
            foreach ($pagequestionsids as $questionid) {
                $missingquestions[$questionid] = $questionid;
            }
            foreach ($pagequestionsids as $questionid) {
                foreach ($responses as $response) {
                    $args = explode('_', $response['name']);
                    if (count($args) >= 3) {
                        $typeid = intval($args[1]);
                        $rquestionid = intval($args[2]);
                        if (in_array($rquestionid, $pagequestionsids)) {
                            unset($missingquestions[$rquestionid]);
                            if ($rquestionid == $questionid) {
                                if ($typeid == $questionnairedata['questionsinfo'][$sec][$rquestionid]['type_id']) {
                                    if ($rquestionid > 0 && !in_array($response['value'], array(-9999, 'undefined'))) {
                                        switch ($questionnairedata['questionsinfo'][$sec][$rquestionid]['type_id']) {
                                            case QUESRATE:
                                                if (isset($args[3]) && !empty($args[3])) {
                                                    $choiceid = intval($args[3]);
                                                    $value = intval($response['value']) - 1;
                                                    $rec = new \stdClass();
                                                    $rec->response_id = $rid;
                                                    $rec->question_id = intval($rquestionid);
                                                    $rec->choice_id = $choiceid;
                                                    $rec->rankvalue = $value;
                                                    if ($questionnairedata['questionsinfo'][$sec][$rquestionid]['precise'] == 1) {
                                                        if ($value == $questionnairedata['questions'][$sec]
                                                            [$rquestionid][$choiceid]->max - 1) {
                                                            $rec->rankvalue = -1;
                                                        }
                                                    }
                                                    $DB->insert_record('questionnaire_response_rank', $rec);
                                                }
                                                break;
                                            default:
                                                if ($questionnairedata['questionsinfo'][$sec][$rquestionid]['required'] == 'n'
                                                    || ($questionnairedata['questionsinfo'][$sec][$rquestionid]['required'] == 'y'
                                                        && !empty($response['value']))) {
                                                    $questionobj = \mod_questionnaire\question\base::question_builder(
                                                        $questionnairedata['questionsinfo'][$sec][$rquestionid]['type_id'],
                                                        $questionnairedata['questionsinfo'][$sec][$rquestionid]);
                                                    if ($questionobj->insert_response($rid, $response['value'])) {
                                                        $ret['responses'][$rid][$questionid] = $response['value'];
                                                    }
                                                } else {
                                                    $ret['warnings'][] = [
                                                        'item' => 'mod_questionnaire_question',
                                                        'itemid' => $questionid,
                                                        'warningcode' => 'required',
                                                        'message' => s(get_string('required') . ': '
                                                            . $questionnairedata['questionsinfo'][$sec][$questionid]['name'])
                                                    ];
                                                }
                                        }
                                    } else {
                                        $missingquestions[$rquestionid] = $rquestionid;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($missingquestions) {
                foreach ($missingquestions as $questionid) {
                    if ($questionnairedata['questionsinfo'][$sec][$questionid]['required'] == 'y') {
                        $ret['warnings'][] = [
                            'item' => 'mod_questionnaire_question',
                            'itemid' => $questionid,
                            'warningcode' => 'required',
                            'message' => s(get_string('required') . ': '
                                . $questionnairedata['questionsinfo'][$sec][$questionid]['name'])
                        ];
                    }
                }
            }
        }
    }
    if ($submit && (!isset($ret['warnings']) || empty($ret['warnings']))) {
        $questionnaire->commit_submission_response(
            $DB->get_field('questionnaire_response', 'id',
                ['questionnaireid' => $surveyid, 'complete' => 'n',
                    'userid' => $userid]), $userid);
    }
    return $ret;
}

function save_questionnaire_data_branching($questionnaireid, $surveyid, $userid,
    $cmid, $sec, $completed, $submit, array $responses) {

    global $DB, $CFG;
    $ret = [
        'responses' => [],
        'warnings' => []
    ];
    if (!$completed) {
        require_once('questionnaire.class.php');
        $cm = get_coursemodule_from_id('questionnaire', $cmid);
        $questionnaire = new \questionnaire($questionnaireid, null,
            $DB->get_record('course', ['id' => $cm->course]), $cm);
        $rid = $questionnaire->delete_insert_response(
            $DB->get_field('questionnaire_response', 'id',
                ['questionnaireid' => $surveyid, 'complete' => 'n',
                    'userid' => $userid]), $sec, $userid);
        $questionnairedata = get_questionnaire_data($cmid, $userid);
        $pagequestions = isset($questionnairedata['questions'][$sec]) ? $questionnairedata['questions'][$sec] : [];
        if (!empty($pagequestions)) {
            $pagequestionsids = array_keys($pagequestions);
            $missingquestions = $warningmessages = [];
            foreach ($pagequestionsids as $questionid) {
                $missingquestions[$questionid] = $questionid;
            }
            foreach ($pagequestionsids as $questionid) {
                foreach ($responses as $response) {
                    $args = explode('_', $response['name']);
                    if (count($args) >= 3) {
                        $typeid = intval($args[1]);
                        $rquestionid = intval($args[2]);
                        unset($missingquestions[$rquestionid]);
                        if ($typeid == $questionnairedata['questionsinfo'][$sec][$rquestionid]['type_id']) {
                            if ($rquestionid > 0 && !in_array($response['value'], array(-9999, 'undefined'))) {
                                if ($typeid == QUESCHECK && $response['value'] == 'true') {
                                    // If checkbox handle differently because we need to check if question value is set to true.
                                    if (isset($args[3]) && !empty($args[3])) {
                                        $choiceid = intval($args[3]);
                                        $rec = new \stdClass();
                                        $rec->response_id = $rid;
                                        $rec->question_id = intval($rquestionid);
                                        $rec->choice_id = $choiceid;

                                        $dupecheck = $DB->get_record('questionnaire_resp_multiple',
                                            ['response_id' => $rec->response_id,
                                            'question_id' => $rec->question_id,
                                            'choice_id' => $rec->choice_id]
                                        );

                                        if (empty($dupecheck)) {
                                            $DB->insert_record('questionnaire_resp_multiple', $rec);
                                        }
                                    }
                                } else if ($typeid == QUESRATE) { // Questionranking saving.
                                    if (isset($args[3]) && !empty($args[3])) {
                                        $choiceid = intval($args[3]);
                                        $value = intval($response['value']) - 1;
                                        $rec = new \stdClass();
                                        $rec->response_id = $rid;
                                        $rec->question_id = intval($rquestionid);
                                        $rec->choice_id = $choiceid;
                                        $rec->rankvalue = $value;
                                        if ($questionnairedata['questionsinfo'][$sec][$rquestionid]['precise'] == 1) {
                                            if ($value == $questionnairedata['questions'][$sec][$rquestionid][$choiceid]->max - 1) {
                                                $rec->rankvalue = -1;
                                            }
                                        }

                                        $dupecheck = $DB->get_record('questionnaire_response_rank',
                                            ['response_id' => $rec->response_id,
                                            'question_id' => $rec->question_id,
                                            'choice_id' => $rec->choice_id]
                                        );

                                        if (empty($dupecheck)) {
                                            $DB->insert_record('questionnaire_response_rank', $rec);
                                        }
                                    }
                                } else if ($typeid == QUESRADIO) {
                                    if (isset($args[2]) && !empty($args[2])) {
                                        $choiceid = intval($args[2]);
                                        $rec = new \stdClass();
                                        $rec->response_id = $rid;
                                        $rec->question_id = intval($rquestionid);
                                        $rec->choice_id = $response['value'];

                                        $dupecheck = $DB->get_record('questionnaire_resp_single',
                                            ['response_id' => $rec->response_id,
                                            'question_id' => $rec->question_id,
                                            'choice_id' => $rec->choice_id]
                                        );

                                        if (empty($dupecheck)) {
                                            $DB->insert_record('questionnaire_resp_single', $rec);
                                        }
                                    }
                                } else {
                                    $questionobj = \mod_questionnaire\question\base::question_builder(
                                    $questionnairedata['questionsinfo'][$sec][$rquestionid]['type_id'],
                                    $questionnairedata['questionsinfo'][$sec][$rquestionid]);

                                    $responsetable = 'questionnaire_';
                                    $responsetable = $responsetable .
                                    $questionnairedata['questionsinfo'][$sec][$rquestionid]['response_table'];

                                    $dupecheck = $DB->get_record($responsetable,
                                        ['response_id' => $rid,
                                        'question_id' => $rquestionid]
                                    );

                                    if (empty($dupecheck)) {
                                        if ($questionobj->insert_response($rid, $response['value'])) {
                                            $ret['responses'][$rid][$questionid] = $response['value'];
                                        }
                                    }
                                }
                            } else {
                                $missingquestions[$rquestionid] = $rquestionid;
                            }
                        }
                    }
                }
            }
            if ($missingquestions) {
                foreach ($missingquestions as $questionid) {
                    if ($questionnairedata['questionsinfo'][$sec][$questionid]['required'] == 'y') {
                        $ret['warnings'][] = [
                            'item' => 'mod_questionnaire_question',
                            'itemid' => $questionid,
                            'warningcode' => 'required',
                            'message' => s(get_string('required') . ': '
                                . $questionnairedata['questionsinfo'][$sec][$questionid]['name'])
                        ];
                    }
                }
            }
        }
    }

    if ($submit && (!isset($ret['warnings']) || empty($ret['warnings']))) {
        $questionnaire->commit_submission_response(
            $DB->get_field('questionnaire_response', 'id',
                ['questionnaireid' => $surveyid, 'complete' => 'n',
                    'userid' => $userid]), $userid);
    }
    return $ret;
}

/**
 * Serves the questionnaire attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 *
 * $forcedownload is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = ['intro', 'info', 'thankbody', 'question', 'feedbacknotes', 'sectionheading', 'feedback'];
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $componentid = (int)array_shift($args);

    if ($filearea == 'question') {
        if (!$DB->record_exists('questionnaire_question', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'sectionheading') {
        if (!$DB->record_exists('questionnaire_fb_sections', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'feedback') {
        if (!$DB->record_exists('questionnaire_feedback', ['id' => $componentid])) {
            return false;
        }
    } else {
        if (!$DB->record_exists('questionnaire_survey', ['id' => $componentid])) {
            return false;
        }
    }

    if (!$DB->record_exists('questionnaire', ['id' => $cm->instance])) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_questionnaire/$filearea/$componentid/$relativepath";
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
}
/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $questionnairenode The node to add module settings to
 *
 * $settings is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_extend_settings_navigation(settings_navigation $settings,
        navigation_node $questionnairenode) {

    global $PAGE, $DB, $USER, $CFG;
    $individualresponse = optional_param('individualresponse', false, PARAM_INT);
    $rid = optional_param('rid', false, PARAM_INT); // Response id.
    $currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

    require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

    $context = $PAGE->cm->context;
    $cmid = $PAGE->cm->id;
    $cm = $PAGE->cm;
    $course = $PAGE->course;

    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

    $courseid = $course->id;
    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

    if ($owner = $DB->get_field('questionnaire_survey', 'courseid', ['id' => $questionnaire->sid])) {
        $owner = (trim($owner) == trim($courseid));
    } else {
        $owner = true;
    }

    // On view page, currentgroupid is not yet sent as an optional_param, so get it.
    $groupmode = groups_get_activity_groupmode($cm, $course);
    if ($groupmode > 0 && $currentgroupid == 0) {
        $currentgroupid = groups_get_activity_group($questionnaire->cm);
        if (!groups_is_member($currentgroupid, $USER->id)) {
            $currentgroupid = 0;
        }
    }

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $questionnairenode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if (($i === false) && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/questionnaire:manage', $context) && $owner) {
        $url = '/mod/questionnaire/qsettings.php';
        $node = navigation_node::create(get_string('advancedsettings'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'advancedsettings',
            new pix_icon('t/edit', ''));
        $questionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/questionnaire:editquestions', $context) && $owner) {
        $url = '/mod/questionnaire/questions.php';
        $node = navigation_node::create(get_string('questions', 'questionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'questions',
            new pix_icon('t/edit', ''));
        $questionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/questionnaire:editquestions', $context) && $owner) {
        $url = '/mod/questionnaire/feedback.php';
        $node = navigation_node::create(get_string('feedback', 'questionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'feedback',
            new pix_icon('t/edit', ''));
        $questionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/questionnaire:preview', $context)) {
        $url = '/mod/questionnaire/preview.php';
        $node = navigation_node::create(get_string('preview_label', 'questionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'preview',
            new pix_icon('t/preview', ''));
        $questionnairenode->add_node($node, $beforekey);
    }

    if ($questionnaire->user_can_take($USER->id)) {
        $url = '/mod/questionnaire/complete.php';
        if ($questionnaire->user_has_saved_response($USER->id)) {
            $args = ['id' => $cmid, 'resume' => 1];
            $text = get_string('resumesurvey', 'questionnaire');
        } else {
            $args = ['id' => $cmid];
            $text = get_string('answerquestions', 'questionnaire');
        }
        $node = navigation_node::create($text, new moodle_url($url, $args),
            navigation_node::TYPE_SETTING, null, '', new pix_icon('i/info', 'answerquestions'));
        $questionnairenode->add_node($node, $beforekey);
    }
    $usernumresp = $questionnaire->count_submissions($USER->id);

    if ($questionnaire->capabilities->readownresponses && ($usernumresp > 0)) {
        $url = '/mod/questionnaire/myreport.php';

        if ($usernumresp > 1) {
            $urlargs = array('instance' => $questionnaire->id, 'userid' => $USER->id,
                'byresponse' => 0, 'action' => 'summary', 'group' => $currentgroupid);
            $node = navigation_node::create(get_string('yourresponses', 'questionnaire'),
                new moodle_url($url, $urlargs), navigation_node::TYPE_SETTING, null, 'yourresponses');
            $myreportnode = $questionnairenode->add_node($node, $beforekey);

            $urlargs = array('instance' => $questionnaire->id, 'userid' => $USER->id,
                'byresponse' => 0, 'action' => 'summary', 'group' => $currentgroupid);
            $myreportnode->add(get_string('summary', 'questionnaire'), new moodle_url($url, $urlargs));

            $urlargs = array('instance' => $questionnaire->id, 'userid' => $USER->id,
                'byresponse' => 1, 'action' => 'vresp', 'group' => $currentgroupid);
            $byresponsenode = $myreportnode->add(get_string('viewindividualresponse', 'questionnaire'),
                new moodle_url($url, $urlargs));

            $urlargs = array('instance' => $questionnaire->id, 'userid' => $USER->id,
                'byresponse' => 0, 'action' => 'vall', 'group' => $currentgroupid);
            $myreportnode->add(get_string('myresponses', 'questionnaire'), new moodle_url($url, $urlargs));
            if ($questionnaire->capabilities->downloadresponses) {
                $urlargs = array('instance' => $questionnaire->id, 'user' => $USER->id,
                    'action' => 'dwnpg', 'group' => $currentgroupid);
                $myreportnode->add(get_string('downloadtextformat', 'questionnaire'),
                    new moodle_url('/mod/questionnaire/report.php', $urlargs));
            }
        } else {
            $urlargs = array('instance' => $questionnaire->id, 'userid' => $USER->id,
                'byresponse' => 1, 'action' => 'vresp', 'group' => $currentgroupid);
            $node = navigation_node::create(get_string('yourresponse', 'questionnaire'),
                new moodle_url($url, $urlargs), navigation_node::TYPE_SETTING, null, 'yourresponse');
            $myreportnode = $questionnairenode->add_node($node, $beforekey);
        }
    }

    // If questionnaire is set to separate groups, prevent user who is not member of any group
    // and is not a non-editing teacher to view All responses.
    if ($questionnaire->can_view_all_responses($usernumresp)) {

        $url = '/mod/questionnaire/report.php';
        $node = navigation_node::create(get_string('viewallresponses', 'questionnaire'),
            new moodle_url($url, array('instance' => $questionnaire->id, 'action' => 'vall')),
            navigation_node::TYPE_SETTING, null, 'vall');
        $reportnode = $questionnairenode->add_node($node, $beforekey);

        if ($questionnaire->capabilities->viewsingleresponse) {
            $summarynode = $reportnode->add(get_string('summary', 'questionnaire'),
                new moodle_url('/mod/questionnaire/report.php',
                    array('instance' => $questionnaire->id, 'action' => 'vall')));
        } else {
            $summarynode = $reportnode;
        }
        $summarynode->add(get_string('order_default', 'questionnaire'),
            new moodle_url('/mod/questionnaire/report.php',
                array('instance' => $questionnaire->id, 'action' => 'vall', 'group' => $currentgroupid)));
        $summarynode->add(get_string('order_ascending', 'questionnaire'),
            new moodle_url('/mod/questionnaire/report.php',
                array('instance' => $questionnaire->id, 'action' => 'vallasort', 'group' => $currentgroupid)));
        $summarynode->add(get_string('order_descending', 'questionnaire'),
            new moodle_url('/mod/questionnaire/report.php',
                array('instance' => $questionnaire->id, 'action' => 'vallarsort', 'group' => $currentgroupid)));

        if ($questionnaire->capabilities->deleteresponses) {
            $summarynode->add(get_string('deleteallresponses', 'questionnaire'),
                new moodle_url('/mod/questionnaire/report.php',
                    array('instance' => $questionnaire->id, 'action' => 'delallresp', 'group' => $currentgroupid)));
        }

        if ($questionnaire->capabilities->downloadresponses) {
            $summarynode->add(get_string('downloadtextformat', 'questionnaire'),
                new moodle_url('/mod/questionnaire/report.php',
                    array('instance' => $questionnaire->id, 'action' => 'dwnpg', 'group' => $currentgroupid)));
        }
        if ($questionnaire->capabilities->viewsingleresponse) {
            $byresponsenode = $reportnode->add(get_string('viewbyresponse', 'questionnaire'),
                new moodle_url('/mod/questionnaire/report.php',
                    array('instance' => $questionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid)));

            $byresponsenode->add(get_string('view', 'questionnaire'),
                new moodle_url('/mod/questionnaire/report.php',
                    array('instance' => $questionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid)));

            if ($individualresponse) {
                $byresponsenode->add(get_string('deleteresp', 'questionnaire'),
                    new moodle_url('/mod/questionnaire/report.php',
                        array('instance' => $questionnaire->id, 'action' => 'dresp', 'byresponse' => 1,
                            'rid' => $rid, 'group' => $currentgroupid, 'individualresponse' => 1)));
            }
        }
    }

    $canviewgroups = true;
    $groupmode = groups_get_activity_groupmode($cm, $course);
    if ($groupmode == 1) {
        $canviewgroups = groups_has_membership($cm, $USER->id);
    }
    $canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
    if ($questionnaire->capabilities->viewsingleresponse && ($canviewallgroups || $canviewgroups)) {
        $url = '/mod/questionnaire/show_nonrespondents.php';
        $node = navigation_node::create(get_string('show_nonrespondents', 'questionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'nonrespondents');
        $questionnairenode->add_node($node, $beforekey);

    }
}

// Any other questionnaire functions go here.  Each of them must have a name that
// starts with questionnaire_.

function questionnaire_get_view_actions() {
    return array('view', 'view all');
}

function questionnaire_get_post_actions() {
    return array('submit', 'update');
}

function questionnaire_get_recent_mod_activity(&$activities, &$index, $timestart,
                $courseid, $cmid, $userid = 0, $groupid = 0) {

    global $CFG, $COURSE, $USER, $DB;
    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
    require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', ['id' => $courseid]);
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $questionnaire = $DB->get_record('questionnaire', ['id' => $cm->instance]);
    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

    $context = context_module::instance($cm->id);
    $grader = has_capability('mod/questionnaire:viewsingleresponse', $context);

    // If this is a copy of a public questionnaire whose original is located in another course,
    // current user (teacher) cannot view responses.
    if ($grader) {
        // For a public questionnaire, look for the original public questionnaire that it is based on.
        if (!$questionnaire->survey_is_public_master()) {
            // For a public questionnaire, look for the original public questionnaire that it is based on.
            $originalquestionnaire = $DB->get_record('questionnaire',
                ['sid' => $questionnaire->survey->id, 'course' => $questionnaire->survey->courseid]);
            $cmoriginal = get_coursemodule_from_instance("questionnaire", $originalquestionnaire->id,
                $questionnaire->survey->courseid);
            $contextoriginal = context_course::instance($questionnaire->survey->courseid, MUST_EXIST);
            if (!has_capability('mod/questionnaire:viewsingleresponse', $contextoriginal)) {
                $tmpactivity = new stdClass();
                $tmpactivity->type = 'questionnaire';
                $tmpactivity->cmid = $cm->id;
                $tmpactivity->cannotview = true;
                $tmpactivity->anonymous = false;
                $activities[$index++] = $tmpactivity;
                return $activities;
            }
        }
    }

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['timestart'] = $timestart;
    $params['questionnaireid'] = $questionnaire->id;

    $ufields = user_picture::fields('u', null, 'useridagain');
    if (!$attempts = $DB->get_records_sql("
                    SELECT qr.*,
                    {$ufields}
                    FROM {questionnaire_response} qr
                    JOIN {user} u ON u.id = qr.userid
                    $groupjoin
                    WHERE qr.submitted > :timestart
                    AND qr.questionnaireid = :questionnaireid
                    $userselect
                    $groupselect
                    ORDER BY qr.submitted ASC", $params)) {
        return;
    }

    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    $usersgroups = null;
    $aname = format_string($cm->name, true);
    $userattempts = array();
    foreach ($attempts as $attempt) {
        if ($questionnaire->respondenttype != 'anonymous') {
            if (!isset($userattempts[$attempt->lastname])) {
                $userattempts[$attempt->lastname] = 1;
            } else {
                $userattempts[$attempt->lastname]++;
            }
        }
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // View complete individual responses permission required.
                continue;
            }

            if (($groupmode == SEPARATEGROUPS) && !$accessallgroups) {
                if ($usersgroups === null) {
                    $usersgroups = groups_get_all_groups($course->id,
                    $attempt->userid, $cm->groupingid);
                    if (is_array($usersgroups)) {
                        $usersgroups = array_keys($usersgroups);
                    } else {
                         $usersgroups = array();
                    }
                }
                if (!array_intersect($usersgroups, $modinfo->groups[$cm->id])) {
                    continue;
                }
            }
        }

        $tmpactivity = new stdClass();

        $tmpactivity->type       = 'questionnaire';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->cminstance = $cm->instance;
        // Current user is admin - or teacher enrolled in original public course.
        if (isset($cmoriginal)) {
            $tmpactivity->cminstance = $cmoriginal->instance;
        }
        $tmpactivity->cannotview = false;
        $tmpactivity->anonymous  = false;
        $tmpactivity->name       = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = $attempt->submitted;
        $tmpactivity->groupid    = $groupid;
        if (isset($userattempts[$attempt->lastname])) {
            $tmpactivity->nbattempts = $userattempts[$attempt->lastname];
        }

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->attemptid = $attempt->id;

        $userfields = explode(',', user_picture::fields());
        $tmpactivity->user = new stdClass();
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                $tmpactivity->user->{$userfield} = $attempt->userid;
            } else {
                if (!empty($attempt->{$userfield})) {
                    $tmpactivity->user->{$userfield} = $attempt->{$userfield};
                } else {
                    $tmpactivity->user->{$userfield} = null;
                }
            }
        }
        if ($questionnaire->respondenttype != 'anonymous') {
            $tmpactivity->user->fullname  = fullname($attempt, $viewfullnames);
        } else {
            $tmpactivity->user = '';
            unset ($tmpactivity->user);
            $tmpactivity->anonymous = true;
        }
        $activities[$index++] = $tmpactivity;
    }
}

/**
 * Prints all users who have completed a specified questionnaire since a given time
 *
 * @global object
 * @param object $activity
 * @param int $courseid
 * @param string $detail not used but needed for compability
 * @param array $modnames
 * @return void Output is echo'd
 *
 * $details and $modenames are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $OUTPUT;

    // If the questionnaire is "anonymous", then $activity->user won't have been set, so do not display respondent info.
    if ($activity->anonymous) {
        $stranonymous = ' ('.get_string('anonymous', 'questionnaire').')';
        $activity->nbattempts = '';
    } else {
        $stranonymous = '';
    }
    // Current user cannot view responses to public questionnaire.
    if ($activity->cannotview) {
        $strcannotview = get_string('cannotviewpublicresponses', 'questionnaire');
    }
    echo html_writer::start_tag('div');
    echo html_writer::start_tag('span', array('class' => 'clearfix',
                    'style' => 'margin-top:0px; background-color: white; display: inline-block;'));

    if (!$activity->anonymous && !$activity->cannotview) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid' => $courseid)),
                        array('style' => 'float: left; padding-right: 10px;'));
    }
    if (!$activity->cannotview) {
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('div');

        $urlparams = array('action' => 'vresp', 'instance' => $activity->cminstance,
                        'group' => $activity->groupid, 'rid' => $activity->content->attemptid, 'individualresponse' => 1);

        $context = context_module::instance($activity->cmid);
        if (has_capability('mod/questionnaire:viewsingleresponse', $context)) {
            $report = 'report.php';
        } else {
            $report = 'myreport.php';
        }
        echo html_writer::tag('a', get_string('response', 'questionnaire').' '.$activity->nbattempts.$stranonymous,
                        array('href' => new moodle_url('/mod/questionnaire/'.$report, $urlparams)));
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('div');
        echo html_writer::tag('div', $strcannotview);
        echo html_writer::end_tag('div');
    }
    if (!$activity->anonymous  && !$activity->cannotview) {
        $url = new moodle_url('/user/view.php', array('course' => $courseid, 'id' => $activity->user->id));
        $name = $activity->user->fullname;
        $link = html_writer::link($url, $name);
        echo html_writer::start_tag('div', array('class' => 'user'));
        echo $link .' - '. userdate($activity->timestamp);
        echo html_writer::end_tag('div');
    }

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('span');
    echo html_writer::end_tag('div');

    return;
}

/**
 * Prints questionnaire summaries on 'My home' page
 *
 * Prints questionnaire name, due date and attempt information on
 * questionnaires that have a deadline that has not already passed
 * and it is available for taking.
 *
 * @global object
 * @global stdClass
 * @global object
 * @uses CONTEXT_MODULE
 * @param array $courses An array of course objects to get questionnaire instances from
 * @param array $htmlarray Store overview output array( course ID => 'questionnaire' => HTML output )
 * @return void
 */
function questionnaire_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $OUTPUT;

    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

    if (!$questionnaires = get_all_instances_in_courses('questionnaire', $courses)) {
        return;
    }

    // Get Necessary Strings.
    $strquestionnaire       = get_string('modulename', 'questionnaire');
    $strnotattempted = get_string('noattempts', 'questionnaire');
    $strattempted    = get_string('attempted', 'questionnaire');
    $strsavedbutnotsubmitted = get_string('savedbutnotsubmitted', 'questionnaire');

    $now = time();
    foreach ($questionnaires as $questionnaire) {

        // The questionnaire has a deadline.
        if (($questionnaire->closedate != 0)
                        // And it is before the deadline has been met.
                        && ($questionnaire->closedate >= $now)
                        // And the questionnaire is available.
                        && (($questionnaire->opendate == 0) || ($questionnaire->opendate <= $now))) {
            if (!$questionnaire->visible) {
                $class = ' class="dimmed"';
            } else {
                $class = '';
            }
            $str = $OUTPUT->box("$strquestionnaire:
                            <a$class href=\"$CFG->wwwroot/mod/questionnaire/view.php?id=$questionnaire->coursemodule\">".
                            format_string($questionnaire->name).'</a>', 'name');

            // Deadline.
            $str .= $OUTPUT->box(get_string('closeson', 'questionnaire', userdate($questionnaire->closedate)), 'info');
            $attempts = $DB->get_records('questionnaire_response',
                ['questionnaireid' => $questionnaire->id, 'userid' => $USER->id, 'complete' => 'y']);
            $nbattempts = count($attempts);

            // Do not display a questionnaire as due if it can only be sumbitted once and it has already been submitted!
            if ($nbattempts != 0 && $questionnaire->qtype == QUESTIONNAIREONCE) {
                continue;
            }

            // Attempt information.
            if (has_capability('mod/questionnaire:manage', context_module::instance($questionnaire->coursemodule))) {
                // Number of user attempts.
                $attempts = $DB->count_records('questionnaire_response',
                    ['questionnaireid' => $questionnaire->id, 'complete' => 'y']);
                $str .= $OUTPUT->box(get_string('numattemptsmade', 'questionnaire', $attempts), 'info');
            } else {
                if ($responses = questionnaire_get_user_responses($questionnaire->id, $USER->id, false)) {
                    foreach ($responses as $response) {
                        if ($response->complete == 'y') {
                            $str .= $OUTPUT->box($strattempted, 'info');
                            break;
                        } else {
                            $str .= $OUTPUT->box($strsavedbutnotsubmitted, 'info');
                        }
                    }
                } else {
                    $str .= $OUTPUT->box($strnotattempted, 'info');
                }
            }
            $str = $OUTPUT->box($str, 'questionnaire overview');

            if (empty($htmlarray[$questionnaire->course]['questionnaire'])) {
                $htmlarray[$questionnaire->course]['questionnaire'] = $str;
            } else {
                $htmlarray[$questionnaire->course]['questionnaire'] .= $str;
            }
        }
    }
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the questionnaire.
 *
 * @param $mform the course reset form that is being built.
 */
function questionnaire_reset_course_form_definition($mform) {
    $mform->addElement('header', 'questionnaireheader', get_string('modulenameplural', 'questionnaire'));
    $mform->addElement('advcheckbox', 'reset_questionnaire',
                    get_string('removeallquestionnaireattempts', 'questionnaire'));
}

/**
 * Course reset form defaults.
 * @return array the defaults.
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_reset_course_form_defaults($course) {
    return array('reset_questionnaire' => 1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * questionnaire responses for course $data->courseid.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function questionnaire_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

    $componentstr = get_string('modulenameplural', 'questionnaire');
    $status = array();

    if (!empty($data->reset_questionnaire)) {
        $surveys = questionnaire_get_survey_list($data->courseid, '');

        // Delete responses.
        foreach ($surveys as $survey) {
            // Get all responses for this questionnaire.
            $sql = "SELECT qr.id, qr.questionnaireid, qr.submitted, qr.userid, q.sid
                 FROM {questionnaire} q
                 INNER JOIN {questionnaire_response} qr ON q.id = qr.questionnaireid
                 WHERE q.sid = ?
                 ORDER BY qr.id";
            $resps = $DB->get_records_sql($sql, [$survey->id]);
            if (!empty($resps)) {
                $questionnaire = $DB->get_record("questionnaire", ["sid" => $survey->id, "course" => $survey->courseid]);
                $questionnaire->course = $DB->get_record("course", array("id" => $questionnaire->course));
                foreach ($resps as $response) {
                    questionnaire_delete_response($response, $questionnaire);
                }
            }
            // Remove this questionnaire's grades (and feedback) from gradebook (if any).
            $select = "itemmodule = 'questionnaire' AND iteminstance = ".$survey->qid;
            $fields = 'id';
            if ($itemid = $DB->get_record_select('grade_items', $select, null, $fields)) {
                $itemid = $itemid->id;
                $DB->delete_records_select('grade_grades', 'itemid = '.$itemid);

            }
        }
        $status[] = array(
                        'component' => $componentstr,
                        'item' => get_string('deletedallresp', 'questionnaire'),
                        'error' => false);

        $status[] = array(
                        'component' => $componentstr,
                        'item' => get_string('gradesdeleted', 'questionnaire'),
                        'error' => false);
    }
    return $status;
}

/**
 * Obtains the automatic completion state for this questionnaire based on the condition
 * in questionnaire settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 *
 * $course is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function questionnaire_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get questionnaire details.
    $questionnaire = $DB->get_record('questionnaire', array('id' => $cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false.
    if ($questionnaire->completionsubmit) {
        $params = ['userid' => $userid, 'questionnaireid' => $questionnaire->id, 'complete' => 'y'];
        return $DB->record_exists('questionnaire_response', $params);
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_questionnaire_core_calendar_provide_event_action(calendar_event $event,
                                                            \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['questionnaire'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
            get_string('view'),
            new \moodle_url('/mod/questionnaire/view.php', ['id' => $cm->id]),
            1,
            true
    );
}

/**
 * custom version of functions required for mobile
 */
function get_mobile_response($userid, $rid = 0, $qid = 0) {
    global $DB;

    $rid = intval($rid);
    if ($rid != 0) {
        // Check for valid rid.
        $fields = 'id, userid';
        $params = ['id' => $rid, 'questionnaireid' => $qid, 'userid' => $userid, 'complete' => 'n'];
        return ($DB->get_record('questionnaire_response', $params, $fields) !== false) ? $rid : '';

    } else {
        // Find latest in progress rid.
        $params = ['questionnaireid' => $qid, 'userid' => $userid, 'complete' => 'n'];
        if ($records = $DB->get_records('questionnaire_response', $params, 'submitted DESC', 'id,questionnaireid', 0, 1)) {
            $rec = reset($records);
            return $rec->id;
        } else {
            return '';
        }
    }
}

function get_mobile_questionnaire($questionnaire, $pagenum, $branching = 0) {
    global $DB;
    // Need to change the page num based on.
    // The check for required questions.
    // That's the logic I am thinking about.
    // Eg page num is 3 if you have never done a course.

    if (!empty($questionnaire['questionsinfo'][1])) {
        $surveyinfo = $questionnaire['questionsinfo'][1];
        $surveyinfo = array_shift($surveyinfo);
        $sid = $surveyinfo['surveyid'];

    }
    // Logic for resuming questionnaire for mobile.
    $prevpage = 1;
    $responses = $questionnaire['responses'];
    foreach ($responses as $key => $response) {
        $args = explode('_', $key);
        if ($args[1] == 1 || $args[1] != $pagenum) {
            $prevpage = (int)$args[1];
        }
    }

    $questionnairedependency = $DB->get_records('questionnaire_dependency', ['surveyid' => $sid]);
    $nondependentquestions = array();

    foreach ($questionnaire['fields'] as $question) {
        $nondependentquestions[$question['id']] = array(
            'id' => $question['id'],
            'qnum' => $question['qnum']
        );
    }

    foreach ($questionnairedependency as $dependency) {
        if (!empty($nondependentquestions[$dependency->questionid])) {
            unset($nondependentquestions[$dependency->questionid]);
        }
        foreach ($nondependentquestions as $nondependent) {
            if ($questionnaire['answered'][$nondependent['id']] === true
                && !empty($questionnaire['resumed'])) { // Resuming questionnaire here.
                unset($nondependentquestions[$nondependent['id']]);
            } else {
                array_shift($nondependentquestions);
                break;
            }
        }
    }

    if (count($questionnairedependency) > 0) {
        foreach ($questionnaire['fields'] as $question) {
            if ($question['qnum'] == $pagenum) {
                foreach ($questionnairedependency as $dependency) {
                    if ($dependency->questionid == $question['id']) {
                        $answereddependency = ($questionnaire['responses']['response_'.$dependency->dependchoiceid.
                            '_'.$dependency->dependquestionid] == 'n' ? 1 : 0);
                        // The dependelogic is an id 0 = y and 1 = no, quesitonnaire is weird.
                        if ($answereddependency == $dependency->dependlogic) {
                            // Find next question that does not have dependency.
                            $pagenums = array(
                                'prevpage' => $pagenum - 1,
                                'pagenum' => $pagenum,
                                'nextpage' => $pagenum + 1
                            );
                            return $pagenums;
                        } else {
                            $nextpage = array_shift(array_slice($nondependentquestions, 1, 1, true));
                            $pagenum = array_shift($nondependentquestions);
                            if ($pagenum['qnum'] == 1) {
                                $prevpage = null;
                                $pagenum = 1;
                                $nextpage = $nextpage['qnum'] - 1;
                            } else {
                                $pagenum = $pagenum['qnum'] - 1;
                                $nextpage = $nextpage['qnum'] - 1;
                            }
                            $pagenums = array(
                                'prevpage' => $prevpage,
                                'pagenum' => $pagenum,
                                'nextpage' => $nextpage,
                            );
                            return $pagenums;
                            // Need to get page next page num without any dependencies.
                        }
                    } else {
                        $pagenums = array(
                            'prevpage' => $prevpage,
                            'pagenum' => $pagenum,
                            'nextpage' => $pagenum + 1,
                        );
                        return $pagenums;
                    }
                }
            }
        }
    } else {
        $pagenums = array(
            'prevpage' => $pagenum - 1,
            'pagenum' => $pagenum,
            'nextpage' => $pagenum + 1,
        );
        return $pagenums;
    }
}

function check_mobile_branching_logic($questionnaire) {
    global $DB;

    $surveyinfo = [];
    $sid = 0;

    if (!empty($questionnaire['questionsinfo'][1])) {
        $surveyinfo = $questionnaire['questionsinfo'][1];
        $surveyinfo = array_shift($surveyinfo);
        $sid = $surveyinfo['surveyid'];
    }

    $questionnairedependency = $DB->get_records('questionnaire_dependency', ['surveyid' => $sid]);

    if (!empty($questionnairedependency)) {
        return true;
    }
    return false;
}
