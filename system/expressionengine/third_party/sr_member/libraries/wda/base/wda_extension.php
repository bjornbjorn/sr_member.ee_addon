<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if(!class_exists('WDA_Extension')) {

    /**
     * WDA EE Extension Base
     *
     * Class WDA_Extension
     */
    class WDA_Extension {

        const BASE_VERSION = 1;

        public $settings_exist	= 'y';
        public $settings 		= array();

        /**
         * The hooks to listen to. The hook call will be linked to the method on_<hook-name>, so. e.g.
         * adding hook "member_register_start" in the $active_hooks array will call "on_member_register_start" method
         * in this extension file.
         */
        protected $active_hooks = array();

        protected $extension_class_name;



        /**
         * Constructor
         *
         * @param 	mixed	Settings array or empty string if none exist.
         */
        public function __construct($settings = '')
        {
            $this->EE =& get_instance();
            $this->settings = $settings;
        }



        public function activate_extension()
        {
            // Setup custom settings in this array.
            $this->settings = array();

            if(!$this->extension_class_name) {
                throw new Exception('$this->extension_class_name not specified');
            }

            if(count($this->active_hooks) == 0) {
                throw new Exception('$this->hooks array in extension is empty, add hooks to listen to there');
            }

            foreach($this->active_hooks as $hook) {
                $data = array(
                    'class'		=> $this->extension_class_name,
                    'method'	=> 'on_'.$hook,
                    'hook'		=> $hook,
                    'settings'	=> serialize($this->settings),
                    'version'	=> $this->version,
                    'enabled'	=> 'y'
                );

                $this->EE->db->insert('extensions', $data);
            }
        }


        // ----------------------------------------------------------------------

        /**
         * Disable Extension
         *
         * This method removes information from the exp_extensions table
         *
         * @return void
         */
        function disable_extension()
        {
            $this->EE->db->where('class', $this->extension_class_name);
            $this->EE->db->delete('extensions');
        }


        // ----------------------------------------------------------------------

        /**
         * Update Extension
         *
         * This function performs any necessary db updates when the extension
         * page is visited
         *
         * @return 	mixed	void on update / false if none
         */
        function update_extension($current = '')
        {
            if ($current == '' OR $current == $this->version)
            {
                return FALSE;
            }
        }

        // ----------------------------------------------------------------------

    }

    /**
     * Class for working with the settings array
     *
     */
    class WDA_Setting {

        private $ref;   // reference to the WDA_Extension object

        public function __construct($wda_extension) {
            $this->ref = $wda_extension;
            $this->EE = get_instance();
        }

        /**
         * Add a text field to the settings array
         *
         * @param $key the key, remember to add a the label for this key in the language file
         * @return array
         */
        public function addTextfield($key) {
            $this->ref->settings[$key] =  array('i', '', '');
        }

        /**
         * Add a radio button Yes/No
         *
         * @param $key
         * @param $default_value
         * @return mixed
         */
        public function addRadioButtons($key, $default_value = 'y') {
            $this->ref->settings[$key] = array('r', array('y' => "Yes", 'n' => "No"), $default_value);
        }

        /**
         * Add a dropdown
         *
         * @param $key
         * @param $values ie. array('fr' => 'France', 'de' => 'Germany', 'us' => 'United States')
         * @param bool $default_value
         */
        public function addDropdown($key, $values, $default_value=FALSE) {
            $this->ref->settings[$key] = array('s', $values, $default_value);
        }

        /**
         *
         * @param $settings
         * @param $key
         * @param $values Should be an array like this array('l' => "Lowfat", 's' => "Salty")
         * @param string $default_values optional, defaults to NONE selected. Default selected values would be e.g. array('1', 's')
         */
        public function addCheckboxes($key, $values, $default_values = FALSE)
        {
            if(!$default_values) {
                $default_values = array();
                foreach($values as $vkey => $vvalue) {
                    $default_values[$vkey] = '';
                }
            }

            $this->ref->settings[$key] = array('c', $values, $default_values);
        }


        /**
         * Add checkboxes for selecting Member Groups
         *
         * @param $key the key, remember to add a the label for this key in the language file
         * @param bool $min_member_group_id minimum id of the member group to show (for "Members" and up, use 5)
         * @return array
         */
        public function addMembergroupCheckboxes($key, $min_member_group_id=FALSE)
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

            $this->ref->settings[$key] = array('c', $mg_select, '');
        }

        /**
         * Add checkboxes for selecting Channels
         *
         * @param $key the key, remember to add a the label for this key in the language file
         * @param bool $min_member_group_id minimum id of the member group to show (for "Members" and up, use 5)
         * @return array
         */
        public function addChannelsCheckboxes($key, $min_channel_id=FALSE)
        {
            if($min_channel_id) {
                $this->EE->db->where('channel_id>=', $min_channel_id, FALSE);
            }

            $channels = $this->EE->db->get('channels');
            $channel_select = array();
            foreach($channels->result() as $channel) {
                $channel_select[$channel->channel_id] = $channel->channel_title;
            }

            $this->ref->settings[$key] = array('c', $channel_select, '');
        }

    }

}