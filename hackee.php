<?php
class hackee {
	public static function _cache($file, $dir = false) {
		if (!$real = realpath($file)) {
			if (!empty($dir) && file_exists($dir.'/'.$file)) {
				$real = realpath($dir.'/'.$file);
			}
		}

		# Clean up the filename.
		$fn = hackee::_find($real);

		# Perform our magic.
		$fn = hackee::_hack($real, $fn);

		echo $file.' =&gt; '.$fn.'<br />';

		return $fn;
	}

	public static function _find($file) {
		# All files go to our single directory, so shorten and clean them up.
		$fn = str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $file);
		$fn = str_replace(array('/', '\\'), '-', $fn);

		# Forced relative paths (like ./) need to be corrected for...
		while ((($pos = strpos($fn, '.-')) !== false) && ($pos == 0)) {
			$fn = substr($fn, 2);
		}

		return $fn;
	}

	# Powerhouse magic. Load (and modify if required) the file, then write it to an alternate
	# location. Cache the results.
	# Returns: The file to load ($orig if an error occurred).
	public static function _hack($orig, $cache) {
		global $hackee_inserted;

		$basedir = dirname(__FILE__);
		$cachedir = $basedir.'/cached/';
		$patch = $basedir.'/mods/'.$cache;

		$cachetime = 0;
		if (file_exists($cachedir.$cache)) {
			$cachetime = filemtime($cachedir.$cache);

			# Force an update when the patch file has been updated.
			if (file_exists($patch) && (filemtime($orig) <= $cachetime) && (filemtime($patch) > $cachetime)) {
				$cachetime = 0;
			}
		}

		$cache = $cachedir.$cache;
		if (filemtime($orig) > $cachetime) {
			# Provide an easy way to jump to function end.
			do {
				# Attempt to open the cache file for writing.
				echo '<br />Out: '.$cache.'<br />';
				if (($fh = fopen($cache, 'w')) === false) {
					# ERROR: Unable to open the file for writing.
					$cache = $orig;
					break;
				}

				# If we succeeded, lock the cache file and begin processing the
				# original.
				flock($fh, LOCK_EX);
				$contents = file_get_contents($orig);
				$contents = hackee::_mod_contents($contents, $patch);
				$contents = str_replace('__FILE__', escapeshellarg(isset($hackee_inserted) ? $hackee_inserted : $orig), $contents);

				# Write the contents to the cache file.
				if (fwrite($fh, $contents) !== strlen($contents)) {
					# ERROR: Unable to write file.
					fclose($fh);
					unlink($cache);
					$cache = $orig;
					break;
				}

				# Unlock the file and close it.
				flock($fh, LOCK_UN);
				fclose($fh);
			} while (0);
		}

		return $cache;
	}

	# Convert:
	# 	require 'helloworld.php';
	#
	# Into:
	# 	$hackee_cache = hackee::_cache('helloworld.php');require $hackee_cache;
	#
	# Voodoo level: High.
	public static function _mod_contents($contents, $patch = '') {
		$ret = $contents;

		if (!empty($patch) && file_exists($patch)) {
			$diff = file_get_contents($patch);
			if (function_exists('xdiff_string_patch')) {
				$ret = xdiff_string_patch($ret, $diff);
			} else {
				require_once dirname(__FILE__).'/diff/diff_match_patch.php';
				$diff_match_patch = new diff_match_patch();

				# Update the file...
				$patches = $diff_match_patch->patch_fromText($diff);
				$up = $diff_match_patch->patch_apply($patches, $ret);
				$ret = $up[0];

				# TODO: Test $up[1][patch##] to look for failures.
				/*foreach ($up[1] as $k => $v) {
					if ($v) {
						echo 'Applied #'.$k;
					}
				}*/

			}
		}

		# We want to insert our hacks after any user changes.
		return preg_replace("/(^|\s+|;)((?:require|include)(?:_once)?)\s*([^;\r\n]+);/", '\1$hackee_cache = hackee::_cache(\3, dirname(__FILE__));\2 $hackee_cache;', $ret);
	}
}
?>
