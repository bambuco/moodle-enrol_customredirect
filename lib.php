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
 * Custom redirect access plugin.
 *
 * This plugin does not add any entries into the user_enrolments table,
 * the access control is granted on the fly via the tricks in require_login().
 *
 * @package    enrol_customredirect
 * @copyright  2020 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class enrol_customredirect_plugin
 *
 * @copyright  2020 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_customredirect_plugin extends enrol_plugin {

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        return array();
    }

    /**
     * Enrol a user using a given enrolment instance.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param null $roleid
     * @param int $timestart
     * @param int $timeend
     * @param null $status
     * @param null $recovergrades
     */
    public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
        // no real enrolments here!
        return;
    }

    /**
     * Enrol a user from a given enrolment instance.
     *
     * @param stdClass $instance
     * @param int $userid
     */
    public function unenrol_user(stdClass $instance, $userid) {
        // nothing to do, we never enrol here!
        return;
    }

    /**
     * Attempt to automatically gain temporary customredirect access to course,
     * calling code has to make sure the plugin and instance are active.
     *
     * @param stdClass $instance course enrol instance
     * @return bool|int false means no customredirect access, integer means end of cached time
     */
    public function try_customredirectaccess(stdClass $instance) {
        global $USER, $CFG;

        return false;
    }

    /**
     * Returns true if the current user can add a new instance of enrolment plugin in course.
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        global $DB;

        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) ||
                !has_capability('enrol/customredirect:config', $context)) {
            return false;
        }

        if ($DB->record_exists('enrol', array('courseid' => $courseid, 'enrol' => 'customredirect'))) {
            return false;
        }

        return true;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $OUTPUT, $SESSION, $USER;

        $uri = $instance->customchar3;

        // Go to the custom uri.
        if (!empty($uri)) {
            $uri = str_replace('{courseid}', $instance->courseid, $uri);
            redirect($uri);
        }

        return null;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param object $course
     * @return int id of new instance
     */
    public function add_default_instance($course) {
        $fields = array('customchar3' => $this->get_config('defaulturi'));

        return $this->add_instance($course, $fields);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;

        if (!$DB->record_exists('enrol', array('courseid' => $data->courseid, 'enrol' => $this->get_name()))) {
            $this->add_instance($course, (array)$data);
        }

        // No need to set mapping, we do not restore users or roles here.
        $step->set_mapping('enrol', $oldid, 0);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/customredirect:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        if (!has_capability('enrol/customredirect:config', $context)) {
            return false;
        }

        return true;
    }

    /**
     * Get default settings for enrol_customredirect.
     *
     * @return array
     */
    public function get_instance_defaults() {
        $fields = array();
        $fields['defaulturi'] = $this->get_config('defaulturi');
        return $fields;
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     */
    public function get_enrol_info(stdClass $instance) {

        $instanceinfo = new stdClass();
        $instanceinfo->id = $instance->id;
        $instanceinfo->courseid = $instance->courseid;
        $instanceinfo->type = $this->get_name();
        $instanceinfo->name = $this->get_instance_name($instance);
        $instanceinfo->status = $instance->status == ENROL_INSTANCE_ENABLED;

        // Specifics enrolment method parameters.
        $instanceinfo->requiredparam = new stdClass();

        return $instanceinfo;
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $CFG;

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_customredirect'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_customredirect');
        $mform->setDefault('status', $this->get_config('status'));
        $mform->setAdvanced('status', $this->get_config('status_adv'));

        $mform->addElement('text', 'customchar3', get_string('uri', 'enrol_customredirect'));
        $mform->setType('customchar3', PARAM_TEXT);
        $mform->setDefault('customchar3', $this->get_config('defaulturi'));
        $mform->addHelpButton('customchar3', 'uri', 'enrol_customredirect');

    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();
        return $errors;
    }

}

/**
 * Get icon mapping for font-awesome.
 */
function enrol_customredirect_get_fontawesome_icon_map() {
    return [
        'enrol_customredirect:withpassword' => 'fa-key',
        'enrol_customredirect:withoutpassword' => 'fa-unlock-alt',
    ];
}
