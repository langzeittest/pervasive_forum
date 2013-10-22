<?php

// Make sure that this script is loaded from the admin interface.
if (!defined('PHORUM_ADMIN')) return;

// Save settings in case this script is run after posting
// the settings form.
if (    count($_POST)
     && isset($_POST['display_options']) ) {
    // Create the settings array for this module.
    $PHORUM['mod_pervasive_forum'] = array
        ( 'display_options' => $_POST['display_options'] );

    if (!phorum_db_update_settings(array('mod_pervasive_forum'=>$PHORUM['mod_pervasive_forum']))) {
        $error = 'Database error while updating settings.';
    } else {
        phorum_admin_okmsg('Settings Updated');
    }
}

// We build the settings form by using the PhorumInputForm object.
include_once './include/admin/PhorumInputForm.php';
$frm = new PhorumInputForm('', 'post', 'Save settings');
$frm->hidden('module', 'modsettings');
$frm->hidden('mod', 'pervasive_forum');

// Here we display an error in case one was set by saving
// the settings before.
if (!empty($error)){
    phorum_admin_error($error);
}

$frm->addbreak('Edit Settings for the Pervasive Forum Module');
// Display options
$row = $frm->addbreak('Define forum display options');
$frm->addhelp($row, 'Define forum display options', 'To make a forum pervasive select "Show in each folder" or "Show in each subfolder".');

$tree = phorum_mod_pervasive_forum_getforumtree();
foreach ($tree as $data) {
    $level = $data[0];
    $node = $data[1];
    $name = str_repeat('&nbsp;&nbsp;', $level);
    $name .= '<img border="0" src="'.$PHORUM['http_path'].'/mods/pervasive_forum/images/'
               .($node['folder_flag'] ? 'folder.gif' : 'forum.gif').'" /> ';
    $name .= $node['name'];

    if ($node['folder_flag']) {
        // No settings for folders.
        $frm->addrow($name);
    } else {
        // Settings for forums.
        if (isset($PHORUM['mod_pervasive_forum']['display_options'][$node['forum_id']])) {
            $display_option = $PHORUM['mod_pervasive_forum']['display_options'][$node['forum_id']];
        } else {
            $display_option = 0;
        }
        $frm->addrow
            ( $name,
              $frm->select_tag
                  ( 'display_options['.$node['forum_id'].']',
                    array('', 'Show in each folder', 'Show in each subfolder'),
                    $display_option ) );
    }
}
// Show settings form
$frm->show();

//
// Internal functions
//

function phorum_mod_pervasive_forum_getforumtree() {
    // Retrieve all forums and create a list of all parents
    // with their child nodes.
    $forums = phorum_db_get_forums();
    $nodes = array();
    foreach ($forums as $id => $data) {
        $nodes[$data['parent_id']][$id] = $data;
    }

    // Create the full tree of forums and folders.
    $treelist = array();
    phorum_mod_pervasive_forum_mktree(0, $nodes, 0, $treelist);
    return $treelist;
}

// Recursive function for building the forum tree.
function phorum_mod_pervasive_forum_mktree($level, $nodes, $node_id, &$treelist) {
    // Should not happen but prevent warning messages, just in case...
    if (!isset($nodes[$node_id])) return;

    foreach ($nodes[$node_id] as $id => $node) {

        // Add the node to the treelist.
        $treelist[] = array($level, $node);

        // Recurse folders.
        if ($node['folder_flag']) {
            $level++;
            phorum_mod_pervasive_forum_mktree($level, $nodes, $id, $treelist);
            $level--;
        }
    }
}

?>
