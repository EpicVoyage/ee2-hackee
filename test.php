<?php
$test = file_get_contents('mcp.hackee.php'); /*'This
is
a
test.
See
if
you
can
spot
the
change.';

$test2 = 'This
is
a
patch.
<h1>See</h1>
if
you
can
spot
the
change.';

################################################
# Diff.
require_once dirname(__FILE__).'/phpdiff/Diff.php';
require_once dirname(__FILE__).'/phpdiff/Diff/Renderer/Text/Unified.php';

# Required for diff_match_patch...
# TODO: Move this to patch write-out in order to allow upgrades to a more correct patch implementation.
$orig = str_replace('%', '%25', $test);
$orig = str_replace(
	array("\n", '`', '[', ']', '\\', '^', '|', '{', '}', '"', '<', '>'),
	array("%0A\n", '%60', '%5B', '%5D', '%5C', '%5E', '%7C', '%7B', '%7D', '%22', '%3C', '%3E'),
	$orig);
		
$contents = str_replace('%', '%25', $test2);
$contents = str_replace(
	array("\n", '`', '[', ']', '\\', '^', '|', '{', '}', '"', '<', '>'),
	array("%0A\n", '%60', '%5B', '%5D', '%5C', '%5E', '%7C', '%7B', '%7D', '%22', '%3C', '%3E'),
	$contents); //*

/*$orig = $test;
$contents = $test2; //*

$a = explode("\n", $orig);
$b = explode("\n", $contents);

$d = new Diff($a, $b, array());
$renderer = new Diff_Renderer_Text_Unified();
$diff = $d->render($renderer); //*/

$diff = file_get_contents('mods/-home-chris-projects-expressionengine-projects-hackee-mcp.hackee.php');
/*$pos = file_get_contents('/home/chris/projects/expressionengine/projects/hackee/mcp.hackee.php');
$lines = explode("\n", $pos);

$total = 0;
$line = array();
foreach ($lines as $k => $v) {
	$total += strlen($v);
	$line[$k] = $total;
}

$diff = preg_replace('/^@@ (-)(\d+)(,\d*)? (\+)(\d+)(,\d*)? @@$/me', '"@@ \1[".$line[\\2]."]\\3 \4[".$line[\\5]."]\\6 @@"', $diff);*/

echo $diff."\n\n===================================\n\n";

###########################################################
# Apply.
require_once dirname(__FILE__).'/diff/diff_match_patch.php';
$diff_match_patch = new diff_match_patch();

# Update the file...
$patches = $diff_match_patch->patch_fromText($diff);
$up = $diff_match_patch->patch_apply($patches, $test);
echo "Applied:\n";
foreach ($up[1] as $k => $v) {
	if ($v) {
		echo '#'.$k."\n";
	}
}
echo "\n\n====================================\n\n";
echo $up[0]."\n\n";
