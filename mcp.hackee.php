<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Hackee_mcp {
	private $action;
	private $url;

	function __construct() {
		$this->EE =& get_instance();
		$this->action = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hackee'.AMP.'method';
		$this->url = BASE.AMP.$this->action;

		return;
	}

	/*
	 * Main settings page.
	 */
	function index() {
		$this->set_title();
		$this->EE->load->library('javascript');
		$this->EE->load->library('table');
		$this->EE->load->helper('form');

		# Load JavaScript related to the tabs. Stolen from the publish code.
		$this->EE->cp->add_js_script(array(
			'ui'	=> array('droppable'),
			'file'	=> array('cp/publish_tabs')
		));
		$this->EE->javascript->compile();

		# Guess the locations of core files. Not everyone installs the system/ directory under DOCUMENT_ROOT.
		//$this->EE->config->item('site_url');
		//$this->EE->functions->fetch_site_index();
		$install = $this->guess_locations();
		$user = '/index.php';
		$admin = FCPATH.'index.php';

		if (file_exists($install['doc_root'].$user)) {
			$user_installed = $this->is_installed(file_get_contents($install['doc_root'].$user)) ? 'Yes' : 'No';
		} else {
			$user_installed = 'Not Found';
		}

		if (file_exists($admin)) {
			$admin_installed = $this->is_installed(file_get_contents($admin)) ? 'Yes' : 'No';
		} else {
			$admin_installed = 'Not Found';
		}

		# Check for Hack:ee installation on the entry scripts...
		$vars['file_user'] = array(
			'file' => str_replace($install['doc_root'], '', $user),
			'installed' => $user_installed,
			'link' => ($user_installed == 'No') ? form_open($this->action.'=install'.AMP.'file='.$user).'<input class="submit" type="submit" value="Install" />'.form_close() : ''
		);
		$vars['file_admin'] = array(
			'file' => str_replace($install['doc_root'], '', $admin),
			'installed' => $admin_installed,
			'link' => ($admin_installed == 'No') ? form_open($this->action.'=install'.AMP.'file='.str_replace($install['doc_root'], '', $admin)).'<input class="submit" type="submit" value="Install" />'.form_close() : ''
		);

		$vars['files'] = array();
		$query = $this->EE->db->get('hackee_mods');
		if ($query->num_rows() > 0) {
			foreach ($query->result() as $row) {
				$vars['files'][] = array(
					'id' => $row->hack_id,
					'edit_link' => $this->url.'=edit'.AMP.'file='.$row->file,
					'desc' => ($row->desc == '') ? basename($row->file) : $row->desc,
					'file_name' => $row->file,
					'changes' => '+'.$row->add.', -'.$row->rem,
					'enabled' => $row->enabled ? 'yes' : '',
				);
			}
		}

		$vars['table_url'] = $this->action.'=toggle_hacks';
		$vars['options'] = array(
			'enable' => lang('enable'),
			'disable' => lang('disable'),
			'delete' => lang('delete')
		);

		$vars['hacked'] = class_exists('hackee');
		$vars['stat'] = array(
			array(
				'path' => str_replace($install['doc_root'], '', dirname(__FILE__).'/mods'),
				'status' => is_writable(dirname(__FILE__).'/mods') ? 'Writable' : 'Error! Directory not writable!'
			),
			array(
				'path' => str_replace($install['doc_root'], '', dirname(__FILE__).'/cached'),
				'status' => is_writable(dirname(__FILE__).'/cached') ? 'Writable' : 'Error! Directory not writable!'
			),
			array(
				'path' => str_replace($install['doc_root'], '', dirname(__FILE__).'/cached/temp'),
				'status' => is_writable(dirname(__FILE__).'/cached/temp') ? 'Writable' : 'Error! Directory not writable!'
			)
		);
		$vars['new_hack'] = form_open($this->action.'=new_hack').'<input class="submit" type="submit" value="Create New Hack" />'.form_close();
		$vars['clear_cache'] = form_open($this->action.'=clear_cache').'<input class="submit" type="submit" value="Clear Cache" />'.form_close();

		//$this->EE->pagination->initialize($p_config);
		//$vars['pagination'] = $this->EE->pagination->create_links();

		$out = $this->EE->load->view('index', $vars, TRUE);
		//$out .= '<pre>'.print_r($this->EE, true).'</pre>';

		return $out;
	}

	/*
	 * Control Panel page. Success should redirect us back to the main settings page. We only show something here if it
	 * was not possible to update the requested file.
	 */
	function install() {
		$install = $this->guess_locations();

		# Determine what file to install on, and attempt it.
		$file = $this->EE->input->get_post('file');

		if (file_exists($install['doc_root'].$file)) {
			$err = $this->install_for_script($install['doc_root'].$file);
		} else {
			$err = $this->install_for_script($file);
		}

		# On success, update file checksums and return.
		if (empty($err)) {
			$this->update_checksums();
			$this->go_home('hackee_installed');
		}

		$this->set_title('Error!');
		return $err;
	}

	/*
	 * Control Panel page. Success should redirect us back to the main settings page. We only show something here if it
	 * was not possible to delete certain files.
	 */
	function clear_cache() {
		$dir = dirname(__FILE__).'/cached/';

		# Loop through the cache directory.
		$dh = opendir($dir);
		$err = '';
		while ($file = readdir($dh)) {
			# Don't try to delete special markers . and ..
			if (in_array($file, array('.', '..'))) {
				continue;
			}

			# Delete the file or track it with an error message.
			if (!@unlink($dir.$file)) {
				$err .= 'Unable to delete '.htmlentities($file).'! You may need to do this manually.<br />';
			}
		}
		closedir($dh);

		# On success, return.
		if (empty($err)) {
			$this->go_home('hackee_cache_cleared');
		}

		$this->set_title('Error!');
		return $err;
	}

	function toggle_hacks() {
		# Load parameters.
		$action = $this->EE->input->get_post('action');
		if (!($hacks = $this->EE->input->get_post('hacks'))) {
			$err = 'No files selected';
		}

		# Enable/Disable - Update
		if (in_array($action, array('enable', 'disable'))) {
			$this->EE->db->where_in('hack_id', $hacks);
			$this->EE->db->update('hackee_mods', array(
				'enabled' => ($action == 'enable')
			));
		# Delete - Well, duh.
		} elseif ($action == 'delete') {
			$this->EE->db->where_in('hack_id', $hacks);
			$this->EE->db->delete('hackee_mods');
		}

		$this->update_mods();

		# On success, go back to our settings page
		if (empty($err)) {
			$this->go_home('hackee_complete');
		}

		$this->set_title('Error!');
		return $err;
	}

	function new_hack() {
		$dir = $this->EE->input->get_post('dir');
		$this->set_title('Select File');
		$ret = '';

		if (empty($dir)) {
			$dir = $_SERVER['DOCUMENT_ROOT'];
		} elseif (!is_dir($dir)) {
			$dir = $_SERVER['DOCUMENT_ROOT'].$dir;
		}

		$ret .= $this->breadcrumbs($dir);

		$dirs = array();
		$files = array();
		if (is_dir($dir)) {
			$dh = opendir($dir);
			$have_renamed = false;
			while ($file = readdir($dh)) {
				if (in_array($file, array('.', '..'))) {
					continue;
				}

				$path = $dir.'/'.$file;
				if (is_dir($path)) {
					$dirs[$file] = '<a href="'.$this->url.'=new_hack'.AMP.'dir='.$path.'">['.$file.']</a>';
				} else {
					$renamed = false;
					if ($file[0] == '.') {
						if (file_exists($dir.'/'.substr($file, 1))) {
							$file = substr($file, 1);
							$have_renamed = true;
							$renamed = true;
						}
					}

					# Allow us to mask files we were installed on.
					if ($renamed || !isset($files[$file])) {
						$files[$file] = '<a href="'.$this->url.'=edit'.AMP.'file='.$path.'">'.$file.'</a>'.($renamed ? ' **' : '');
					}
				}
			}
			closedir($dh);

			ksort($dirs);
			ksort($files);

			$ret .= implode("<br />\n", $dirs)."<br />\n".implode("<br />\n", $files);
			if ($have_renamed) {
				$ret .= '<br /><br />** Hack:ee was installed in place of this file. You may hack the original contents by clicking on the file name.';
			}

		# These should not happen. If a file is requested through this function, go ahead and edit it.
		} elseif (file_exists($dir)) {
			$this->EE->functions->redirect($action.'=edit'.AMP.'file='.$dir);

		# Otherwise, if no file/directory exists, go back to our settings page.
		} else {
			$this->go_home('', 'Unable to find the requested file.');
		}

		return $ret;
	}

	function edit() {
		$ret = '';

		$file = $this->EE->input->get_post('file');
		$this->set_title(basename($file));

		$ret .= '<script type="text/javascript" charset="utf-8">
// <![CDATA[
	$(document).ready(function() {
		$("#save").submit(function() {
			$.ajax({
				url: "'.str_replace(AMP, '&', $this->url).'=save",
				type: "post",
				data: ({
					file: $("#hackee_file").val(),
					contents: $("#hackee_contents").val(),
					title: $("#hackee_title").val(),
					description: $("#hackee_description").val(),
					compile: $("#hackee_compile").attr("checked"),
					XID: EE.XID
				}),
				dataType: "text",
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					$("#hackee_error").html("Unable to communicate with the server: "+errorThrown).slideDown();

					return;
				},
				success: function(data, textStatus) {
					/* Look for a success message. */
					if (data == "success") {
						$("#hackee_error").slideUp();
						document.location.href = "'.str_replace(AMP, '&', $this->url).'=index";
					} else {
						console.log(data);
						$("#hackee_error").html("Server did not confirm changes:<br /><br />"+data).slideDown();
					}

					return;
				}
			});

			return false;
		});
	});
//]]>
</script>';
		$ret .= $this->breadcrumbs($file);
		$ret .= form_open($this->action.'=save', 'id="save"');
		$ret .= '<div id="hackee_error"></div>';
		$ret .= '<input type="hidden" name="file" id="hackee_file" value="'.htmlentities($file).'" />';
		$ret .= '<textarea name="contents" id="hackee_contents" cols="60" rows="15">'.htmlentities(file_get_contents($file)).'</textarea>';
		$ret .= '<div class="tableFooter"><div class="tableSubmit">';
		$ret .= '<label for="hackee_description">Optional description for this hack (required to maintain multiple hacks on the same file):</label> <input type="text" name="description" id="hackee_description" value="" maxlength="255" /><br />';
		$ret .= '<input type="checkbox" name="compile" id="hackee_compile" value="1"'.(substr($file, -4) == '.php' ? ' checked="checked"' : '').' /> <label for="hackee_compile">Verify that this will compile before saving</label><br />';
		$ret .= '<input class="submit" type="submit" value="Save Changes" />';
		$ret .= '</div></div>';
		$ret .= form_close();

		return $ret;
	}

	/*
	 * AJAX Function. Does not display anything cool.
	 */
	function save() {
		ob_start();

		# Load script paramemters
		$contents = $this->EE->input->get_post('contents');
		$description = $this->EE->input->get_post('description');
		$file = $this->EE->input->get_post('file');
		$compile = $this->EE->input->get_post('compile');

		# Enable debugging. We need this when we are modifying PHP files.
		ini_set('display_errors', 1);
		error_reporting(E_ALL);

		# Load the original file
		$orig = file_get_contents($file);

		# Required for diff_match_patch...
		# TODO: Move this to patch write-out in order to allow upgrades to a more correct patch implementation.
		$orig = str_replace('%', '%25', $orig);
		$orig = str_replace(
			array("\n"), //, '`', '[', ']', '\\', '^', '|', '{', '}', '"', '<', '>'),
			array("%0A\n"), //, '%60', '%5B', '%5D', '%5C', '%5E', '%7C', '%7B', '%7D', '%22', '%3C', '%3E'),
			$orig);

		$new = str_replace('%', '%25', $contents);
		$new = str_replace(
			array("\n"), //, '`', '[', ']', '\\', '^', '|', '{', '}', '"', '<', '>'),
			array("%0A\n"), //, '%60', '%5B', '%5D', '%5C', '%5E', '%7C', '%7B', '%7D', '%22', '%3C', '%3E'),
			$new);

		# Attempt to use PHP PECL function
		if (function_exists('xdiff_string_diff')) {
			$diff = xdiff_string_diff($orig, $new);

		# Fall back to PHPdiff
		} else {
			require_once dirname(__FILE__).'/phpdiff/Diff.php';
			require_once dirname(__FILE__).'/phpdiff/Diff/Renderer/Text/Unified.php';

			$a = explode("\n", $orig);
			$b = explode("\n", $new);

			$d = new Diff($a, $b, array());
			$renderer = new Diff_Renderer_Text_Unified();
			$diff = $d->render($renderer);

			# TODO: Move to patch write-out.
			$total = 0;
			$line = array();
			foreach ($a as $k => $v) {
				$total += strlen($v) - 3;
				$line[$k] = $total;
			}

			# Convert line numbers to rough character counts... Blech.
			$diff = preg_replace('/^@@ (-)(\d+)(,\d*)? (\+)(\d+)(,\d*)? @@$/me', '"@@ \1".$line[\\2]."\\3 \4".$line[\\5]."\\6 @@"', $diff);
			# Such a hack for Google's code's sake. Remove %0A from the ends of additions/removals... but
			# leave it on the other lines.
			$diff = preg_replace('/^([+-].*)%0A/m', '\1', $diff);
		}

		# So far, so good?
		$output = ob_get_clean();
		if ($output == '') {
			$save = true;
			if ($compile) {
				$temp = dirname(__FILE__).'/cached/temp/'.mt_rand(100000, 999999).'.php';
				file_put_contents($temp, $contents);

				# Try to find a PHP interpreter that we can run this file through.
				$php = '';
				if (exec('php5 -v', $output, $return) && ($return == 0)) {
					$php = 'php5';
				} elseif (exec('php -v', $output, $return) && ($return == 0)) {
					$php = 'php';
				}

				# If we found a PHP interpreter, use it as a lint check.
				if (!empty($php)) {
					exec(escapeshellarg($php).' -l '.escapeshellarg($temp), $output, $return);
					if ($return != 0) {
						echo implode("<br />\n", $output);
						$save = false;
					}
				} else {
					# Really ugly but require_once as a last-ditch effort. CI requires > 5.0.4 so
					# php_check_syntax() is not worth wasting our fingers on.
					#
					# On fatal error, we are about to kill our script...
					ob_start();
					require_once $temp;

					$output = ob_get_clean();
					if ($output != '') {
						$save = false;
						echo $output;
					}
				}

				//unlink($temp);
			}

			if ($save) {
				preg_match('/^(\+)/m', $diff, $add);
				preg_match('/^(-)/m', $diff, $rem);

				# Only save actual modifications.
				if (count($add) || count($rem)) {
					$data = array(
						'file' => $file,
						'desc' => $description,
						'diff' => $diff,
						'date' => time(),
						'add' => count($add) - 1,
						'rem' => count($rem) - 1,
					);
					$this->EE->db->query($this->EE->db->insert_string('hackee_mods', $data));
					//$this->EE->db->query($this->EE->db->update_string('hackee_mods', $data, 'hack_id = '.intval()));
				}

				$this->update_mods();
			}

			echo "success";
		} else {
			echo $output;
		}

		exit;
	}

	private function breadcrumbs($filename) {
		$segments = explode('/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $filename));
		$last = array_pop($segments);

		$ret = empty($segments) ? '' : '<a href="'.$this->url.'=new_hack">Root</a> ';
		$path = '';
		foreach ($segments as $seg) {
			$path .= '/'.$seg;
			$ret .= '<a href="'.$this->url.'=new_hack'.AMP.'dir='.$path.'">'.$seg.'</a> / ';
		}
		if (!empty($ret)) {
			$ret .= $last."<br /><br />\n";
		}

		return $ret;
	}

	private function set_title($optional = '') {
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('hackee_module_name').(empty($optional) ? '' : ' - '.$optional));

		return;
	}

	/*
	 * Redirect to our main settings page. If there is a success message, show it.
	 */
	private function go_home($success = '', $error = '') {
		if (!empty($success)) {
			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line($success));
		}
		if (!empty($error)) {
			$this->EE->session->set_flashdata('message_error', $this->EE->lang->line($error));
		}
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hackee');
	}

	/*
	 * Since we are talking about modifying EE functionality, let's keep a log of when changes are made.
	 */
	private function logger($msg) {
		$this->EE->load->library('logger');
		$this->EE->logger->developer($msg);

		return;
	}

	/*
	 * We do not need to overtly warn our users about file modifications since they were authorized.
	 * See /system/expressionengine/controllers/cp/homepage.php -> accept_checksums().
	 */
	private function update_checksums() {
		$this->EE->load->library('file_integrity');
		$changed = $this->EE->file_integrity->check_bootstrap_files(TRUE);

		if ($changed) {
			foreach ($changed as $site_id => $paths) {
				foreach ($paths as $path) {
					$this->EE->file_integrity->create_bootstrap_checksum($path, $site_id);
				}
			}
		}

		return;
	}

	# TODO: Watch out for query caching with this function.
	private function update_mods() {
		$mods_dir = dirname(__FILE__).'/mods/';
		$cache_dir = dirname(__FILE__).'/cached/';
		$mods = array();

		# Query active mods
		$this->EE->db->where('enabled', 1);
		$query = $this->EE->db->get('hackee_mods');

		if ($query->num_rows()) {
			foreach ($query->result() as $row) {
				$file = realpath($row->file);
				$file = str_replace('/', '-', str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $file));
				$patch = $mods_dir.$file;

				# Open each patch file only once.
				$nl = "\n";
				if (!isset($mods[$file])) {
					$mods[$file] = fopen($patch, 'w');
					$nl = '';
				}

				# Write the current patch to a file (we will not have DB access for about half of
				# the bootstrap time).
				$len = fprintf($mods[$file], $nl."%s", $row->diff);
			}
		}

		# Remove inactive mods
		$dh = opendir($mods_dir);
		while ($file = readdir($dh)) {
			if (in_array($file, array('.', '..'))) {
				continue;
			}

			if (!isset($mods[$file])) {
				unlink($mods_dir.$file);

				# Kill the cache file for this patch.
				unlink($cache_dir.$file);
			}
		}
		closedir($dh);

		# Close file handles for active mods
		foreach ($mods as $fh) {
			fclose($fh);
		}

		return;
	}

	# Check whether "hackee.php" has already been inserted into a file.
	#
	# NOTE: Within EE you could use PHP's class_exists('hackee') instead.
	private function is_installed($contents) {
		return (strpos($contents, 'hackee.php') !== false);
	}

	# Install Hack:ee into top-level script $file.
	private function install_for_script($file) {
		$ret = '';

		if (is_writable($file)) {
			$basedir = dirname($file);
			$basename = basename($file);

			# Check if we are already installed in the target file...
			$contents = file_get_contents($file);
			if (!$this->is_installed($contents)) {
				# Shorten the filename of this file. This might help to make the site more portable
				# while we are installed.
				$loc = str_replace(array(
					$_SERVER['DOCUMENT_ROOT'],
					'"'
				), array(
					'',
					'\"'
				), __FILE__);
				$loc = (($loc != __FILE__) ?
					$_SERVER['DOCUMENT_ROOT'] :
					''
				).'"'.$loc.'"';
				$loc = str_replace(basename(__FILE__), 'hackee.php', $loc);
				$contents = '<?php
require_once('.$loc.');
$hackee_inserted = __FI'.'LE__;
$hackee_cache = hackee::_cache("./.'.$basename.'");
unset($hackee_inserted);
require_once $hackee_cache;
unset($hackee_cache);
?>';

				# Write us out. Ensure that the whole file gets written to prevent issues with full harddrives.
				if (file_put_contents($file.'-replacement', $contents) === strlen($contents)) {
					if (file_exists($basedir.'/.'.$basename)) {
						rename($basedir.'/.'.$basename, $basedir.$basename.filemtime($file));
					}
					rename($file, $basedir.'/.'.$basename);
					rename($file.'-replacement', $file);
				} else {
					$ret = 'Unable to write the new code for (not enough disk space?): '.htmlentities($file).'!';
					unlink($file.'-replacement');
				}
			} else {
				$ret = 'Hack:ee is already installed on: '.htmlentities($file).'!';
			}
		} else {
			$ret = 'We do not have permissions to modify: '.htmlentities($file).'!';
			$ret .= form_open($this->action.'=install'.AMP.'file='.$file).'<input class="submit" type="submit" value="Retry" />'.form_close();
		}

		return $ret;
	}

	/*public function uninstall_for_script($file) {
		$ret = false;

		if (is_writable($file)) {
			$basename = basename($file);
			$ext = '';
			if (preg_match('/(?:^|\/|\\\\)([^\/\\]+)\.([^\.]+)$/i', $file, $matches)) {
				$basename = $matches[1];
				$ext = $matches[2];
			}

			# Check if we are already installed in the target file...
			$contents = file_get_contents($file);
			if (!$this->is_installed($contents)) {
				$contents = hackee::_unmod_contents($contents, false);
				$contents = preg_replace("/\nrequire_once(\"[^\"]+".basename(__FILE__)."\");/", '', $contents);

				# Write us out. Ensure that the whole file gets written to prevent issues with full harddrives.
				if (file_put_contents($file.'-replacement', $contents) === strlen($contents)) {
					rename($file.'-replacement', $file);
				} else {
					unlink($file.'-replacement');
				}

				$ret = true;
			}
		}

		return $ret;
	}*/

	private function guess_locations() {
		# TODO:
		$d = defined('SLASH') ? SLASH : '/';

		# Default paths...
		$doc_root = $_SERVER['DOCUMENT_ROOT'];
		$system = $doc_root.$d.'system';

		# Assume that we are somewhare below the "system" folder.
		$segments = explode($d, dirname(__FILE__));
		do {
			array_pop($segments);

			# Look for "index.php"
			$cur_dir = implode($d, $segments);
			if (file_exists($cur_dir.$d.'index.php')) {
				# Pull the file's contents...
				$contents = file_get_contents($cur_dir.$d.'index.php');

				# Attempt to verify that this is an EE file.
				if (preg_match('/$system_path\s*=\s*/', $contents)) {
					# Now determine back- or front-end...
					if (strpos($contents, 'installer/') !== false) {
						$system = $contents;
					} else {
						$doc_root = $cur_dir;
					}
				}
			}
		} while (!empty($segments));

		return array(
			'doc_root' => $doc_root,
			'system' => $system
		);
	}
}
