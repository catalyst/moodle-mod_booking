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

namespace mod_booking\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

use context;
use context_system;
use core_form\dynamic_form;
use html_writer;
use mod_booking\optiondates_handler;
use mod_booking\pricecategories;
use mod_booking\singleton_service;
use moodle_url;
use stdClass;

/**
 * Add holidays form.
 *
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamicchangepricacategoriesform extends dynamic_form {

    private $cmid = null;

    /**
     * Get context for dynamic submission.
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Check access for dynamic submission.
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', context_system::instance());
    }


    /**
     * Set data for dynamic submission.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {

        $cmid = optional_param('id', 0, PARAM_INT);
        $choosepricacategories = null;

        if ($cmid && $cmid != 0) {
            $this->cmid = $cmid;

            $booking = singleton_service::get_instance_of_booking_by_cmid($cmid);

        } else if ($this->cmid && $this->cmid != 0) {
            $cmid = $this->cmid;
        } else if (isset($this->_ajaxformdata['cmid'])) {
            $cmid = $this->_ajaxformdata['cmid'];
            $choosepricacategories = isset($this->_ajaxformdata['choosepricacategories']) ? $this->_ajaxformdata['choosepricacategories'] : null;
            $this->cmid = $cmid;
        }

        $data = new stdClass();

        if ($cmid) {
            $data->cmid = $cmid;
            $data->choosespricacategories = $choosepricacategoriesr;
        }

        $this->set_data($data);
    }

    /**
     * Process dynamic submission.
     * @return stdClass|null
     */
    public function process_dynamic_submission(): stdClass {

        $data = $this->get_data();

        optiondates_handler::change_pricacategories($data->cmid, $data->choosepricacategories);

        return $data;
    }

    /**
     * Form definition.
     * @return void
     */
    public function definition(): void {
        global $DB;

        $mform = $this->_form;

        $changepricacategorieslabel = html_writer::tag('b', get_string('changepricacategories', 'mod_booking'),
            array('class' => 'changepricacategorieslabel'));

        $mform->addElement('static', 'changepricacategorieslabel', $changepricacategorieslabel);

        $mform->addElement('hidden', 'cmid', 0);
        $mform->settype('cmid', PARAM_INT);

        $selectarray = semester::get_pricacategories_id_name_array();

        $mform->addElement('select', 'choosepricacategories', get_string('choosepricacategories', 'mod_booking'), $selectarray);

        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Server-side form validation.
     * @param array $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files): array {
        $errors = [];

        return $errors;
    }

    /**
     * Get page URL for dynamic submission.
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/booking/pricecategories.php', ['id' => $this->cmid]);
    }
}
