<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'sr_member/libraries/wda/base/wda_extension.php';

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * SR Member Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Bjørn Børresen
 * @link		http://wedoaddons.com
 */

class Sr_member_ext extends WDA_Extension {

    public $name			= 'SR Member';
	public $description		= 'Link a member to a channel entry';
	public $docs_url		= 'http://wedoaddons.com';
	public $version			= '1.0';
    public $settings_exist	= 'y';

    private $tag_prefix     = 'sr_member:';

    /**
     * The hooks to listen to. The hook call will be linked to the method on_<hook-name>, so. e.g.
     * adding hook "member_register_start" in the $active_hooks array will call "on_member_register_start" method
     * in this extension file.
     */
    protected $active_hooks = array(
        'simple_registration_success',
        'cp_members_member_delete_end',
        'sessions_end',
    );

    protected $extension_class_name = __CLASS__;

    public function settings() {
        $settings = array();
        $this->EE->load->add_package_path(PATH_THIRD.'sr_member/');
        $this->EE->load->library('settingslib');
        $this->EE->settingslib->addChannelsCheckboxes($settings, 'sr_member_channels_populate_on_member_register');
        $this->EE->settingslib->addDropdown($settings, 'sr_member_entry_author', array('admin' => 'Admin', 'member' => 'Member'));
        $this->EE->settingslib->addCheckboxes($settings, 'sr_member_delete_entry_on_member_delete', array('y' => 'Yes'));

        return $settings;
    }

    public function on_simple_registration_success($member_id, $member_data, $ref)
    {
        $channel_ids = $this->settings['sr_member_channels_populate_on_member_register'];

        if(count($channel_ids) > 0) {
            $this->EE->load->library('api');
            $this->EE->api->instantiate('channel_entries');
            $this->EE->api->instantiate('channel_fields');
            $this->EE->api_channel_fields->fetch_custom_channel_fields();

            $logged_in_member_id = $this->EE->session->userdata('member_id');
            $author_entry_id = $member_id;
            if($this->settings['sr_member_entry_author'] == 'admin') {
                $author_entry_id = 1;
            }
            $this->EE->session->create_new_session($author_entry_id);
            $this->EE->session->fetch_session_data();
            $this->EE->session->fetch_member_data();

            foreach($channel_ids as $channel_id) {

                $this->EE->db->from('channel_fields f');
                $this->EE->db->join('channels c', 'c.field_group = f.group_id');
                $this->EE->db->where('c.channel_id', $channel_id);
                $this->EE->db->where('c.site_id', $this->EE->config->item('site_id'));
                $q = $this->EE->db->get();
                $data = array(
                    'title' => $member_data['screen_name']
                );
                foreach($q->result() as $field) {

                    $field_value = $this->EE->input->post($field->field_name);
                    if($field->field_type == 'sr_member') {
                        $field_value = $member_id;  // should always be set to member id
                    }

                    $data['field_id_'.$field->field_id] = $field_value;
                    $data['field_ft_'.$field->field_id] = 'none';
                }

            }

            $success = $this->EE->api_channel_entries->save_entry($data, $channel_id);

            if($logged_in_member_id != $author_entry_id) {
                $this->EE->session->create_new_session($logged_in_member_id);
                $this->EE->session->fetch_session_data();
                $this->EE->session->fetch_member_data();
            }

            if(!$success) {
                show_error("Error - could not add information on member registration: ".print_r($this->EE->api_channel_entries->errors, true));
            }
        }
    }

    /**
     * Map member variables
     *
     * $this->EE->config->_global_vars = array_merge($early, $this->EE->config->_global_vars);
     * @param $ref
     */
    public function on_sessions_end($sess)
    {
        if (REQ != 'PAGE') return;  // exit if not a page request

        if($sess->userdata['member_id'] && $this->settings['sr_member_channels_populate_on_member_register']) {
            $member_id = $sess->userdata['member_id'];

            $sr_member_fields = $this->EE->db->get_where('channel_fields', array('site_id' => $this->EE->config->item('site_id'), 'field_type' => 'sr_member'));
            if($sr_member_fields->num_rows() > 0) {


                $this->EE->load->model('file_upload_preferences_model');
                $upload_prefs = $this->EE->file_upload_preferences_model->get_file_upload_preferences(NULL, NULL, TRUE);

                /**
                 * Get fields
                 */
                $fields = $this->EE->db->from('channels c, field_groups g, channel_fields f')
                            ->where('g.group_id', 'f.group_id', FALSE)
                            ->where('c.field_group', 'g.group_id', FALSE)
                            ->where_in('c.channel_id', $this->settings['sr_member_channels_populate_on_member_register'])
                            ->get();

                $fields_arr = array();
                $field_types_arr = array();
                foreach($fields->result() as $field ) {
                    $fields_arr[$field->field_id] = $field->field_name;
                    $field_types_arr[$field->field_id] = $field->field_type;
                }

                $member_tags_arr = array();
                $sr_member_entry_ids = array();

                foreach($sr_member_fields->result() as $sr_member_field) {
                    $member_info = $this->EE->db->from('channel_data d, channels c, field_groups g')
                                    ->where('c.site_id', $this->EE->config->item('site_id'))
                                    ->where('d.channel_id', 'c.channel_id', FALSE)
                                    ->where('c.field_group', 'g.group_id', FALSE)
                                    ->where('d.field_id_'.$sr_member_field->field_id, $member_id)
                                    ->get();

                    if($member_info->num_rows() > 0) {
                        $sr_member_entry_ids[] = $member_info->row('entry_id');
                        foreach($fields_arr as $field_id => $field_name) {
                            $field_value = $member_info->row('field_id_'.$field_id);
                            $member_tags_arr[$this->tag_prefix.$field_name] = $field_value;

                            // Assets - @todo add support for regular file type
                            if($field_types_arr[$field_id] == 'assets') {

                                $as = $this->EE->db->from('assets_selections a, assets_files f, assets_folders fo')
                                        ->where('a.file_id', 'f.file_id', FALSE)
                                        ->where('f.folder_id', 'fo.folder_id', FALSE)
                                        ->where('a.entry_id', $member_info->row('entry_id'))
                                        ->where('a.field_id', $field_id)
                                        ->get();

                                if($as->num_rows() > 0) {
                                    $server_url = $upload_prefs[$as->row('filedir_id')]['url'] . $as->row('full_path');
                                    $server_path = $upload_prefs[$as->row('filedir_id')]['server_path'] . $as->row('full_path');
                                    $file_name = $as->row('file_name');

                                    $member_tags_arr[$this->tag_prefix.$field_name] = $server_url.$file_name;
                                    $member_tags_arr[$this->tag_prefix.$field_name.':server_path'] = $server_path.$file_name;
                                    $member_tags_arr[$this->tag_prefix.$field_name.':file_name'] = $file_name;
                                } else {
                                    $member_tags_arr[$this->tag_prefix.$field_name] = '';
                                    $member_tags_arr[$this->tag_prefix.$field_name.':server_path'] = '';
                                    $member_tags_arr[$this->tag_prefix.$field_name.':file_name'] = '';
                                }
                            }
                        }
                    }
                }

                $member_tags_arr[$this->tag_prefix.'entry_id'] = implode('|', $sr_member_entry_ids);

                $this->EE->config->_global_vars = array_merge($member_tags_arr, $this->EE->config->_global_vars);
            }
        }
    }

    /**
     * Called when members are deleted from the CP.
     *
     */
    public function on_cp_members_member_delete_end($member_ids)
    {
        if($this->settings['sr_member_delete_entry_on_member_delete'] == 'y') {

            /**
             * Find all SR_member fields
             */
            $q = $this->EE->db->from('channel_fields')->get_where(array(
                    'site_id' => $this->EE->config->item('site_id'),
                    'field_type' => 'sr_member')
            );
            $sr_field_ids = array();
            foreach($q->result() as $row) {
                $sr_field_ids = $row->field_id;
            }

            foreach($member_ids as $member_id) {
                foreach($sr_field_ids as $field_id) {
                    $this->EE->db->query('DELETE FROM '.$this->EE->db->dbprefix('channel_data').' d, '.
                                            $this->EE->db->dbprefix('channel_titles').' t WHERE t.entry_id=d.entry_id AND d.field_id_'.$field_id.'='.$this->EE->db->escape($member_id));
                }
            }
        }
    }

}

/* End of file ext.sr_member.php */
/* Location: /system/expressionengine/third_party/sr_member/ext.sr_member.php */