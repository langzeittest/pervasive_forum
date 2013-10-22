<?php

if (!defined('PHORUM')) return;

//
// Add pervasive forums to forum array.
//
function mod_pervasive_forum_index($forums) {
    global $PHORUM;

    if (!is_array($forums)) return $forums;

    if (    isset($PHORUM['mod_pervasive_forum']['display_options'])
         && is_array($PHORUM['mod_pervasive_forum']['display_options']) ) {
        // Get all parents for the current forum_id till root (but without root)
        $all_parents = array();
        $act_id = $PHORUM['forum_id'];
        while ($act_id>0) {
            $folder = phorum_db_get_forums($act_id);
            $act_id = $folder[$act_id]['parent_id'];
            if ($act_id>0) {
                $all_parents[$act_id] = $act_id;
            }
        }
        // Build list with pervasive forum ids.
        $add_ids = array();
        foreach ($PHORUM['mod_pervasive_forum']['display_options'] as $forum_id => $display_option) {
            if ($display_option==1) {
                // Show in each folder
                $add_ids[$forum_id] = $forum_id;
            } elseif ($display_option==2) {
                // Show in each subfolder, so check if pervasive forum belong to one
                // of the parent folders.
                $pervasive_forum = phorum_db_get_forums($forum_id);
                if (isset($all_parents[$pervasive_forum[$forum_id]['parent_id']])) {
                    $add_ids[$forum_id] = $forum_id;
                }
            }
        }
        // Remove forums which are already in forum list.
        foreach ($forums as $key => $forum) {
            if (isset($add_ids[$forum['forum_id']])) {
                unset($add_ids[$forum['forum_id']]);
            }
        }
        // Add pervasive forums to forum list.
        if (!empty($add_ids)) {
            // Get new flags
            if ($PHORUM['DATA']['LOGGEDIN']) {
                if ($PHORUM['show_new_on_index']==2) {
                    $new_checks = phorum_db_newflag_check($add_ids);
                } elseif ($PHORUM['show_new_on_index']==1) {
                    $new_counts = phorum_db_newflag_count($add_ids);
                }
            }
            // Prepare all stuff we need for the index page (I hope, the Phorum
            // developers will put this stuff one day in a separate function;
            // for the moment they have that code two times: in index_new and
            // index_classic... And even better: make a new hook after getting
            // forums/folders from database and before preparing that stuff).
            $add_forums = phorum_db_get_forums($add_ids);
            foreach ($add_forums as $key => $forum) {
                if (    $PHORUM['hide_forums']
                     && !phorum_api_user_check_access(PHORUM_USER_ALLOW_READ, $forum['forum_id']) ) {
                    unset($add_forums[$key]);
                    continue;
                }
                $forum['URL']['LIST'] = phorum_get_url(PHORUM_LIST_URL, $forum['forum_id']);
                if ($PHORUM['DATA']['LOGGEDIN']) {
                    $forum['URL']['MARK_READ']
                        = phorum_get_url
                              (PHORUM_INDEX_URL, $forum['forum_id'], 'markread', $PHORUM['forum_id']);
                }
                if (isset($PHORUM['use_rss']) && $PHORUM['use_rss']) {
                    $forum['URL']['FEED']
                        = phorum_get_url
                              (PHORUM_FEED_URL, $forum['forum_id'], 'type='.$PHORUM['default_feed']);
                }
                if ($forum['message_count'] > 0) {
                    $forum['last_post'] = phorum_date($PHORUM['long_date_time'], $forum['last_post_time']);
                    $forum['raw_last_post'] = $forum['last_post_time'];
                } else {
                    $forum['last_post'] = '&nbsp;';
                }
                $forum['raw_message_count'] = $forum['message_count'];
                $forum['message_count']
                    = number_format($forum['message_count'], 0, $PHORUM['dec_sep'], $PHORUM['thous_sep']);
                $forum['raw_thread_count'] = $forum['thread_count'];
                $forum['thread_count']
                    = number_format($forum['thread_count'], 0, $PHORUM['dec_sep'], $PHORUM['thous_sep']);
                if ($PHORUM['DATA']['LOGGEDIN']) {
                    if ($PHORUM['show_new_on_index']==1) {
                        $forum['new_messages']
                            = number_format
                                  ( $new_counts[$forum['forum_id']]['messages'],
                                    0,
                                    $PHORUM['dec_sep'],
                                    $PHORUM['thous_sep'] );
                        $forum['new_threads']
                            = number_format
                                  ( $new_counts[$forum['forum_id']]['threads'],
                                    0,
                                    $PHORUM['dec_sep'],
                                    $PHORUM['thous_sep'] );
                    } elseif ($PHORUM['show_new_on_index']==2) {
                        if (!empty($new_checks[$forum['forum_id']])) {
                            $forum['new_message_check'] = true;
                        } else {
                            $forum['new_message_check'] = false;
                        }
                    }
                }
                if ($PHORUM['use_new_folder_style']) {
                    $forum['level'] = 1;
                }
                $forums[] = $forum;
            }
            // Sort forums with display_order and name ascending.
            $level = array();
            $display_order = array();
            $name = array();
            foreach ($forums as $key => $forum) {
                if ($PHORUM['use_new_folder_style']) {
                    $level[$key] = $forum['level'];
                } else {
                    $level[$key] = 0;
                }
                $display_order[$key] = $forum['display_order'];
                $name[$key] = $forum['name'];
            }
            // Add $forums as the last parameter, to sort by the common key
            array_multisort($level, SORT_ASC, $display_order, SORT_ASC, $name, SORT_ASC, $forums);
        }
    }

    return $forums;
}

//
// Add sanity checks
//
function mod_pervasive_forum_sanity_checks($sanity_checks) {
    if (    isset($sanity_checks)
         && is_array($sanity_checks) ) {
        $sanity_checks[] = array(
            'function'    => 'mod_pervasive_forum_do_sanity_checks',
            'description' => 'Pervasive Forum Module'
        );
    }
    return $sanity_checks;
}

//
// Do sanity checks
//
function mod_pervasive_forum_do_sanity_checks() {
    global $PHORUM;

    // Check if module settings exists.
    if (    !isset($PHORUM['mod_pervasive_forum']['display_options'])
         || !is_array($PHORUM['mod_pervasive_forum']['display_options']) ) {
          return array(
                     PHORUM_SANITY_CRIT,
                     'The default settings for the module are missing.',
                     "Login as administrator in Phorum's administrative "
                         .'interface and go to the "Modules" section. Open '
                         .'the module settings for the Pervasive Forum '
                         .'Module add at least one pervasive forum.'
                 );
    }

    return array(PHORUM_SANITY_OK, NULL);
}


?>