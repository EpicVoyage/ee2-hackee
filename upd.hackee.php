<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Hackee_upd {
	var $version = '1.0';

	function __construct() {
		$this->EE =& get_instance();
	}

	function install() {
		$data = array(
			'module_name' => 'Hackee',
			'module_version' => $this->version,
			'has_cp_backend' => 'y',
			'has_publish_fields' => 'n'
		);
		$this->EE->db->insert('modules', $data);

		$fields = array(
			'hack_id' => array(
				'type' => 'int',
				'unsigned'  => TRUE,
				'auto_increment' => TRUE
			), 'dir' => array(
				'type' => 'tinyint',
				'default' => 0
			), 'file' => array(
				'type' => 'varchar',
				'constraint' => 255
			), 'desc' => array(
				'type' => 'varchar',
				'constraint' => 255
			), 'diff' => array(
				'type' => 'text'
			), 'date' => array(
				'type' => 'int',
				'constraint' => 10,
				'unsigned' => TRUE,
				'null' => FALSE,
				'default' => 0
			), 'add' => array(
				'type' => 'int',
				'unsigned' => TRUE
			), 'rem' => array(
				'type' => 'int',
				'unsigned' => TRUE
			), 'enabled' => array(
				'type' => 'boolean',
				'null' => FALSE,
				'default' => 1
			), 'applied' => array(
				'type' => 'text'
			), 'fatal' => array(
				'type' => 'boolean',
				'default' => 0
			)
		);

		$this->EE->load->dbforge();
		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('hack_id', TRUE);
		$this->EE->dbforge->add_key('file', FALSE);
		$this->EE->dbforge->add_key('desc', FALSE);
		$this->EE->dbforge->create_table('hackee_mods');

		unset($fields);

		return TRUE;
	}

	function uninstall() {
		$this->EE->load->dbforge();
		$this->EE->dbforge->drop_table('hackee_mods');

		$this->EE->db->where('module_name', 'Hackee');
		$this->EE->db->delete('modules');

		return TRUE;
	}

	function update($current = '') {
		return TRUE;
	}
}
