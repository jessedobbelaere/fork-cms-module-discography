<?php

class UsersInstall extends ModuleInstaller
{
	public function __construct(SpoonDatabase $db, array $languages)
	{
		// set database instance
		$this->db = $db;

		// load install.sql
		$this->importSQL(PATH_WWW .'/backend/modules/users/installer/install.sql');

		// add 'users' as a module
		$this->addModule('users', 'User management.');

		// general settings
		$this->setSetting('users', 'requires_akismet', false);
		$this->setSetting('users', 'requires_google_maps', false);
		$this->setSetting('users', 'default_group', 1);
		$this->setSetting('users', 'date_formats', array('j/n/Y', 'd/m/Y', 'j F Y', 'F j, Y'));
		$this->setSetting('users', 'time_formats', array('H:i', 'H:i:s', 'g:i a', 'g:i A'));

		// module rights
		$this->setModuleRights(1, 'users');

		// action rights
		$this->setActionRights(1, 'users', 'add');
		$this->setActionRights(1, 'users', 'delete');
		$this->setActionRights(1, 'users', 'edit');
		$this->setActionRights(1, 'users', 'index');

		// add default user
		$this->addUser();
	}


	protected function addUser()
	{
		// no god user already exists
		if(!$this->db->getNumRows('SELECT id FROM users WHERE is_god = ? AND deleted = ? AND active = ?;', array('Y', 'N', 'Y')))
		{
			// build settings
			$settings = array();
			$settings['nickname'] = serialize('Fork CMS');
			$settings['name'] = serialize('Fork');
			$settings['surname'] = serialize('CMS');
			$settings['interface_language'] = serialize('nl');
			$settings['date_format'] = serialize('j F Y');
			$settings['time_format'] = serialize('H:i');
			$settings['datetime_format'] = serialize($settings['date_format'] .' '. $settings['time_format']);
			$settings['password_key'] = serialize(uniqid());
			$settings['avatar'] = serialize('no-avatar.gif');


			// @todo davy - slaag mij hard
			$emailBlub = $this->getSetting('core', 'mailer_from');


			// build user
			$user = array();
			$user['group_id'] = $this->getSetting('users', 'default_group');
			$user['email'] = $emailBlub['email'];
			$user['password'] = sha1(md5(unserialize($settings['password_key'])) . md5('fork'));
			$user['active'] = 'Y';
			$user['deleted'] = 'N';
			$user['is_god'] = 'Y';

			// insert user
			$user['id'] = $this->db->insert('users', $user);

			// loop settings
			foreach($settings as $name => $value)
			{
				// insert user settings
				$this->db->insert('users_settings', array('user_id' => $user['id'], 'name' => $name, 'value' => $value));
			}
		}
	}
}

?>