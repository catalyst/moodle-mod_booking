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
require_once($CFG->libdir . '/adminlib.php');

use coding_exception;
use context;
use context_system;
use core_form\dynamic_form;
use html_writer;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Add semesters form.
 *
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamicpricecategoriesform extends dynamic_form {

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
     * Transform semester data from to an array of semester classes.
     * @param stdClass $pricecategoriesdata data from
     * @return array
     */
    protected function transform_data_to_pricecategories_array(stdClass $pricecategoriesdata): array {
        $pricecategpriesarray = [];

        if (!empty($pricecategoriesdata->pricecategoryidentifier) && is_array($pricecategoriesdata->pricecategoryidentifier)
            && !empty($pricecategoriesdata->pricecategoriesname) && is_array($pricecategoriesdata->name)
            && !empty($pricecategoriesdata->pricestart) && is_array($pricecategoriesdata->pricestart)
            && !empty($pricecategoriesdata->priceend) && is_array($pricecategoriesdata->priceend)) {

            foreach ($pricedata->pricecategoryidentifier as $idx => $pricecategoryidentifier) {

                $price = new stdClass;
                $price->identifier = trim($pricecategoryidentifier);
                $price->pricecategoriesname = trim($pricecategoriesdata->pricecategoriesname[$idx]);
                $price->startdate = $pricedata->pricestart[$idx];
                $price->enddate = $pricedata->priceend[$idx];

                if (!empty($price->identifier)) {
                    $pricesarray[] = $price;
                } else {
                    throw new moodle_exception('ERROR: Semester identifier must not be empty.');
                }
            }
        }
        return $pricesarray;
    }

    /**
     * Set data for dynamic submission.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $data = new stdClass;

        if ($existingpricecategories = $DB->get_records_sql("SELECT * FROM {booking_pricecategories}")) {
            $data->prices = count($existingpricecategories);
            foreach ($existingpricecategories as $existingpricecategory) {
                $data->pricecategoryidentifier[] = trim($existingpricecategory->identifier);
                $data->name[] = trim($existingpricecategory->name);
                $data->pricedefaultvalue[] = $existingpricecategory->defaultvalue;
                $data->pricedisabled[] = $existingpricecategory->disabled;
            }

        } else {
            // No prices found in DB.
            $data->prices = 0;
            $data->pricecategoryidentifier = [];
            $data->name = [];
            $data->pricedefaultvalue = [];
            $data->pricedisabled = [];
        }

        $this->set_data($data);
    }

    /**
     * Process dynamic submission.
     * @return stdClass|null
     */
    public function process_dynamic_submission(): stdClass {
        global $DB;

        // This is the correct place to save and update semesters.
        $pricedata = $this->get_data();
        $pricesarray = $this->transform_data_to_price_array($pricedata);

        $existingidentifiers = [];
        $currentidentifiers = $pricedata->pricecategoryidentifier;

        if ($existingpricecategories = $DB->get_records('booking_semesters')) {
            foreach ($existingpricecategories as $existingpricecategory) {
                $existingidentifiers[] = trim($existingpricecategory->identifier);
            }
        }

        foreach ($pricesarray as $price) {

            $price->identifier = trim($price->identifier);
            $price->name = trim($price->name);

            // If it's a new identifier: insert.
            if (!in_array($price->identifier, $existingidentifiers)) {
                $DB->insert_record('booking_pricecategory', $price);
            } else {
                // If it's an existing identifier: update.
                $existingrecord = $DB->get_record('booking_pricecategory', ['identifier' => $price->identifier]);
                $price->id = $existingrecord->id;
                $DB->update_record('booking_pricecategories', $price);
            }
        }

        // Delete all semesters from DB which are not part of the form anymore.
        foreach ($existingidentifiers as $existingidentifier) {
            if (!in_array($existingidentifier, $currentidentifiers)) {
                $DB->delete_records('booking_pricecategories', ['identifier' => $existingidentifier]);
            }
        }

        return $this->get_data();
    }

    /**
     * Form definition.
     * @return void
     */
    public function definition(): void {
        global $DB;

        $mform = $this->_form;

        // Repeated elements.
        $repeatedprices = [];

        // Options to store help button texts etc.
        $repeateloptions = [];

        $pricelabel = html_writer::tag('b', get_string('pricecategories', 'booking') . ' {no}',
            array('class' => 'pricelabel'));
        $repeatedprices[] = $mform->createElement('static', 'pricelabel', $pricelabel);

        $repeatedprices[] = $mform->createElement('text', 'pricecategoryidentifier', get_string('pricecategoryidentifier', 'booking'));
        $mform->setType('pricecategoryidentifier', PARAM_TEXT);
        $repeateloptions['pricecategoryidentifier']['helpbutton'] = ['pricecategoryidentifier', 'mod_booking'];

        $repeatedprices[] = $mform->createElement('text', 'pricecategoryname', get_string('pricecategoryname', 'booking'));
        $mform->setType('pricecategoryname', PARAM_TEXT);
        $repeateloptions['pricecategoryname']['helpbutton'] = ['pricecategoryname', 'mod_booking'];

        $repeatedprices[] = $mform->createElement('text', 'pricedefaultvalue', get_string('defaultvalue', 'booking'));
        // $repeateloptions['pricedefaultvalue']['helpbutton'] = ['defaultvalue', 'mod_booking'];

        $repeatedprices[] = $mform->createElement('text', 'disablepricecategory', get_string('disablepricecategory', 'booking'));
        $repeateloptions['disablepricecategory']['helpbutton'] = ['disablepricecategory', 'mod_booking'];

        $repeatedprices[] = $mform->createElement('submit', 'deleteprice', get_string('deletepricebtn', 'mod_booking'));

        $numberofpricestoshow = 1;
        if ($existingprices = $DB->get_records('booking_pricecategories')) {
            $numberofpricestoshow = count($existingprices);
        }

        $this->repeat_elements($repeatedprices, $numberofpricestoshow,
            $repeateloptions, 'prices', 'addprice', 1, get_string('addprice', 'mod_booking'), true, 'deleteprice');

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

        $data['pricecategoryidentifier'] = array_map('trim', $data['pricecategoryidentifier']);
        $data['name'] = array_map('trim', $data['name']);

        $pricecategoryidentifiercounts = array_count_values($data['pricecategoryidentifier']);
        $namecounts = array_count_values($data['name']);

        foreach ($data['pricecategoryidentifier'] as $idx => $pricecategoryidentifier) {
            if (empty($pricecategoryidentifier)) {
                $errors["pricecategoryidentifier[$idx]"] = get_string('erroremptypricecategoryidentifier', 'booking');
            }
            if ($pricecategoryidentifiercounts[$pricecategoryidentifier] > 1) {
                $errors["pricecategoryidentifier[$idx]"] = get_string('errorduplicatepricecategoryidentifier', 'booking');
            }
        }

        foreach ($data['name'] as $idx => $name) {
            if (empty($name)) {
                $errors["name[$idx]"] = get_string('erroremptypricecategoryname', 'booking');
            }
            if ($namecounts[$name] > 1) {
                $errors["name[$idx]"] = get_string('errorduplicatepricecategoryname', 'booking');
            }
        }

        /*foreach ($data['defaultvalue'] as $idx => $defaultvalue) {
            if (empty($defaultvalue)) {
                $errors["defaultvalue[$idx]"] = get_string('erroremptydefaultvalue', 'booking');
            }
        }*/

        return $errors;
    }

    /**
     * Get page URL for dynamic submission.
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/booking/semesters.php');
    }
}
