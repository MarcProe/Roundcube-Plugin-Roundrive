<?php

/**
 * Roundcube Drive using flysystem for filesystem
 * Special Version for sciebo
 *
 * @version @package_version@
 * @author Thomas Payen <thomas.payen@apitech.fr>
 * @author Marcus Proest <marcus@proest.net>
 * @see preferences part taken from enigma plugin https://github.com/roundcube/roundcubemail/blob/master/plugins/enigma/enigma.php
 *
 * This plugin is inspired by kolab_files plugin
 * Use flysystem library : https://github.com/thephpleague/flysystem
 * With flysystem WebDAV adapter : https://github.com/thephpleague/flysystem-webdav
 *
 * Copyright (C) 2015 PNE Annuaire et Messagerie MEDDE/MLETR
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class roundrive extends rcube_plugin
{
    // all task excluding 'login' and 'logout'
    public $task = '?(?!login|logout).*';

    public $rc;
    public $home;
    private $engine;

    public function init()
    {
        $this->rc = rcube::get_instance();

        // Register hooks
        $this->add_hook('refresh', array($this, 'refresh'));

        // Plugin actions for other tasks
        $this->register_action('plugin.roundrive', array($this, 'actions'));


        // Register task
        $this->register_task('roundrive');

        // Register plugin task actions
        $this->register_action('index', array($this, 'actions'));
        $this->register_action('prefs', array($this, 'actions'));
        $this->register_action('open',  array($this, 'actions'));
        $this->register_action('file_api', array($this, 'actions'));

        // Load UI from startup hook
        $this->add_hook('startup', array($this, 'startup'));


	//Settings
	if ($this->rc->task == 'settings') {
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
            $this->add_hook('preferences_sections_list', array($this, 'preferences_sections_list'));
            $this->add_hook('preferences_list', array($this, 'preferences_list'));
            $this->add_hook('preferences_save', array($this, 'preferences_save'));
            $this->register_action('plugin.roundrive', array($this, 'preferences_ui'));
            $this->load_ui();
            if (empty($_REQUEST['_framed']) || strpos($this->rc->action, 'plugin.roundrive') === 0) {
        }
        }
    }


    function load_env()
    {
        if ($this->env_loaded) {
            return;
        }
        $this->env_loaded = true;
        $include_path = $this->home . '/lib' . PATH_SEPARATOR;
        $include_path .= ini_get('include_path');
        set_include_path($include_path);
        $this->load_config();
        // include localization (if wasn't included before)
        $this->add_texts('localization/');
    }
    /**
     * Plugin UI initialization.
     */
    function load_ui($all = false)
    {
        if (!$this->ui) {
            // load config/localization
            $this->load_env();
            // Load UI
            //$this->ui = new enigma_ui($this, $this->home);
        }
        if ($all) {
            $this->ui->add_css();
            $this->ui->add_js();
        }
    }

    /**
     * Handler for settings_actions hook.
     * Adds Sciebo settings section into preferences.
     *
     * @param array Original parameters
     *
     * @return array Modified parameters
     */
    function settings_actions($args)
    {
        // add labels
        $this->add_texts('localization/');
        // register as settings action
        $args['actions'][] = array(
            'action' => 'plugin.roundrive',
            'class'  => 'sciebo auth',
            'label'  => 'scieboauth',
            'title'  => 'scieboauth',
            'domain' => 'roundrive',
        );

    }

     /**
     * Handler for preferences_sections_list hook.
     * Adds Encryption settings section into preferences sections list.
     *
     * @param array Original parameters
     *
     * @return array Modified parameters
     */
    function preferences_sections_list($p)
    {
        $p['list']['roundrive'] = array(
            'id' => 'roundrive', 'section' => $this->gettext('sciebo auth'),
        );
        return $p;
    }

 /**
     * Handler for preferences_list hook.
     * Adds options blocks into Sciebo settings sections in Preferences.
     *
     * @param array Original parameters
     *
     * @return array Modified parameters
     */
    function preferences_list($p)
    {
        if ($p['section'] != 'roundrive') {
            return $p;
        }
        $p['blocks']['main']['name'] = $this->gettext('mainoptions');
        if (!$p['current']) {
            $p['blocks']['main']['content'] = true;
            return $p;
        }
        $field_id = 'rcmfd_roundrive_sciebo_pubkey';
        $input    = new html_inputfield(array(
                'name'  => '_roundrive_sciebo_pubkey',
                'id'    => $field_id,
                'value' => 1,
        ));
        $p['blocks']['main']['options']['roundrive_sciebo_pubkey'] = array(
 	       'title'   => html::label($field_id, $this->gettext('enter_pubkey')),
	       'content' => $input->show($this->rc->config->get('roundrive_sciebo_pubkey')),
        );

	$field_id = 'rcmfd_roundrive_sciebo_passphrase';
        $input    = new html_inputfield(array(
                'name'  => '_roundrive_sciebo_passphrase',
                'id'    => $field_id,
                'value' => 1,
        ));
        $p['blocks']['main']['options']['roundrive_sciebo_passphrase'] = array(
               'title'   => html::label($field_id, $this->gettext('enter_passphrase')),
               'content' => $input->show($this->rc->config->get('roundrive_sciebo_passphrase')),
        );


	$field_id = 'rcmfd_roundrive_sciebo_info';

	
	$p['blocks']['main']['options']['roundrive_sciebo_infotext'] = array(
		'title'	=> html::label($field_id, $this->gettext('sciebo_info')),
	);

    	return $p;
    }

    /**
     * Handler for preferences_save hook.
     * Executed on Sciebo settings form submit.
     *
     * @param array Original parameters
     *
     * @return array Modified parameters
     */
    function preferences_save($p)
    {
        if ($p['section'] == 'roundrive') {
            $p['prefs'] = array(
                'roundrive_sciebo_pubkey'    => (string) rcube_utils::get_input_value('_roundrive_sciebo_pubkey', rcube_utils::INPUT_POST),
                'roundrive_sciebo_passphrase'    => (string) rcube_utils::get_input_value('_roundrive_sciebo_passphrase', rcube_utils::INPUT_POST),
            );
        }
        return $p;
    }


    /**
     * Creates roundrive_engine instance
     */
    private function engine()
    {
        if ($this->engine === null) {
            $this->load_config();


            require_once $this->home . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'roundrive_files_engine.php';

            $this->engine = new roundrive_files_engine($this);
        }

        return $this->engine;
    }

    /**
     * Startup hook handler, initializes/enables Files UI
     */
    public function startup($args)
    {
        // call this from startup to give a chance to set
        $this->ui();
    }

    /**
     * Adds elements of files API user interface
     */
    private function ui()
    {
        if ($this->rc->output->type != 'html') {
            return;
        }

        if ($engine = $this->engine()) {
            $engine->ui();
        }
    }

    /**
     * Refresh hook handler
     */
    public function refresh($args)
    {
        // Here we are refreshing API session, so when we need it
        // the session will be active
        if ($engine = $this->engine()) {
        }

        return $args;
    }

    /**
     * Engine actions handler
     */
    public function actions()
    {
        if ($engine = $this->engine()) {
            $engine->actions();
        }
    }
}
