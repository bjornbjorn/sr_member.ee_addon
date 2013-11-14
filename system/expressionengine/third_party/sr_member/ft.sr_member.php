<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'fieldtypes/select/ft.select.php';

/**
 * Fieldtype to select projects
 */
class Sr_member_ft extends Select_ft {

    var $info = array(
        'name'		=> 'SR Member',
        'version'	=> '1.0.0'
    );

    var $has_array_data = TRUE;

    function display_field($data)
    {
        $members = $this->EE->db->select('member_id, screen_name')->order_by('screen_name')->from('members')->get();
        $dropdown_options = array();
        foreach($members->result() as $member) {
            $dropdown_options[ $member->member_id ] = $member->screen_name;
        }
        return form_dropdown($this->field_name, $dropdown_options, $data);
    }


    public function display_settings($data) {
        return array();
    }

    public function validate($member_id)
    {
        $valid = false;

        // empty selection OK
        if ($member_id == '') {
            return TRUE;
        }

        /**
         * Verify that member selected exists
         */
        $q = $this->EE->db->get_where('members', array('member_id' => $member_id));
        if($q->num_rows() == 1) {
            $valid = TRUE;
        }

       if(!$valid) {
            return $this->EE->lang->line('invalid_selection');
        }
    }


    /**
     * Called after field is saved
     *
     * @access	public
     * @param	string
     */
    function post_save($data)
    {
        return array();
    }

}