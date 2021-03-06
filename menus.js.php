<?php
require_once ('smart-match.inc');

/*
 * Scan 'path' for normal files.
 * For each file, return the filename and the
 * first ".*data-name=['"](.*)['"].*" match
 *
 */
function scan_dir ($path) {
    $entries = Array ();
    $d = @opendir ($path);
    if (!$d)
        return $entries;
    /* Scan through the directory first looking for files.
     * Then for each file, look for a sub-directory that matches
     * the file entry name. If found, scan as a sub-page */
    while (($n = readdir ($d)) !== false) {
        if (is_dir ($path.'/'.$n) ||
            preg_match ('/\.html$/', $n))
            continue;
        $entry = null;
        $f = fopen ($path.'/'.$n, 'r');
        while (!feof ($f)) {
            $l = trim (fgets ($f));
            if (preg_match ('/^.*data-name=[\'"]([^"\']*)["\']/', $l, $matches)) {
                $entry = Array ('wiki' => $n, 'name' => $matches[1]);
                break;
            }
        }
        fclose ($f);
        /* A title wasn't found with the above preg_match, so use the filename */
        if (!$entry) {
            $entry = Array ('wiki' => $n,
                            'name' => make_name (
                                pathinfo ($path.'/'.$n, PATHINFO_FILENAME)));
        }
        if ($entry != null) {
            $entries [] = $entry;
        }
    }
    closedir ($d);
    usort ($entries, "sort_entries");
    for ($i = 0; $i < count ($entries); $i++) {
        $name = preg_replace ('/^[0-9]*[-_]/', '', $entries[$i]['wiki']);
        $name = preg_replace ('/\.[^.]*$/', '', $name);
        $entries[$i]['file'] = $name;
        $entries[$i]['wiki'] = preg_replace ('/\.[^.]*$/', '', $entries[$i]['wiki']);
        $subpages = basename (dir_smart_match ($path.'/'.$entries[$i]['file']));
        if ($subpages) {
            $entries[$i]['subpages'] = scan_dir ($path.'/'.$subpages);
        }
    }

    return $entries;
}

function make_name ($name) {
    return preg_replace ('/_+/', ' ', 
           preg_replace ('/-+/', ' ', 
           preg_replace ('/^[0-9]*[-_]/', '', 
           $name)));
}

function sort_entries ($a, $b) {
    return strcasecmp ($a['wiki'], $b['wiki']);
}

$rebuild = false;
$php = @stat ('menus.js.php');
$menus = @stat ('menus.js');
if (!$menus)
    $rebuild = true;
if (!$rebuild) {
    $documentation = @stat ('documentation');
    $contribute = @stat ('contribute');
    $rebuild = (($documentation && $documentation['mtime'] > $menus['mtime']) ||
                ($contribute && $contribute['mtime'] > $menus['mtime']) ||
                ($php['mtime'] > $menus['mtime']));
}
if ($rebuild) {
    $json = Array ();
    $json[] = Array ('menu' => 'documentation', 
                     'items' => scan_dir ('documentation'));
    $json[] = Array ('menu' => 'contribute', 
                     'items' => scan_dir ('contribute'));
    $json[] = Array ('menu' => 'wiki',
                     'items' => Array (Array ('name' => 'Home', 'file' => 'Home' ),
                                       Array ('name' => 'Pages', 'file' => 'Pages' ),
                                       Array ('name' => 'History', 'file' => 'History' )));
    $f = @fopen ('menus.js', 'w');
    if (!$f) {
	print 'Unable to open menus.js for writing.'."\n";
        exit -1;
    }
    if (!defined('JSON_PRETTY_PRINT'))
        fwrite ($f, 'var menus = '.json_encode ($json).';');
    else
        fwrite ($f, 'var menus = '.json_encode ($json, JSON_PRETTY_PRINT).';');

    fclose ($f);
    @chmod ('menus.js', 0644);
}

header('Content-type: text/javascript');
require ('menus.js');
