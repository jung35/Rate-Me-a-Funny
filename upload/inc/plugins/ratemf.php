<?php
/**
 * Rate Me a Funny

 * Copyright 2014 Jung Oh

 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at

 ** http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
**/

// Disallow direct access to this file for security reasons
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


/**
 * Rate Me A Funny plugin info
 * @return Array some information about the plugin
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
    "version" => "2.0.0-pre0.1",
    "compatibility" => "18*",
    "guid" => ""
  );
}
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
      "optionscode" => "text",
      "value" => "7,1,5"
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_ajax_refresh",
      "title" => "Ajax refresh",
      "description" => "How long till the data gets refreshed by ajax? (in seconds)",
      "optionscode" => "text",
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
      "name" => "ratemf_shrink",
      "title" => "Shrink Rate List",
      "description" => "How many different rates until the rate erases the names and show the numbers rated and icon? (default 5)",
      "optionscode" => "text",
      "value" => 5
  );

  $ratemf_settings[] = array(
      "name" => "ratemf_selfrate",
      "title" => "Allow Self Rate",
      "description" => "Allow users to rate themselves. (yes = allowed to rate self)",
      "optionscode" => "yesno",
      "value" => 1
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
     * puid =>  User ID of the post that is being rated
     * rid =>   ID of the rating from `ratemf_rates`
     * rate_time =>   Time when the rating was made
     * ip =>    IP of the person raiting
     */
    $db->write_query("
      CREATE TABLE  " . TABLE_PREFIX . "ratemf_postbit (
        `id` SMALLINT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `uid` SMALLINT(5) NOT NULL ,
        `pid` SMALLINT(5) NOT NULL,
        `puid` SMALLINT(5) NOT NULL ,
        `rid` SMALLINT(5) NOT NULL ,
        `rate_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

    $rates = array();
    foreach($ratemf_rates_preload as $rate)
    {
        $rates[$rate['postbit']] = $rate;
        unset($rates[$rate['postbit']]['postbit']);
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
  find_replace_templatesets("postbit", "#".preg_quote('{$post[\'iplogged\']}')."#i", '{$post[\'iplogged\']}{$post[\'ratemf\']}');
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
    "ratemf_selfrate"
  );
  rebuild_settings();

  $settings = "'" . implode("','", $settings) . "'";
  $db->delete_query("settings", "name IN ({$settings})");

  if($db->table_exists("ratemf_postbit")) { $db->drop_table("ratemf_postbit"); }
  if($db->table_exists("ratemf_rates")) { $db->drop_table("ratemf_rates"); }
}

/**
 * Show the ratings under postbit
 * @param  Array $post Directly from the hooks
 * @return Array       Return back the post with plugin stuff inserted
 */
function ratemf_postbit(&$post)
{
  global $db, $settings, $mybb, $cache;

  $ratemf_rates = $cache->read('ratemf_rates');
  $ratemf_postbit_store = $cache->read('ratemf_postbit_store');

  $ratemf_rates_html = '';

  /**
   * Display the list of possible ratings for user
   */
  if($mybb->user['uid'] !== 0 && !$settings['ratemf_selfrate'] && $mybb->user['uid'] != $post['uid'])
  {
    foreach($ratemf_rates as $rates)
    {
      $ratemf_rates_html .= '<li><img src="images/rating/'.$rates['image'].'"></li>';
    }
  }

  $post['ratemf'] = '<div class="ratemf_postbit">'.$post['ratemf'].'</div>';
  return $post;
}
