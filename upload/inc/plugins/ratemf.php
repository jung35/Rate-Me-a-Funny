<?php
if(!defined("IN_MYBB"))
{
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Hooks that directly add the functions
 * from this plugin to the forum backend
 */
$plugins->add_hook("postbit", "ratemf_postbit");
$plugins->add_hook("postbit_prev", "ratemf_postbit");
$plugins->add_hook("postbit_pm", "ratemf_postbit");
$plugins->add_hook("postbit_announcement", "ratemf_postbit");

$plugins->add_hook("showthread_start","ratemf_head");
$plugins->add_hook("forumdisplay_start","ratemf_head");
$plugins->add_hook("forumdisplay_thread", "ratemf_thread");

$plugins->add_hook("member_profile_start", "ratemf_profile_view");

$plugins->add_hook("xmlhttp", "ratemf_ajax");

$plugins->add_hook("admin_config_menu", "ratemf_cfg_menu");
$plugins->add_hook("admin_config_action_handler", "ratemf_cfg_page");
$plugins->add_hook("admin_config_permissions ", "ratemf_cfg_permission");


/**
 * Rate Me A Funny plugin info
 * @return array some information about the plugin
 */
function ratemf_info()
{
  return array(
    "name" => "Rate Me a Funny",
    "description" => "This plugin lets the user rate someone a smile (icon) per postbit.
    <!--
      <br>
      <b>
        Please look at
        <a href='https://github.com/jung3o/Rate-Me-a-Funny/wiki/How-to-Install'>
        THIS READ ME
        </a>
        so you know what to do after you install it!
      </b>
    -->",
    "website" => "https://github.com/jung3o/Rate-Me-a-Funny/",
    "author" => "Jung Oh",
    "authorsite" => "http://jung3o.com",
    "version" => "2.0.5",
    "compatibility" => "18*",
  );
}

/**
 * Rate Me A Funny plugin installer
 */
function ratemf_install()
{
  global $db, $cache;

  /**
   * Create setting group
   */
  $ratemf_settinggroups = array(
      "name" => "ratemf",
      "title" => "Rate Me a Funny Settings",
      "description" => "Settings for the Rate Me a Funny plugin.",
      "disporder" => "99",
      "isdefault" => 0
  );
  $db->insert_query("settinggroups", $ratemf_settinggroups);

  /**
   * Grab Setting Group ID for the plugin to use
   * it on the settings created for this plugin
   */
  $gid = $db->insert_id();

  /**
   * Possible settings to administrate this plugin
   */
  $ratemf_settings = array();
  $ratemf_settings[] = array(
      "name" => "ratemf_disabled_group",
      "title" => "Groups that cannot use this. (UNIVERSAL)",
      "description" => "Type in all the groups that you don't want them using this (Seperated by comma (,))",
      "optionscode" => "groupselect",
      "value" => "7,1,5"
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_ajax_refresh",
      "title" => "Ajax refresh",
      "description" => "How long till the data gets refreshed by ajax? (in seconds)",
      "optionscode" => "numeric",
      "value" => "5"
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_multirate",
      "title" => "Multi-Rate",
      "description" => "Allows user to rate multiple things (Yes to enable)",
      "optionscode" => "yesno",
      "value" => 0
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_double_delete",
      "title" => "Rate delete",
      "description" => "Allows user to rate a post 2 times to delete their rating (Yes to enable)",
      "optionscode" => "yesno",
      "value" => 1
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_selfrate",
      "title" => "Allow Self Rate",
      "description" => "Allow users to rate themselves. (yes = allowed to rate self)",
      "optionscode" => "yesno",
      "value" => 1
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_op_thread_show",
      "title" => "Show top OP rating on thread",
      "description" => "Show the minimum requirement for top rating to show. (0 = disabled)",
      "optionscode" => "numeric",
      "value" => 10
  );

  foreach($ratemf_settings as $key => $setting)
  {
    $insert = array(
      "name" => $db->escape_string($setting['name']),
      "title" => $db->escape_string($setting['title']),
      "description" => $db->escape_string($setting['description']),
      "optionscode" => $db->escape_string($setting['optionscode']),
      "value" => $db->escape_string($setting['value']),
      "disporder" => $key + 1,
      "gid" => intval($gid),
    );
    $db->insert_query("settings", $insert);
  }

  rebuild_settings();
  change_admin_permission("config", "ratemf", 1);

  if(!$db->table_exists("ratemf_postbit"))
  {
    /**
     * Create new table to store all the ratings from posts
     *
     * uid =>   User ID of the person rating
     * pid =>   Post ID of the post that is being rated
     * tid =>   Thread ID of the post that is being rated
     * puid =>  User ID of the post that is being rated
     * rid =>   ID of the rating from `ratemf_rates`
     * rate_time =>   Time when the rating was made
     * ip =>    IP of the person raiting
     */
    $db->write_query("
      CREATE TABLE  " . TABLE_PREFIX . "ratemf_postbit (
        `id` SMALLINT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `uid` SMALLINT(5) NOT NULL,
        `pid` SMALLINT(5) NOT NULL,
        `tid` SMALLINT(5) NOT NULL,
        `puid` SMALLINT(5) NOT NULL,
        `rid` SMALLINT(5) NOT NULL,
        `rate_time` DATETIME NOT NULL,
        `del_time` DATETIME NULL,
        `ip` VARCHAR(255) NULL
      ) ENGINE = MYISAM ;
    ");
  }

  if(!$db->table_exists("ratemf_rates"))
  {
    /**
     * Create new table to store all the rating informations
     * name =>    The name of the rating it will appear on the admin cp
     * postbit => The name of the raiting when user hover over it
     * image =>   Place the image is located relation to images/rating/
     *
     * Rest are permissions
     */
    $db->write_query("
      CREATE TABLE  " . TABLE_PREFIX . "ratemf_rates (
        `id` SMALLINT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `name` VARCHAR(255) NOT NULL ,
        `postbit` VARCHAR(255) NOT NULL,
        `image` VARCHAR(255) NOT NULL ,
        `selected_ranks_use` VARCHAR(255) NULL ,
        `selected_ranks_see` VARCHAR(255) NULL ,
        `selected_forum_use` VARCHAR(255) NULL ,
        `del_time` DATETIME NULL,
        `disporder` SMALLINT(5) NOT NULL
      ) ENGINE = MYISAM ;
    ");

    $ratemf_rates_preload = array();
    $ratemf_rates_preload[] = array(
      "name" => "Agree",
      "postbit" => "Agree",
      "image" => "agree.png",
      "selected_ranks_use" => "",
      "selected_ranks_see" => "",
      "selected_forum_use" => ""
    );
    $ratemf_rates_preload[] = array(
      "name" => "Disagree",
      "postbit" => "Disagree",
      "image" => "disagree.png",
      "selected_ranks_use" => "",
      "selected_ranks_see" => "",
      "selected_forum_use" => ""
    );
    $ratemf_rates_preload[] = array(
      "name" => "Funny",
      "postbit" => "Funny",
      "image" => "funny.png",
      "selected_ranks_use" => "",
      "selected_ranks_see" => "",
      "selected_forum_use" => ""
    );
    $ratemf_rates_preload[] = array(
      "name" => "Dumb",
      "postbit" => "Dumb",
      "image" => "dumb.png",
      "selected_ranks_use" => "",
      "selected_ranks_see" => "",
      "selected_forum_use" => ""
    );


    foreach($ratemf_rates_preload as $key => $rating)
    {
      $insert = array(
        "name" => $db->escape_string($rating['name']),
        "postbit" => $db->escape_string($rating['postbit']),
        "image" => $db->escape_string($rating['image']),
        "selected_ranks_use" => $db->escape_string($rating['selected_ranks_use']),
        "selected_ranks_see" => $db->escape_string($rating['selected_ranks_see']),
        "selected_forum_use" => $db->escape_string($rating['selected_forum_use']),
        "disporder" => $key + 1
      );
      $db->insert_query("ratemf_rates", $insert);
    }

    $query = $db->simple_select("ratemf_rates", "*");
    $rates = array();

    while($result = $db->fetch_array($query)) {
      $rates[$result['disporder']] = $result;
    }

    $cache->update('ratemf_rates', $rates);
  }
}

/**
 * Activate the plugin
 */
function ratemf_activate()
{
  require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

  /**
   * Display the ratings on the postbits
   */
  find_replace_templatesets("postbit", "#".preg_quote('{$post[\'iplogged\']}')."#i", '{$post[\'iplogged\']}{$post[\'ratemf\']}');
  find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'iplogged\']}')."#i", '{$post[\'iplogged\']}{$post[\'ratemf\']}');

  /**
   * Display the top rating on the thread display for OP
   */
  find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$attachment_count}')."#i", '{$thread[\'ratemf\']}{$attachment_count}');

  /**
   * Include all the assets (JS / CSS) on the post & forumdisplay page
   */
  find_replace_templatesets("showthread", "#".preg_quote('{$headerinclude}')."#i", '{$headerinclude}{$ratemf_head}');
  find_replace_templatesets("forumdisplay", "#".preg_quote('{$headerinclude}')."#i", '{$headerinclude}{$ratemf_head}');
}


/**
 * Check if the plugin is installed
 * @return boolean
 */
function ratemf_is_installed()
{
  global $db;

  return $db->table_exists("ratemf_postbit") && $db->table_exists("ratemf_rates");
}

/**
 * Deactivating plugin by just removing the display
 */
function ratemf_deactivate()
{
  require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

  find_replace_templatesets("postbit", "#".preg_quote('{$post[\'ratemf\']}')."#i", '', 0);
  find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'ratemf\']}')."#i", '', 0);

  find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$thread[\'ratemf\']}')."#i", '', 0);

  find_replace_templatesets("showthread", "#".preg_quote('{$ratemf_head}')."#i", '', 0);
  find_replace_templatesets("forumdisplay", "#".preg_quote('{$ratemf_head}')."#i", '', 0);
}

/**
 * Removing the existance of the plugin... :*(
 */
function ratemf_uninstall()
{
  global $db;

  $db->delete_query("settinggroups", "name = 'ratemf'");

  $settings = array(
    "ratemf_disabled_group",
    "ratemf_multirate",
    "ratemf_double_delete",
    "ratemf_ajax",
    "ratemf_ajax_refresh",
    "ratemf_shrink",
    "ratemf_selfrate",
    "ratemf_op_thread_show"
  );
  rebuild_settings();

  $settings = "'" . implode("','", $settings) . "'";
  $db->delete_query("settings", "name IN ({$settings})");

  if($db->table_exists("ratemf_postbit")) { $db->drop_table("ratemf_postbit"); }
  if($db->table_exists("ratemf_rates")) { $db->drop_table("ratemf_rates"); }
}

/**
 * Plugin's Custom stylesheet and javascript
 * @return [void] ratemf custom stuff for head
 */
function ratemf_head()
{
  global $db, $settings, $mybb, $ratemf_head;

  eval("\$ratemf_head = \"\n\n<!-- start: ratemf_head -->\";");

  /**
   * Display Stylesheet from inc/plugins/ratemf
   */
  eval("\$ratemf_head .= \"\n<link type='text/css' rel='stylesheet' href='inc/plugins/ratemf/ratemf.css' />\";");

  /**
   * Set base time for ajax check
   */
  eval("\$ratemf_head .= \"
<script type='text/javascript'>
  var ratemf_time = '". TIME_NOW ."',
  tid = '". $mybb->get_input('tid') ."';

  function ratemf_rate(pid, rid) {
    jQuery.ajax({
      url: '". $mybb->settings['bburl'] ."/xmlhttp.php',
      type: 'post',
      data: {
        plugin: 'ratemf',
        my_post_key: my_post_key,
        type: 'rate',
        pid: pid,
        rid: rid
      },
      beforeSend: function() { jQuery('#post_'+pid).find('.ratemf_rates_list').html('Rating...'); },
      success: function(data) {
        if(typeof data.success == 'string') {
          jQuery('#post_'+pid).find('.ratemf_rates_list').html(data.success);
          ratemf_refresh();
        } else {
          jQuery('#post_'+pid).find('.ratemf_rates_list').html(data.error);
        }
      },
      error: function() {
        jQuery('#post_'+pid).find('.ratemf_rates_list').html('error');
      }
    });
  }

  setInterval(ratemf_refresh, 1000*". $settings['ratemf_ajax_refresh'] .");

  function ratemf_refresh() {
    jQuery.ajax({
      url: '". $mybb->settings['bburl'] ."/xmlhttp.php',
      type: 'post',
      data: {
        plugin: 'ratemf',
        my_post_key: my_post_key,
        type: 'refresh',
        tid: tid,
        timestamp: ratemf_time
      },
      success: function(data) {
        if(typeof data.time == 'number') {
          ratemf_time = data.time;

          delete data.time;
          jQuery.each(data, function(k, value) {

            var rate_post_element = jQuery('#post_'+value.post_id);
            var rate_rating_element = rate_post_element.find('.ratemf_users_rating_rate_id_'+value.rate_id);
            var rate_list_element = rate_post_element.find('.ratemf_users_list_rate_id_'+value.rate_id);

            if(typeof value.del_time == 'string') {
              var remove_rating_count = parseInt(rate_rating_element.find('strong').text(), 10)-1;

              if(remove_rating_count == 0) {
                rate_rating_element.remove();
                rate_list_element.remove();
              } else {
                rate_rating_element = rate_rating_element.find('strong');
                rate_rating_element.html(remove_rating_count);

                rate_list_element.find('.ratemf_users_list_id_'+value.postbit_uid).remove();
                rate_list_element.find('.ratemf_users_list_title').find('strong').html(remove_rating_count);
              }
            } else {
              if(rate_rating_element.length > 0) {
                rate_rating_element = rate_rating_element.find('strong');
                rate_rating_element.html(parseInt(rate_rating_element.text() ,10)+1);

                rate_list_element.find('.ratemf_users_list_title').find('strong').html(parseInt(rate_list_element.find('.ratemf_users_list_title').find('strong').text(),10)+1);
              } else {
                rate_post_element.find('.ratemf_users_rating').prepend(' \\
                  <li title=\\\"'+value.rate_postbit+'\\\" class=\\\"ratemf_users_rating_rate_id_'+value.rate_id+'\\\"> \\
                    <img src=\\\"". $mybb->settings['bburl'] ."/images/rating/'+value.rate_image+'\\\"> x<strong>1</strong> \\
                  </li>');

                rate_post_element.find('.ratemf_users_list_container').prepend(' \\
                  <ul class=\\\"ratemf_users_list ratemf_users_list_rate_id_'+value.rate_id+'\\\"> \\
                    <li title=\\\"'+value.rate_postbit+'\\\" class=\\\"ratemf_users_list_title\\\"> \\
                      <img src=\\\"". $mybb->settings['bburl'] ."/images/rating/'+value.rate_image+'\\\"> x<strong>1</strong> \\
                    </li> \\
                  </ul>');
              }

              rate_post_element.find('.ratemf_users_list_container').find('.ratemf_users_list_rate_id_'+value.rate_id).append(' \\
                <li class=\\\"ratemf_users_list_id_'+value.postbit_uid+'\\\"> \\
                  <a href=\\\"". $mybb->settings['bburl'] ."/member.php?action=profile&uid='+value.postbit_uid+'\\\"> \\
                    '+value.postbit_username+' \\
                  </a> \\
                </li>');
            }
          });
        }
      }
    });

  }
</script>\";");

  eval("\$ratemf_head .= \"\n<!-- end: ratemf_head -->\n\";");
}

/**
 * Show the ratings under postbit
 * @param  array Directly from the hooks
 * @return array Return back the post with plugin stuff inserted
 */
function ratemf_postbit(&$post)
{
  global $db, $settings, $mybb, $cache;

  $post['ratemf'] = '';

  $ratemf_rates = $cache->read('ratemf_rates');

  $rates_list = '';

  /**
   * Display the list of possible ratings for user
   */

  if($mybb->user['uid'] !== 0 && ($settings['ratemf_selfrate'] || $mybb->user['uid'] != $post['uid']))
  {
    $ratemf_rates_reordered = $ratemf_rates;
    usort($ratemf_rates_reordered, function($a, $b) {
      return $a['disporder'] - $b['disporder'];
    });

    foreach($ratemf_rates_reordered as $rates)
    {
      if($rates['del_time'] != null)
      {
        continue;
      }
      $ranks_use = array_filter(explode(",", $rates['selected_ranks_use']));
      $ranks_see = array_filter(explode(",", $rates['selected_ranks_see']));
      $forum_use = array_filter(explode(",", $rates['selected_forum_use']));


      if((count($ranks_use) && in_array($mybb->usergroup['gid'], $ranks_use))
         || (count($ranks_see) && !in_array($mybb->usergroup['gid'], $ranks_see))
         || (count($forum_use) && !in_array($fid, $forum_use)))
      {
        continue;
      }
      $rates_list .= ratemf_html("rates_list_item", array(
        'pid' => $post['pid'],
        'rid' => $rates['id'],
        'image' => $rates['image'],
        'postbit' => $rates['postbit'],
      ));
    }

    $post['ratemf'] = ratemf_html("rates_list_wrapper", $rates_list);
  }

  $query = $db->write_query("
    SELECT
      pbit.rid AS `rate_id`,
      pbit.uid AS `rate_uid`,
      rates.postbit AS `rate_postbit`,
      rates.image AS `rate_image`,
      u.username AS `rate_username`
    FROM " . TABLE_PREFIX . "ratemf_postbit pbit
    LEFT JOIN
      ". TABLE_PREFIX ."users u
      ON
        u.uid = pbit.uid
    LEFT JOIN
      ". TABLE_PREFIX ."ratemf_rates rates
      ON
        rates.id = pbit.rid
    WHERE
      pbit.pid='".$db->escape_string($post['pid'])."'
      AND
      pbit.del_time IS NULL
    ORDER BY pbit.id DESC
  ");

  $users_by_rate_id = array();
  $rating_info_found = array();

  while($result = $db->fetch_array($query))
  {
    if(!isset($users_by_rate_id[$result['rate_id']]))
    {
      $users_by_rate_id[$result['rate_id']] = array();
      $rating_info_found[$result['rate_id']] = array(
        'rate_postbit' => $result['rate_postbit'],
        'rate_image' => $result['rate_image'],
        'rate_id' => $result['rate_id']
      );
    }

    $users_by_rate_id[$result['rate_id']][] = array(
      'rate_uid' => $result['rate_uid'],
      'rate_username' =>$result['rate_username']
    );
  }

  $users_rating = '';
  $users_list = '';

  foreach($users_by_rate_id as $rateId => $users_under_rate_id)
  {

    $rating_info_found[$rateId]['rate_count'] = count($users_under_rate_id);

    $users_list_tmp = '';
    foreach($users_under_rate_id as $listed_user_info)
    {
      $users_list_tmp .= ratemf_html("users_list_sub_item", $listed_user_info);
    }

    $rating_info_found[$rateId]['rate_usernames'] = $users_list_tmp;

    $users_list .= ratemf_html("users_list_item", $rating_info_found[$rateId]);

    $users_rating .= ratemf_html("users_rating_item", $rating_info_found[$rateId]);
  }

  $users_list = ratemf_html("users_list_wrapper", $users_list);

  $post['ratemf'] .= ratemf_html("users_rating_wrapper", array(
   'users_rating' => $users_rating,
   'post_id' => $post['pid'],
   'users_list_wrapper' => $users_list
  ));

  $post['ratemf'] = ratemf_html("wrapper", $post['ratemf']);
  return $post;
}

/**
 * Make it show up on menu of admin page
 * @param  array menu
 * @return array menu
 */
function ratemf_cfg_menu($sub_menu)
{
    $sub_menu[] = array("id" => "ratemf", "title" => "Rate Me a Funny", "link" => "index.php?module=config-ratemf");
    return $sub_menu;
}

/**
 * Show the custom made page for plugin of admin page
 * @param  array actions
 * @return array actions
 */
function ratemf_cfg_page($actions)
{
    $actions['ratemf'] = array('active' => 'ratemf', 'file' => 'ratemf.php');
    return $actions;
}

/**
 * Admin permission for plugin
 * @return string Just a question for permission
 */
function ratemf_cfg_permission($admin_permissions)
{
    $admin_permissions['ratemf'] = "Can use 'Rate Me a Funny' plugin?";
    return $admin_permissions;
}

/**
 * Handles all the ajax requests made for/by users & refreshing
 * @return string json
 */
function ratemf_ajax()
{
  global $mybb, $charset, $db, $cache, $settings;

  if($mybb->get_input('plugin') == 'ratemf') {
    header("Content-type: application/json; charset={$charset}");

    $disabled_group = explode(",", $settings['ratemf_disabled_group']);
    if((count($disabled_group) && in_array($mybb->usergroup['gid'], $disabled_group)))
    {
      echo json_encode(array("error" => "Sorry, this group is not allowed."));
      return;
    }

    $my_post_key = $mybb->get_input('my_post_key');

    if(is_null($my_post_key)
       || !verify_post_check($my_post_key, true))
    {
      echo json_encode(array("error" => "Authentication failure."));
      return;
    }

    switch($mybb->get_input('type'))
    {
      case 'rate':
        if(is_null($mybb->get_input('pid')) || is_null($mybb->get_input('rid')))
        {
          echo json_encode(array("error" => "Missing value."));
          return;
        }
        echo json_encode(ratemf_rate_action($mybb->get_input('pid'), $mybb->get_input('rid')));
        return;
      case 'refresh':
        if(is_null($mybb->get_input('tid')) || is_null($mybb->get_input('timestamp')))
        {
          echo json_encode(array("error" => "Missing value."));
          return;
        }
        echo json_encode(ratemf_refresh_action($mybb->get_input('tid'), $mybb->get_input('timestamp')));
        return;
      default:
        echo json_encode(array("error" => "Invalid type."));
        return;
    }
  }
}

/**
 * Determines if the user is allowed to rate the
 * request post and acts accordingly
 * @param  integer Id of the post that is being rated
 * @param  integer Id of the rating
 * @return void Echos out json text
 */
function ratemf_rate_action($postId, $rateId)
{
  global $db, $settings, $cache, $mybb;

  /**
   * User tried to make up their own rating id...
   */
  $rating = ratemf_find_rates_by('id', $rateId);
  if(!$rating) return array("error" => "Invalid rating.");

  /**
   * Sucks to be able not to use this awesome plugin=
   */
  $ranks_use = array_filter(explode(",", $rating['selected_ranks_use']));
  $ranks_see = array_filter(explode(",", $rating['selected_ranks_see']));

  if((count($ranks_use) && in_array($mybb->usergroup['gid'], $ranks_use))
     || (count($ranks_see) && !in_array($mybb->usergroup['gid'], $ranks_see)))
  {
    return array('error' => 'Invalid rating');
  }

  $query = $db->simple_select("posts", "*", "pid='". $db->escape_string($postId) ."'", array(
    "limit" => 1
  ));

  $post = $db->fetch_array($query);

  if($post == null) return array("error" => "Invalid post.");

  $forum_use = array_filter(explode(",", $rating['selected_forum_use']));
  if(count($forum_use) && !in_array($fid, $forum_use))
  {
    return array('error' => 'Invalid rating');
  }

  /**
   * What a loser
   */
  if(!$settings['ratemf_selfrate']
     && $mybb->user['uid'] == $post['uid']) return array('error' => 'Cannot rate self');

  $query = $db->simple_select("ratemf_postbit", "id, rid",
    "uid='". $db->escape_string($mybb->user['uid']) ."'
     AND
     pid='". $db->escape_string($post['pid']) ."'
     AND
     del_time IS NULL");

  $previous_rate = array();

  /**
   * Check on any existing rating made by
   * this user on this post with settings
   */
  while($result = $db->fetch_array($query))
  {
    if($result != null)
    {
      if($result['rid'] == $rateId)
      {
        if(!$settings['ratemf_double_delete']) return array("error" => "You have already rated.");

        $db->update_query("ratemf_postbit",
          array("del_time" => date("Y-m-d H:i:s", TIME_NOW)),
          "id=".$db->escape_string($result['id']));

        return array("success" => "Removed Rating");

      } else {
        $previous_rate[] = $result;
      }
    }
  }

  /**
   * User might have had multi-rate when the permissions allowed it
   * but now check again just incase and delete all of the ratings
   * that the user had made before inserting a new rating on the post
   */
  if(count($previous_rate) > 0)
  {
    if(!$settings['ratemf_multirate'])
    {
      $db->update_query("ratemf_postbit",
        array("del_time" => date("Y-m-d H:i:s", TIME_NOW)),
        "pid='".$db->escape_string($post['pid'])."'
        and uid='".$db->escape_string($mybb->user['uid'])."'
        and del_time IS NULL");
    }
  }

  $insert = array(
    "uid" => $db->escape_string($mybb->user['uid']), // User Id
    "pid" => $db->escape_string($post['pid']), // Post Id
    "tid" => $db->escape_string($post['tid']), // Thread Id
    "puid" => $db->escape_string($post['uid']), // Post User Id
    "rid" => $db->escape_string($rateId), // Rating Id
    "rate_time" => $db->escape_string(date("Y-m-d H:i:s", TIME_NOW)), // Rating Id
    "ip" => $db->escape_string(get_ip()) // IP Address
  );
  $db->insert_query("ratemf_postbit", $insert);

  return array("success" => "Rated");

}

/**
 * Grabs the ratings from database based on time and returns array back
 * @param  integer Thread ID of the requested refresh
 * @param  integer Unix timestamp in seconds
 * @return array ratings
 */
function ratemf_refresh_action($threadId, $timestamp)
{
  global $db, $settings, $cache;

  $datetime = date("Y-m-d H:i:s", $timestamp+1);
  $result = Array();

  $query = $db->write_query("
    SELECT
      p.pid AS `post_id`,
      pbit.uid AS `postbit_uid`,
      u.username AS `postbit_username`,
      pbit.rid AS `rate_id`,
      rates.postbit AS `rate_postbit`,
      rates.image AS `rate_image`,
      pbit.rate_time AS `rate_time`
    FROM " . TABLE_PREFIX . "ratemf_postbit pbit
    LEFT JOIN
      " . TABLE_PREFIX . "posts p
      ON
        p.tid = pbit.tid
        AND
        p.pid = pbit.pid
    LEFT JOIN
      ". TABLE_PREFIX ."users u
      ON
        u.uid = pbit.uid
    LEFT JOIN
      ". TABLE_PREFIX ."ratemf_rates rates
      ON
        rates.id = pbit.rid
    WHERE
      pbit.tid='".$db->escape_string($threadId)."'
      AND
      pbit.rate_time >= '".$db->escape_string($datetime)."'
  ");
  while($result[] = $db->fetch_array($query));

    $query = $db->write_query("
    SELECT
      p.pid AS `post_id`,
      pbit.uid AS `postbit_uid`,
      u.username AS `postbit_username`,
      pbit.rid AS `rate_id`,
      rates.postbit AS `rate_postbit`,
      rates.image AS `rate_image`,
      pbit.rate_time AS `rate_time`,
      pbit.del_time AS `del_time`
    FROM " . TABLE_PREFIX . "ratemf_postbit pbit
    LEFT JOIN
      " . TABLE_PREFIX . "posts p
      ON
        p.tid = pbit.tid
        AND
        p.pid = pbit.pid
    LEFT JOIN
      ". TABLE_PREFIX ."users u
      ON
        u.uid = pbit.uid
    LEFT JOIN
      ". TABLE_PREFIX ."ratemf_rates rates
      ON
        rates.id = pbit.rid
    WHERE
      pbit.tid='".$db->escape_string($threadId)."'
      AND
      pbit.del_time >= '".$db->escape_string($datetime)."'
  ");
  while($result[] = $db->fetch_array($query));

  $result = array_filter($result);

  if(count($result) > 0 && !is_null($result)) {
    $result['time'] = TIME_NOW;
  }

  return $result;
}

/**
 * Handles all the html for the php-end
 * (this is again repeated for the javascript part above)
 * @param  string title of the html string
 * @param  string/array values to fit into the string requested
 * @return string html
 */
function ratemf_html($type, $value)
{
  global $mybb;

  if(empty($type)) {
    return;
  }

  switch($type) {
    case("wrapper"):
      return '<div class="ratemf_postbit">'.$value.'</div>';

    case("rates_list_wrapper"):
      return '
      <ul class="ratemf_rates_list">
        '. $value. '
      </ul>';

    case("rates_list_item"):
      return '
      <li title="'. $value['postbit'] .'" onClick="javascript:ratemf_rate('.$value['pid'].', '. $value['rid'] .')">
        <img src="'. $mybb->settings['bburl'] .'/images/rating/'.$value['image'].'">
      </li>';

    case("users_rating_wrapper"):
      return '
      <ul class="ratemf_users_rating">
        '. $value['users_rating'] .'
        <li class="ratemf_users_rating_show">
           <input id="ratemf_list_'. $value['post_id'] .'" type="checkbox">
           <label for="ratemf_list_'. $value['post_id'] .'">(list)</label>
           '. $value['users_list_wrapper'] .'
        </li>
      </ul>';

    case("users_rating_item"):
      return '
      <li title="'. $value['rate_postbit'].'" class="ratemf_users_rating_rate_id_'.$value['rate_id'].'">
        <img src="'. $mybb->settings['bburl'] .'/images/rating/'.$value['rate_image'].'"> x<strong>'. $value['rate_count'] .'</strong>
      </li>';

    case("users_list_wrapper"):
      return '
      <div class="ratemf_users_list_container">
        '. $value .'
      </div>';

    case("users_list_item"):
      return '
      <ul class="ratemf_users_list ratemf_users_list_rate_id_'.$value['rate_id'].'">
        <li title="'. $value['rate_postbit'].'" class="ratemf_users_list_title">
          <img src="'. $mybb->settings['bburl'] .'/images/rating/'.$value['rate_image'].'"> x<strong>'. $value['rate_count'] .'</strong>
        </li>
        '. $value['rate_usernames'] .'
      </ul>';

    case("users_list_sub_item"):
      return '
      <li class="ratemf_users_list_id_'. $value['rate_uid'] .'">
        <a href="'. $mybb->settings['bburl'] .'/member.php?action=profile&uid='. $value['rate_uid'] .'">
          '. $value['rate_username'] .'
        </a>
      </li>';

    case("thread_simple_image"):
      return '
      <div class="ratemf_thread_simple_image">
        <img src="'. $mybb->settings['bburl'] .'/images/rating/'.$value['rate_image'].'"> x<strong>'. $value['rate_count'] .'</strong>
      </div>';
  }

}

/**
 * Look for value in ratemf_rates 2D array
 * @param  string index of array to look in
 * @param  string value of the array to compare
 * @return array  array of the rating found
 */
function ratemf_find_rates_by($type, $value)
{
  global $cache;
  $ratemf_rates = $cache->read('ratemf_rates');

  foreach($ratemf_rates as $key => $rates) {
    if($rates[$type] == $value && $rates['del_time'] == null) {
      return $ratemf_rates[$key];
    }
  }

  return false;
}

/**
 * Show the top rating that was rated on the
 * opening post of the thread using settings
 * @return void
 */
function ratemf_thread()
{
  global $thread, $cache, $db, $settings, $mybb;
  $ratemf_rates = $cache->read('ratemf_rates');

  $postId = $thread['firstpost'];
  if(!$settings['ratemf_op_thread_show']) return;

  $query = $db->write_query("
  SELECT
    p.pid AS `post_id`,
    pbit.uid AS `postbit_uid`,
    pbit.rid AS `rate_id`,
    rates.postbit AS `rate_postbit`,
    rates.image AS `rate_image`
  FROM " . TABLE_PREFIX . "ratemf_postbit pbit
  LEFT JOIN
    ". TABLE_PREFIX ."posts p
    ON
      p.pid = pbit.pid
  LEFT JOIN
    ". TABLE_PREFIX ."ratemf_rates rates
    ON
      rates.id = pbit.rid
  WHERE
    pbit.pid='".$db->escape_string($postId)."'
    and pbit.del_time IS NULL
");

  $ratemf_sorted_by_rating = array();

  while($result = $db->fetch_array($query))
  {
    if(!isset($ratemf_sorted_by_rating[$result['rate_id']])) {
      $ratemf_sorted_by_rating[$result['rate_id']] = 0;
    }
    $ratemf_sorted_by_rating[$result['rate_id']]++;
  }

  if(count($ratemf_sorted_by_rating) == 0) return;

  $maxRateValue = max($ratemf_sorted_by_rating);

  if($maxRateValue < $settings['ratemf_op_thread_show']) return;

  $maxRateArrayKey = array_keys($ratemf_sorted_by_rating, $maxRateValue);
  $maxRateImage = ratemf_find_rates_by('id', $maxRateArrayKey[0]);
  $maxRateImage = $maxRateImage['image'];

  $thread['ratemf'] = ratemf_html("thread_simple_image", array(
  'rate_image' => $maxRateImage,
  'rate_count' => $maxRateValue));

}

/**
 * Based on settings, display ratings on user's profile page
 * @return void
 */
function ratemf_profile_view()
{

}
