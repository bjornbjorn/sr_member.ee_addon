<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library for working with the ExpressionEngine Extension settings
 */

if(!class_exists('Settingslib')) {

class Settingslib {


    public function __construct() { $this->EE = get_instance(); }


    /**
     * Add a text field to the settings array
     *
     * @param $settings the settings array to add to
     * @param $key the key, remember to add a the label for this key in the language file
     * @return array
     */
    public function addTextfield(&$settings, $key) {
        $settings[$key] =  array('i', '', '');
        return $settings;
    }

    /**
     * Add a radio button Yes/No
     *
     * @param $settings
     * @param $key
     * @param $default_value
     * @return mixed
     */
    public function addRadioButtons(&$settings, $key, $default_value = 'y')
    {
        $settings[$key] = array('r', array('y' => "Yes", 'n' => "No"), $default_value);
        return $settings;
    }

    /**
     * Add a dropdown
     *
     * @param $key
     * @param $values ie. array('fr' => 'France', 'de' => 'Germany', 'us' => 'United States')
     * @param bool $default_value
     */
    public function addDropdown(&$settings, $key, $values, $default_value=FALSE) {
        $settings[$key] = array('s', $values, $default_value);
    }

    /**
     *
     * @param $settings
     * @param $key
     * @param $values Should be an array like this array('l' => "Lowfat", 's' => "Salty")
     * @param string $default_values optional, defaults to NONE selected. Default selected values would be e.g. array('1', 's')
     */
    public function addCheckboxes(&$settings, $key, $values, $default_values = FALSE)
    {
        if(!$default_values) {
            $default_values = array();
            foreach($values as $vkey => $vvalue) {
                $default_values[$vkey] = '';
            }
        }

        $settings[$key]    = array('c', $values, $default_values);
    }

    /**
     * Add checkboxes for selecting Member Groups
     *
     * @param $settings the settings array to add to
     * @param $key the key, remember to add a the label for this key in the language file
     * @param bool $min_member_group_id minimum id of the member group to show (for "Members" and up, use 5)
     * @return array
     */
    public function addMembergroupCheckboxes(&$settings, $key, $min_member_group_id=FALSE)
    {
        if($min_member_group_id) {
            $this->EE->db->where('group_id>=', $min_member_group_id, FALSE);
        }
        $member_groups = $this->EE->db->get('member_groups');
        $mg_select = array();
        foreach($member_groups->result() as $group)
        {
            $mg_select[$group->group_id] = $group->group_title;
        }

        $settings[$key] = array('c', $mg_select, '');
        return $settings;
    }

    /**
     * Add checkboxes for selecting Channels
     *
     * @param $settings the settings array to add to
     * @param $key the key, remember to add a the label for this key in the language file
     * @return array
     */
    public function addChannelsCheckboxes(&$settings, $key)
    {
        $channels = $this->EE->db->get_where('channels', array('site_id' => $this->EE->config->item('site_id')));
        $channel_select = array();
        foreach($channels->result() as $channel)
        {
            $channel_select[$channel->channel_id] = $channel->channel_name;
        }

        $settings[$key] = array('c', $channel_select, '');
        return $settings;
    }



    /**
     * Get extension settings for an addon by name
     *
     * @param $module_name
     * @return array
     */
    public function getSettings($module_name)
    {
        $arr = array();
        $q = $this->EE->db->get_where('extensions', array('class' => ucfirst($module_name).'_ext'));
        if($q->num_rows() > 0) {
            $arr = unserialize($q->row('settings'));    // just grab the first one, EE saves the same settings array for each hook
        }
        return $arr;
    }
}
}