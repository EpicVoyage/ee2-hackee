<!-- Tabs -->
<ul class="tab_menu" id="tab_menu_tabs">
	<li id="menu_hacks" title="" class="content_tab">
		<a href="#" title="menu_hacks" class="menu_hacks"><?php echo lang('hacks'); ?></a>&nbsp;
	</li>
	<li id="menu_health" title="" class="content_tab">
		<a href="#" title="menu_health" class="menu_health"><?php echo lang('health'); ?></a>&nbsp;
	</li>
	<li id="menu_settings" title="" class="content_tab">
		<a href="#" title="menu_settings" class="menu_settings"><?php echo lang('settings'); ?></a>&nbsp;
	</li>
</ul><br />

<div id="holder">
<div id="<?php echo url_title('hacks', 'underscore', TRUE); ?>" class="main_tab">
	<div class="tableFooter">
		<div class="tableSubmit">
			<?php echo $new_hack; ?>
		</div>
	</div>
<?php
# Existing Core hacks.
echo form_open($table_url, '', NULL);
$this->table->set_template($cp_table_template);
$this->table->set_heading(
	lang('hack'),
	lang('file_name'),
	lang('changes'),
	lang('enabled'),
	form_checkbox('select_all', 'true', FALSE, 'class="toggle_all" id="select_all"')
);

foreach($files as $file) {
	$this->table->add_row(
		'<a href="'.$file['edit_link'].'">'.$file['desc'].'</a>',
		$file['file_name'],
		$file['changes'],
		'<span class="notice_success">'.$file['enabled'].'</span>',
		form_checkbox('hacks[]', $file['id'])
	);
}

echo $this->table->generate();
?>
<div class="tableFooter">
	<div class="tableSubmit">
		<?php echo form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')).NBS.NBS.form_dropdown('action', $options); ?>
	</div>

	<span class="js_hide"><?php $pagination; ?></span>
	<span class="pagination" id="filter_pagination"></span>
</div>
<?php echo form_close();?>
</div>
<div id="<?php echo url_title('health', 'underscore', TRUE); ?>" class="main_tab js_hide"><br />
<?php
# Installation status.
$this->table->set_template($cp_table_template);
$this->table->set_heading(
	lang('core_file'),
	lang('modified'),
	lang('install')
);

$this->table->add_row($file_user['file'], $file_user['installed'], $file_user['link']);
$this->table->add_row($file_admin['file'], $file_admin['installed'], $file_admin['link']);
echo $this->table->generate();

$this->table->set_template($cp_table_template);
$this->table->set_heading(
	lang('hackee'),
	lang('status')
);
$this->table->add_row('Installed', $hacked ? lang('yes') : lang('no'));
echo $this->table->generate();

# Permission checks.
$this->table->set_template($cp_table_template);
$this->table->set_heading(
	lang('path'),
	lang('status')
);

foreach($stat as $file) {
	$this->table->add_row(
		$file['path'],
		$file['status']
	);
}

echo $this->table->generate();
?>
<div class="tableFooter">
	<div class="tableSubmit">
		<?php echo $clear_cache; ?>
	</div>
</div>
</div>
<div id="<?php echo url_title('settings', 'underscore', TRUE); ?>" class="main_tab js_hide"><br />
Placeholder
</div>
