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
 * Mobile output class for mod_questionnaire.
 *
 * @copyright 2018 Igor Sazonov <sovletig@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $OUTPUT, $USER, $CFG, $DB, $SESSION;

        require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');
        $args = (object) $args;
        $cmid = $args->cmid;
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;
        $prevpage = 0;
        if (!empty($SESSION->prevpage)) {
            $prevpage = $SESSION->prevpage;
            if (!$prevpage) {
                $SESSION->prevpage = $pagenum;
                if ($pagenum == 1) {
                    $prevpage = 0;
                } else {
                    $prevpage = $pagenum;
                }
            }
        }
        // Capabilities check.
        $cm = get_coursemodule_from_id('questionnaire', $cmid);
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questionnaire:view');
        // Set some variables we are going to be using.
        $questionnaire = get_questionnaire_data($cmid, $USER->id);
        if (isset($questionnaire['questions'][$pagenum - 1]) && !empty($questionnaire['questions'][$pagenum - 1])) {
            $prevpage = $pagenum - 1;
        }
        $data = [
            'questionnaire' => $questionnaire,
            'cmid' => $cmid,
            'courseid' => intval($cm->course),
            'pagenum' => $pagenum,
            'userid' => $USER->id,
            'nextpage' => 0,
            'prevpage' => 0,
            'emptypage' => false
        ];
        $pagebreaks = false;
        $branching = check_mobile_branching_logic($questionnaire);
        if ($branching) {
            $pagebreaks = true;
        }
        $break = false;
        // Checking for completion below, cmid is a complettion variable.
        // Checks whether a questionnaire was touched, so created a proper completed check coming from the DB.
        if ($cmid) {
            $data['completed'] = (isset($questionnaire['response']['complete'])
                && $questionnaire['response']['complete'] == 'y') ? 1 : 0;

            $data['complete_userdate'] = (isset($questionnaire['response']['complete'])
                && $questionnaire['response']['complete'] == 'y') ?
                userdate($questionnaire['response']['submitted']) : '';
            if (isset($questionnaire['questions'][$pagenum]) && $branching == false) {
                $i = 0;
                foreach ($questionnaire['questions'][$pagenum] as $questionid => $choices) {
                    if (isset($questionnaire['questionsinfo'][$pagenum][$questionid])
                        && !empty($questionnaire['questionsinfo'][$pagenum][$questionid])) {
                        $data['questions'][$pagenum][$i]['info'] = $questionnaire['questionsinfo'][$pagenum][$questionid];
                        if ($data['questions'][$pagenum][$i]['info']['required'] == 'n') {
                            unset($data['questions'][$pagenum][$i]['info']['required']);
                        }
                        $ii = 0;
                        foreach ($choices as $k => $v) {
                            $data['questions'][$pagenum][$i]['choices'][$ii] = (array) $v;
                            $ii++;
                        }
                        if (count($choices) == 1) {
                            $data['questions'][$pagenum][$i]['value'] = $data['questions'][$pagenum][$i]['choices'][0]['value'];
                        }
                        $i++;
                    }
                }
                if (isset($data['questions'][$pagenum]) && !empty($data['questions'][$pagenum])) {
                    $i = 0;
                    foreach ($data['questions'][$pagenum] as $arr) {
                        $data['pagequestions'][$i] = $arr;
                        $i++;
                    }
                }
                if (isset($questionnaire['questions'][$pagenum + 1]) && !empty($questionnaire['questions'][$pagenum + 1])) {
                    $data['nextpage'] = $pagenum + 1;
                }
                if ($prevpage) {
                    $data['prevpage'] = $prevpage;
                }
            } else if (isset($questionnaire['questions'][$pagenum]) && $branching == true
                && $questionnaire['completed'] == false ) {
                $i = 0;
                foreach ($questionnaire['questions'][$pagenum] as $questionid => $choices) {
                    if (isset($questionnaire['questionsinfo'][$pagenum][$questionid]) &&
                        !empty($questionnaire['questionsinfo'][$pagenum][$questionid])) {
                        $data['questions'][$pagenum][$i]['info'] = $questionnaire['questionsinfo'][$pagenum][$questionid];
                        if ($data['questions'][$pagenum][$i]['info']['required'] == 'n') {
                            unset($data['questions'][$pagenum][$i]['info']['required']);
                        }
                        $ii = 0;
                        foreach ($choices as $k => $v) {
                            $data['questions'][$pagenum][$i]['choices'][$ii] = (array) $v;
                            $ii++;
                        }
                        if (count($choices) == 1) {
                            $data['questions'][$pagenum][$i]['value'] = $data['questions'][$pagenum][$i]['choices'][0]['value'];
                        }
                        $i++;
                    }
                }
                if (isset($data['questions'][$pagenum]) && !empty($data['questions'][$pagenum])) {
                    $i = 0;
                    foreach ($data['questions'][$pagenum] as $arr) {
                        $data['pagequestions'][$i] = $arr;
                        $i++;
                    }
                }
                if (isset($questionnaire['questions'][$pagenum + 1]) && !empty($questionnaire['questions'][$pagenum + 1])) {
                    $data['nextpage'] = $pagenum + 1;
                }
                if ($prevpage) {
                    $data['prevpage'] = $prevpage;
                }
            }

            if ($questionnaire['completed'] == true) {
                // Branching specific logic.
                // If we are branching and the questionnaire is complete, display all the responses on one page.
                $pagecounter = 1;
                foreach ($questionnaire['questions'] as $question) {
                    $i = 0;
                    foreach ($questionnaire['questions'][$pagecounter] as $questionid => $choices) {
                        if (isset($questionnaire['questionsinfo'][$pagecounter][$questionid]) &&
                            !empty($questionnaire['questionsinfo'][$pagecounter][$questionid])) {
                            $data['questions'][$pagecounter][$i]['info']
                            = $questionnaire['questionsinfo'][$pagecounter][$questionid];
                            if ($data['questions'][$pagecounter][$i]['info']['required'] == 'n') {
                                unset($data['questions'][$pagecounter][$i]['info']['required']);
                            }
                            $ii = 0;
                            foreach ($choices as $k => $v) {
                                $data['questions'][$pagecounter][$i]['choices'][$ii] = (array) $v;
                                $ii++;
                            }
                            if (count($choices) == 1) {
                                $data['questions'][$pagecounter][$i]['value']
                                = $data['questions'][$pagecounter][$i]['choices'][0]['value'];
                            }
                            $i++;
                        }
                        if ($pagecounter > count($questionnaire['questions'])) {
                            break;
                        }
                    }
                    $pagecounter++;
                    $x = 0;
                    $questioncounter = 1;
                    foreach ($data['questions'] as $dataq) {
                        foreach ($dataq as $arr) {
                            $data['pagequestions'][$x] = $arr;
                            $x++;
                            if ($questioncounter >= count($questionnaire['questions'])) {
                                break;
                            }
                        }
                        $questioncounter++;
                    }
                }

                $data['prevpage'] = 0;
                $data['nextpage'] = 0;
                $pagebreaks = false;
            }
        } else {
            $data['emptypage'] = true;
            $data['emptypage_content'] = get_string('questionnaire:submit', 'questionnaire');
        }
        // Let each pagequestions know it's current required step, and fill up the final required step.
        // Logic states that we get all the required steps and give them an counter.
        // We get the final required count and check it againts the input once it's sent to a js file.
        // If its the final required count we display the button.
        $currentrequiredresponse = 0;
        $counter = 0;
        $multichoiceflag = false;
        $completedchoices = 0;
        $finalpagerequired = false;
        $completeddisabledflag = false;
        foreach ($data['pagequestions'] as &$pagequestion) {
            if ($pagequestion['info']['required'] == 'y') {
                if (!empty($pagequestion['choices']) && $pagequestion['info']['response_table'] == 'response_rank') {
                    foreach ($pagequestion['choices'] as &$choice) {
                        if (empty($choice['value'])) {
                            if ($currentrequiredresponse > 0 && empty($counter)) {
                                $counter = $currentrequiredresponse;
                            }
                            $counter++;
                            $choice['current_required_resp'] = $counter;
                        } else {
                            $completedchoices++;
                            $completeddisabledflag = true;
                        }
                    }
                    $currentrequiredresponse = $counter;
                } else {
                    $currentrequiredresponse++;
                    $pagequestion['info']['current_required_resp'] = $currentrequiredresponse;
                }
                if ($pagequestion['info']['qnum'] === count($data['pagequestions'])) {
                    $finalpagerequired = true;
                }
            }
        }

        $disablesavebutton = true;
        if ($completedchoices == $currentrequiredresponse && !$finalpagerequired) {
            $disablesavebutton = false;
        } else if ($completeddisabledflag) {
            $disablesavebutton = false;
        } else {
            $disablesavebutton = true;
        }

        // Let each pagequestions know what the final required field is.
        foreach ($data['pagequestions'] as &$pagequestion) {
            $pagequestion['info']['final_required_resp'] = $currentrequiredresponse - $completedchoices;
        }

        $mobileviewactivity = 'mod_questionnaire/mobile_view_activity_page';
        if ($branching) {
            $mobileviewactivity = 'mod_questionnaire/mobile_view_activity_branching_page';
        }

        $data['pagebreak'] = true;

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template($mobileviewactivity, $data)
                ],
            ],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/questionnaire/javascript/mobile_questionnaire.js'),
            'otherdata' => [
                'fields' => json_encode($questionnaire['fields']),
                'questionsinfo' => json_encode($questionnaire['questionsinfo']),
                'questions' => json_encode($questionnaire['questions']),
                'pagequestions' => json_encode($data['pagequestions']),
                'responses' => json_encode($questionnaire['responses']),
                'pagenum' => $pagenum,
                'nextpage' => $data['nextpage'],
                'prevpage' => $data['prevpage'],
                'completed' => $data['completed'],
                'intro' => $questionnaire['questionnaire']['intro'],
                'string_required' => get_string('required'),
                'string_dropdown' => get_string('selectdropdowntext', 'mod_questionnaire'),
                'disable_save' => $disablesavebutton,
            ],
            'files' => null
        ];
    }

    /**
     * Confirms the user is logged in and has the specified capability.
     *
     * @param \stdClass $cm
     * @param \context $context
     * @param string $cap
     */
    protected static function require_capability(\stdClass $cm, \context $context, string $cap) {
        require_login($cm->course, false, $cm, true, true);
        require_capability($cap, $context);
    }

    public static function mobile_view_activity_branching($args) {
        global $OUTPUT, $USER, $CFG, $DB, $SESSION;

        require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');
        require_once($CFG->dirroot . '/mod/questionnaire/lib.php');
        $args = (object) $args;
        $cmid = $args->cmid;
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;
        $prevpage = 0;
        if (!empty($SESSION->prevpage)) {
            $prevpage = $SESSION->prevpage;
            if (!$prevpage) {
                $SESSION->prevpage = $pagenum;
                if ($pagenum == 1) {
                    $prevpage = 0;
                } else {
                    $prevpage = $pagenum;
                }
            }
        }
        $quesitonnaireresponses = (!empty($args->responses)) ? $args->responses : [];
        $branching = (isset($args->branching) && !empty($args->branching)) ? intval($args->branching) : 0;
        // Capabilities check.
        $cm = get_coursemodule_from_id('questionnaire', $cmid);
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questionnaire:view');
        // Set some variables we are going to be using.
        $questionnaire = get_questionnaire_data($cmid, $USER->id);
        if (isset($questionnaire['questions'][$pagenum - 1]) && !empty($questionnaire['questions'][$pagenum - 1])) {
            $prevpage = $pagenum - 1;
        }

        $branching = check_mobile_branching_logic($questionnaire);
        $pagenum = get_mobile_questionnaire($questionnaire, $pagenum, $branching);
        $newpagenum = $pagenum['pagenum'];
        $newprevpagenum = $prevpage;
        $newnextpagenum = $pagenum['nextpage'];
        $pagenum = $newpagenum;

        $data = [
            'questionnaire' => $questionnaire,
            'cmid' => $cmid,
            'courseid' => intval($cm->course),
            'pagenum' => $pagenum,
            'userid' => $USER->id,
            'nextpage' => 0,
            'prevpage' => 0,
            'emptypage' => false
        ];
        $data['completed'] = (isset($questionnaire['response']['complete'])
            && $questionnaire['response']['complete'] == 'y') ? 1 : 0;
        $data['complete_userdate'] = (isset($questionnaire['response']['complete'])
            && $questionnaire['response']['complete'] == 'y') ?
        userdate($questionnaire['response']['submitted']) : '';
        if (isset($questionnaire['questions'][$pagenum])) {
            $i = 0;
            foreach ($questionnaire['questions'][$pagenum] as $questionid => $choices) {
                if (isset($questionnaire['questionsinfo'][$pagenum][$questionid])
                    && !empty($questionnaire['questionsinfo'][$pagenum][$questionid])) {
                    $data['questions'][$pagenum][$i]['info'] = $questionnaire['questionsinfo'][$pagenum][$questionid];
                    if ($data['questions'][$pagenum][$i]['info']['required'] == 'n') {
                        unset($data['questions'][$pagenum][$i]['info']['required']);
                    }
                    $ii = 0;
                    foreach ($choices as $k => $v) {
                        $data['questions'][$pagenum][$i]['choices'][$ii] = (array) $v;
                        $ii++;
                    }
                    if (count($choices) == 1) {
                        $data['questions'][$pagenum][$i]['value'] = $data['questions'][$pagenum][$i]['choices'][0]['value'];
                    }
                    $i++;
                }
            }
            if (isset($data['questions'][$pagenum]) && !empty($data['questions'][$pagenum])) {
                $i = 0;
                foreach ($data['questions'][$pagenum] as $arr) {
                    $data['pagequestions'][$i] = $arr;
                    $i++;
                }
            }
            if (isset($questionnaire['questions'][$pagenum + 1]) && !empty($questionnaire['questions'][$pagenum + 1])) {
                $data['nextpage'] = $pagenum + 1;
            }
            if ($prevpage) {
                $data['prevpage'] = $prevpage;
            }
        }
        // Let each pagequestions know it's current required step, and fill up the final required step.
        // Logic states that we get all the required steps and give them an counter.
        // We get the final required count and check it againts the input once it's sent to a js file.
        // If its the final required count we display the button.
        $currentrequiredresponse = 0;
        $counter = 0;
        $multichoiceflag = false;
        $completedchoices = 0;
        $finalpagerequired = false;
        $completeddisabledflag = false;
        foreach ($data['pagequestions'] as &$pagequestion) {
            if ($pagequestion['info']['required'] == 'y') {
                if (!empty($pagequestion['choices']) && $pagequestion['info']['response_table'] == 'response_rank') {
                    foreach ($pagequestion['choices'] as &$choice) {
                        if (empty($choice['value'])) {
                            if ($currentrequiredresponse > 0 && empty($counter)) {
                                $counter = $currentrequiredresponse;
                            }
                            $counter++;
                            $choice['current_required_resp'] = $counter;
                        } else {
                            $completedchoices++;
                            $completeddisabledflag = true;
                        }
                    }
                    $currentrequiredresponse = $counter;
                } else {
                    $currentrequiredresponse++;
                    $pagequestion['info']['current_required_resp'] = $currentrequiredresponse;
                }
                if ($pagequestion['info']['qnum'] === count($data['pagequestions'])) {
                    $finalpagerequired = true;
                }
            }
        }
        $disablesavebutton = true;
        if ($completedchoices == $currentrequiredresponse && !$finalpagerequired) {
            $disablesavebutton = false;
        } else if ($completeddisabledflag) {
            $disablesavebutton = false;
        } else {
            $disablesavebutton = true;
        }

        // Let each pagequestions know what the final required field is.
        foreach ($data['pagequestions'] as &$pagequestion) {
            $pagequestion['info']['final_required_resp'] = $currentrequiredresponse;
        }

        $data['pagebreak'] = true; // Branching logic is always true.

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_questionnaire/mobile_view_activity_branching_page', $data)
                ],
            ],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/questionnaire/javascript/mobile_questionnaire.js'),
            'otherdata' => [
                'fields' => json_encode($questionnaire['fields']),
                'questionsinfo' => json_encode($questionnaire['questionsinfo']),
                'questions' => json_encode($questionnaire['questions']),
                'pagequestions' => json_encode($data['pagequestions']),
                'responses' => json_encode($questionnaire['responses']),
                'pagenum' => $pagenum,
                'nextpage' => $newnextpagenum,
                'prevpage' => $newprevpagenum,
                'completed' => $data['completed'],
                'intro' => $questionnaire['questionnaire']['intro'],
                'string_required' => get_string('required'),
                'string_dropdown' => get_string('selectdropdowntext', 'mod_questionnaire'),
                'disable_save' => $disablesavebutton,
            ],
            'files' => null
        ];
    }
}