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
        $pricecategoriesarray = [];

        if (
        !empty($pricecategoriesdata->pricecategoryidentifier) && is_array($pricecategoriesdata->pricecategoryidentifier)
        && !empty($pricecategoriesdata->pricecategoriesname) && is_array($pricecategoriesdata->name)) {

            foreach ($pricecategoriesdata->pricecategoryidentifier as $idx => $pricecategoryidentifier) {

                $pricecategory = new stdClass;
                $pricecategory->identifier = trim($pricecategoryidentifier);
                $pricecategory->pricecategoriesname = trim($pricecategoriesdata->pricecategoriesname[$idx]);
                $pricecategory->pricedefaultvalue = $existingpricecategory->defaultvalue;
                $pricecategory->pricedisabled = $existingpricecategory->disabled;

                if (!empty($pricecategory->identifier)) {
                    $pricecategoriesarray[] = $pricecategory;
                } else {
                    throw new moodle_exception('ERROR: Price Category identifier must not be empty.');
                }
            }
        }
        return $pricecategoriesarray;
    }

    /**
     * Set data for dynamic submission.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $data = new stdClass;

        if ($existingpricecategories = $DB->get_records_sql("SELECT * FROM {booking_pricecategories} ORDER BY ordernum ASC")) {
            $data->prices = count($existingpricecategories);
            foreach ($existingpricecategories as $existingpricecategory) {
                $data->pricecategoryid[] = $existingpricecategory->id;
                $data->pricecategoryordernum[] = $existingpricecategory->ordernum;
                $data->pricecategoryidentifier[] = trim($existingpricecategory->identifier);
                $data->pricecategoryname[] = trim($existingpricecategory->name);
                $data->pricedefaultvalue[] = $existingpricecategory->defaultvalue;
                $data->disablepricecategory[] = $existingpricecategory->disabled;
            }
        } else {
            // No prices found in DB.
            $data->prices = 0;
            $data->ordernum = [];
            $data->pricecategoryidentifier = [];
            $data->name = [];
            $data->pricedefaultvalue = [];
            $data->disablepricecategory = [];
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
        $pricecategoriesdata = $this->get_data();
        $existingpricecategories = $DB->get_records('booking_pricecategories');

        if (empty($existingpricecategories)) {
            // There are no price categories yet.
            // Currently there can be up to nine price categories.
            for ($i = 1; $i <= MAX_PRICE_CATEGORIES; $i++) {
                $pricecategoryordernumx = 'pricecategoryordernum' . $i;
                $pricecategoryidentifierx = 'pricecategoryidentifier' . $i;
                $pricecategorynamex = 'pricecategoryname' . $i;
                $defaultvaluex = 'defaultvalue' . $i;
                $disablepricecategoryx = 'disablepricecategory' . $i;

                // Only add price categories if a name was entered.
                if (!empty($pricecategoriesdata->{$pricecategoryidentifierx})) {
                    $pricecategory = new stdClass();
                    $pricecategory->ordernum = $pricecategoriesdata->{$pricecategoryordernumx};
                    $pricecategory->identifier = $pricecategoriesdata->{$pricecategoryidentifierx};
                    $pricecategory->name = $pricecategoriesdata->{$pricecategorynamex};
                    $pricecategory->defaultvalue = $pricecategoriesdata->{$defaultvaluex};
                    $pricecategory->disabled = $pricecategoriesdata->{$disablepricecategoryx};

                    $DB->insert_record('booking_pricecategories', $pricecategory);
                }
            }
        } else {
            // There are already existing price categories.
            // So we need to check for changes.
            if ($pricecategorychanges = $this->pricecategories_get_changes($existingpricecategories, $pricecategoriesdata)) {
                foreach ($pricecategorychanges['updates'] as $record) {
                    $DB->update_record('booking_pricecategories', $record);
                }
                if (count($pricecategorychanges['inserts']) > 0) {
                    $DB->insert_records('booking_pricecategories', $pricecategorychanges['inserts']);
                }
            }
        }

        return $pricecategoriesdata;
    }

    /**
     * Form definition.
     * @return void
     */
    public function definition(): void {
        global $DB;

        $mform = $this->_form;

        // Default price always needs to be there, ordernum has to be 1 and identifier has to be 'default'.
        $defaultprice = $DB->get_record_sql("SELECT * FROM {booking_pricecategories} WHERE identifier = 'default'");
        if (empty($defaultprice)) {
            $defaultprice = new stdClass;
            $defaultprice->ordernum = 1;
            $defaultprice->identifier = 'default';
            $defaultprice->name = get_string('price', 'booking');
            $defaultprice->defaultvalue = 0;
            $defaultprice->disabled = 0; // Default price cannot be disabled.

            $defaultprice->id = $DB->insert_record('booking_pricecategories', $defaultprice);
        }

        // Repeated elements.
        $repeatedprices = [];

        // Options to store help button texts etc.
        $repeateloptions = [];

        $pricelabel = html_writer::tag(
        'b',
        get_string('pricecategories', 'booking') . ' {no}',
        array('class' => 'pricelabel')
        );
        $repeatedprices[] = $mform->createElement('static', 'pricelabel', $pricelabel);

        $repeatedprices[] = $mform->createElement('hidden', 'pricecategoryid');
        $mform->setType('pricecategoryid', PARAM_TEXT);

        $repeatedprices[] = $mform->createElement('hidden', 'pricecategoryordernum');
        $mform->setType('pricecategoryordernum', PARAM_TEXT);

        $repeatedprices[] = $mform->createElement('text', 'pricecategoryidentifier', get_string('pricecategoryidentifier', 'booking'));
        $mform->setType('pricecategoryidentifier', PARAM_TEXT);

        $repeatedprices[] = $mform->createElement('text', 'pricecategoryname', get_string('pricecategoryname', 'booking'));
        $mform->setType('pricecategoryname', PARAM_TEXT);

        $repeatedprices[] = $mform->createElement('text', 'pricedefaultvalue',
        get_string('defaultvalue', 'booking'));
        $mform->setType('pricedefaultvalue', PARAM_INT);

        // Checkbox.
        $repeatedprices[] = $mform->createElement('advcheckbox', 'disablepricecategory',
        get_string('disablepricecategory', 'booking'), null, null, [0, 1]);
        $mform->setType('disablepricecategory', PARAM_INT);
        $mform->setDefault('disablepricecategory', 0);

        $numberofpricestoshow = 1;
        if ($existingprices = $DB->get_records('booking_pricecategories')) {
            $numberofpricestoshow = count($existingprices);
        }

        $repeateloptions['pricecategoryidentifier']['disabledif'] = array('disablepricecategory', 'eq', 1);
        $repeateloptions['pricecategoryname']['disabledif'] = array('disablepricecategory', 'eq', 1);
        $repeateloptions['pricedefaultvalue']['disabledif'] = array('disablepricecategory', 'eq', 1);
        $repeateloptions['disablepricecategory']['hideif'] = array('pricecategoryidentifier', 'eq', 'default');

        $this->repeat_elements(
        $repeatedprices,
        $numberofpricestoshow,
        $repeateloptions,
        'prices',
        'addprice',
        1,
        get_string('addprice', 'mod_booking'),
        true
        );

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
        return new moodle_url('/mod/booking/pricecategories.php');
    }
    /**
     * Helper function to return arrays containing all relevant pricecategories update changes.
     * The returned arrays will have the prepared stdClasses for update and insert in booking_pricecategories table.
     *
     * @param $oldpricecategories the existing price categories
     * @param $data the form data
     * @return array
     */

    private function pricecategories_get_changes($oldpricecategories, $formdata) {

        $updates = [];
        $inserts = [];

        foreach ($formdata->pricecategoryid as $key => $value) {

            // Counter.
            $counter = (int)substr($key, -1);

            if ($value !== "" || isset($oldpricecategories[$value])) {
                // Deal with stuff which is already in the database.
                $data = $oldpricecategories[$value];

                // If identifier(comes from the database) is not equal to the identifier(comes from  the form).
                if ($data->identifier !== $formdata->pricecategoryidentifier[$key]
                    || $data->name !== $formdata->pricecategoryname[$key]
                    || $data->defaultvalue !== $formdata->pricedefaultvalue[$key]
                ) {
                    $data->id = $value;
                    $data->ordernum = $formdata->pricecategoryordernum[$key];
                    $data->identifier = $formdata->pricecategoryidentifier[$key];
                    $data->name = $formdata->pricecategoryname[$key];
                    $data->defaultvalue = $formdata->pricedefaultvalue[$key];
                    $data->disabled = $formdata->disablepricecategory[$key];

                    $updates[] = $data;

                }

                if ($data->identifier !== $formdata->pricecategoryidentifier[$key]) {
                    $data->identifier == $formdata->pricecategoryidentifier[$key];
                }

            } else {
                // Deal with stuff which is not yet in the database.

                $data = new stdClass();
                $data->ordernum = $formdata->pricecategoryordernum[$key] . $counter;
                $data->identifier = $formdata->pricecategoryidentifier[$key];
                $data->name = $formdata->pricecategoryname[$key];
                $data->defaultvalue = $formdata->pricedefaultvalue[$key];
                $data->disabled = $formdata->disablepricecategory[$key];

                $inserts[] = $data;
            }

        }
        return [
            'inserts' => $inserts,
            'updates' => $updates
            ];
    }
}
