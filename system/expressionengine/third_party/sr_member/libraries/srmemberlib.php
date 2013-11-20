<?php

class Srmemberlib {

    public function log($msg)
    {
        ee()->load->library('logger');
        ee()->logger->developer('SR Member: '.$msg);
    }

    /**
     * Create channel entries for a member
     *
     * @param $member_id member_id
     * @param $member_data Array: the newly registered users member data
     * @param extension settings (ext.sr_member.php)
     * @param from_cp does this request come from the CP?
     * @return bool success (true/false) - will return TRUE even if no entries are created (ie. if the settings indicate that no channels should be populated). FALSE on errors (logged to developer log)
     *
     */
    public function create_member_entries($member_id, $member_data, $settings, $from_cp = FALSE)
    {
        $channel_ids = $settings['sr_member_channels_populate_on_member_register'];
        $success = TRUE;

        if(count($channel_ids) > 0) {
            ee()->load->library('api');
            ee()->api->instantiate('channel_entries');
            ee()->api->instantiate('channel_fields');
            ee()->api_channel_fields->fetch_custom_channel_fields();

            $logged_in_member_id = ee()->session->userdata('member_id');
            $author_entry_id = $member_id;
            if($settings['sr_member_entry_author'] == 'admin') {
                $author_entry_id = 1;
            }
            ee()->session->create_new_session($author_entry_id);
            ee()->session->fetch_session_data();
            ee()->session->fetch_member_data();

            foreach($channel_ids as $channel_id) {

                ee()->db->from('channel_fields f');
                ee()->db->join('channels c', 'c.field_group = f.group_id');
                ee()->db->where('c.channel_id', $channel_id);
                ee()->db->where('c.site_id', ee()->config->item('site_id'));
                $q = ee()->db->get();
                $data = array(
                    'title' => $member_data['screen_name']
                );
                foreach($q->result() as $field) {

                    $field_value = ee()->input->post($field->field_name);
                    if($field->field_type == 'sr_member') {
                        $field_value = $member_id;  // should always be set to member id
                    }

                    $data['field_id_'.$field->field_id] = $field_value;
                    $data['field_ft_'.$field->field_id] = 'none';
                }

            }

            $success = ee()->api_channel_entries->save_entry($data, $channel_id);
            if($logged_in_member_id != $author_entry_id) {
                ee()->session->create_new_session($logged_in_member_id, $from_cp);
                ee()->session->fetch_session_data();
                ee()->session->fetch_member_data();
            }

            if(!$success) {
                $this->log("Error - could not add information on member registration: ".print_r(ee()->api_channel_entries->errors, true));
            }
        }

        return $success;
    }
}