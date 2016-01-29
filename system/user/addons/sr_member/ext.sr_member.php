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
	public $version			= '1.2';
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
        'cp_members_member_create',
        'sessions_end',
    );

    protected $extension_class_name = __CLASS__;

    public function settings() {
        $settings = array();
        ee()->load->add_package_path(PATH_THIRD.'sr_member/');
        ee()->load->library('settingslib');
        ee()->settingslib->addChannelsCheckboxes($settings, 'sr_member_channels_populate_on_member_register');
        ee()->settingslib->addDropdown($settings, 'sr_member_entry_author', array('admin' => 'Admin', 'member' => 'Member'));
        ee()->settingslib->addCheckboxes($settings, 'sr_member_delete_entry_on_member_delete', array('y' => 'Yes'));

        return $settings;
    }

    private function loadSRMemberLib()
    {
        ee()->load->add_package_path(PATH_THIRD.'sr_member/');
        ee()->load->library('srmemberlib');
    }

    /**
     * Called when Simple Registration finishes creating a member
     *
     * @param $member_id the new member's member_id
     * @param $member_data array with information about the member
     * @param $ref a reference to the ext.simple_registration.php object
     */
    public function on_simple_registration_success($member_id, $member_data, $ref)
    {
        $this->loadSRMemberLib();
        ee()->srmemberlib->create_member_entries($member_id, $member_data, $this->settings);
    }

    /**
     * Called when a member is created in the CP
     *
     * @param $member_id
     * @param $member_data
     */
    public function on_cp_members_member_create($member_id, $member_data)
    {
        $this->loadSRMemberLib();
        ee()->srmemberlib->create_member_entries($member_id, $member_data, $this->settings, TRUE);
    }

    /**
     * Map member variables
     *
     * ee()->config->_global_vars = array_merge($early, ee()->config->_global_vars);
     * @param $ref
     */
    public function on_sessions_end($sess)
    {
        if (REQ != 'PAGE') return;  // exit if not a page request

        if($sess->userdata['member_id'] && $this->settings['sr_member_channels_populate_on_member_register']) {
            $member_id = $sess->userdata['member_id'];

            $member_tags_arr = array();
            $sr_member_fields = ee()->db->get_where('channel_fields', array('site_id' => ee()->config->item('site_id'), 'field_type' => 'sr_member'));
            if($sr_member_fields->num_rows() > 0) {


                ee()->load->model('file_upload_preferences_model');
                $upload_prefs = ee()->file_upload_preferences_model->get_file_upload_preferences(NULL, NULL, TRUE);

                /**
                 * Get fields
                 */
                $fields = ee()->db->from('channels c, field_groups g, channel_fields f')
                            ->where('g.group_id', 'f.group_id', FALSE)
                            ->where('c.field_group', 'g.group_id', FALSE)
                            ->where_in('c.channel_id', $this->settings['sr_member_channels_populate_on_member_register'])
                            ->get();

                $fields_arr = array();
                $field_types_arr = array();
                foreach($fields->result() as $field ) {
                    $fields_arr[$field->field_id] = $field->field_name;
                    $field_types_arr[$field->field_id] = $field->field_type;
                    $member_tags_arr[$this->tag_prefix.$field->field_name] = '';    // just set empty for now

                    if($field->field_type == 'assets' || $field->field_type == 'file') {
                        $member_tags_arr[$this->tag_prefix.$field->field_name.':server_path'] = '';
                        $member_tags_arr[$this->tag_prefix.$field->field_name.':file_name'] = '';
                    }
                }

                $sr_member_entry_ids = array();

                foreach($sr_member_fields->result() as $sr_member_field) {
                    $member_info = ee()->db->from('channel_data d, channels c, field_groups g')
                                    ->where('c.site_id', ee()->config->item('site_id'))
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

                                $as = ee()->db->from('assets_selections a, assets_files f, assets_folders fo')
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

            }

            ee()->config->_global_vars = array_merge($member_tags_arr, ee()->config->_global_vars);
        }
    }

    /**
     * Called when members are deleted from the CP.
     *
     */
    public function on_cp_members_member_delete_end($member_ids)
    {
        if(isset($this->settings['sr_member_delete_entry_on_member_delete']) && $this->settings['sr_member_delete_entry_on_member_delete'] == 'y') {

            /**
             * Find all SR_member fields
             */
            $q = ee()->db->from('channel_fields')->get_where(array(
                    'site_id' => ee()->config->item('site_id'),
                    'field_type' => 'sr_member')
            );
            $sr_field_ids = array();
            foreach($q->result() as $row) {
                $sr_field_ids = $row->field_id;
            }

            foreach($member_ids as $member_id) {
                foreach($sr_field_ids as $field_id) {
                    ee()->db->query('DELETE FROM '.ee()->db->dbprefix('channel_data').' d, '.
                                            ee()->db->dbprefix('channel_titles').' t WHERE t.entry_id=d.entry_id AND d.field_id_'.$field_id.'='.ee()->db->escape($member_id));
                }
            }
        }
    }

}

/* End of file ext.sr_member.php */
/* Location: /system/expressionengine/third_party/sr_member/ext.sr_member.php */