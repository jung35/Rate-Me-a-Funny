<?php
/**
 * Rate Me a Funny

 * Copyright 2012 Jung Oh

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

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
Hooks
*/
$plugins->add_hook("postbit", "ratemf_postbit");
$plugins->add_hook("postbit_prev", "ratemf_postbit");
$plugins->add_hook("postbit_pm", "ratemf_postbit");
$plugins->add_hook("postbit_announcement", "ratemf_postbit");

$plugins->add_hook("showthread_start","ratemf_style");

$plugins->add_hook("admin_config_menu", "ratemf_cfg_menu");
$plugins->add_hook("admin_config_action_handler", "ratemf_cfg_page");
$plugins->add_hook("admin_config_permissions ", "ratemf_cfg_permission");

$plugins->add_hook("xmlhttp", "ratemf_json_request");


/** 
Rate Me a Funny PLUGIN info
*/
function ratemf_info()
{
    return array(
        "name" => "Rate Me a Funny",
        "description" => "This plugin lets the user rate someone a smile (icon) per postbit.
        <br><b>Please look at <a href='https://gist.github.com/89eb1e77d51136af3c3c'>THIS READ ME</a> so you know what to do after you install it!</b>",
        "website" => "",
        "author" => "Jung Oh",
        "authorsite" => "http://jung3o.com",
        "version" => "1.1.4",
        "compatibility" => "16*",
        "guid" => "f357ab8855f18a4f13973d9dd01b86ca"
    );
}


/** 
Rate Me a Funny PLUGIN install
*/
function ratemf_install()
{
    global $db,$cache;
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

    $ratemf_cfg = array();
    $ratemf_cfg[] = array(
        "name" => "ratemf_disabled_group",
        "title" => "Groups that cannot use this. (UNIVERSAL)",
        "description" => "Type in all the groups that you don't want them using this (Seperated by comma (,))",
        "optionscode" => "text",
        "value" => "7,1,5"
    );
    $ratemf_cfg[] = array(
        "name" => "ratemf_ajax",
        "title" => "Enable/Disable Ajax Update",
        "description" => "Press 'yes' to enable Ajax Updates and 'no' to disable it. (uses JQuery)",
        "optionscode" => "yesno",
        "value" => "1"
    );
    $ratemf_cfg[] = array(
        "name" => "ratemf_ajax_refresh",
        "title" => "Ajax refresh",
        "description" => "How long till the data gets refreshed by ajax? (in seconds)",
        "optionscode" => "text",
        "value" => "5"
    );
    $ratemf_cfg[] = array(
        "name" => "ratemf_multirate",
        "title" => "Multi-Rate",
        "description" => "Allows user to rate multiple things (Yes to enable)",
        "optionscode" => "yesno",
        "value" => 0
    );
    $ratemf_cfg[] = array(
        "name" => "ratemf_double_delete",
        "title" => "Rate delete",
        "description" => "Allows user to rate a post 2 times to delete their rating (Yes to enable)",
        "optionscode" => "yesno",
        "value" => 1
    );

    $settings_group = array(
        "name" => "ratemf",
        "title" => "Rate Me a Funny Settings",
        "description" => "Settings for the Rate Me a Funny plugin.",
        "disporder" => "99",
        "isdefault" => 0
    );
    $db->insert_query("settinggroups", $settings_group);
    $gid = $db->insert_id();

    $i = 1;
    foreach($ratemf_cfg as $setting)
    {
        $insert = array(
            "name" => $db->escape_string($setting['name']),
            "title" => $db->escape_string($setting['title']),
            "description" => $db->escape_string($setting['description']),
            "optionscode" => $db->escape_string($setting['optionscode']),
            "value" => $db->escape_string($setting['value']),
            "disporder" => $i,
            "gid" => intval($gid),
        );
        $db->insert_query("settings", $insert);
        $i++;
    }

    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "posts ADD ratemf TEXT NULL AFTER posthash;");

    find_replace_templatesets("showthread", "#".preg_quote('{$headerinclude}')."#i", '{$headerinclude}{$ratemf_style}');
    find_replace_templatesets("showthread", "#".preg_quote('{$footer}')."#i", '{$footer}{$ratemf_js}');

    rebuild_settings();

    change_admin_permission("config", "ratemf", 1);

    if(!$db->table_exists("ratemf"))
    {
        $db->write_query("
            CREATE TABLE  " . TABLE_PREFIX . "ratemf (
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

        $rating_db = array();
        $rating_db[] = array(
            "name" => "Agree",
            "postbit" => "Agree",
            "image" => "agree.png",
            "selected_ranks_use" => "",
            "selected_ranks_see" => "",
            "selected_forum_use" => ""
        );
        $rating_db[] = array(
            "name" => "Disagree",
            "postbit" => "Disagree",
            "image" => "disagree.png",
            "selected_ranks_use" => "",
            "selected_ranks_see" => "",
            "selected_forum_use" => ""
        );
        $rating_db[] = array(
            "name" => "Funny",
            "postbit" => "Funny",
            "image" => "funny.png",
            "selected_ranks_use" => "",
            "selected_ranks_see" => "",
            "selected_forum_use" => ""
        );
        $rating_db[] = array(
            "name" => "Dumb",
            "postbit" => "Dumb",
            "image" => "dumb.png",
            "selected_ranks_use" => "",
            "selected_ranks_see" => "",
            "selected_forum_use" => ""
        );

        $i = 1;
        foreach($rating_db as $ratings)
        {
            $insert = array(
                "name" => $db->escape_string($ratings['name']),
                "postbit" => $db->escape_string($ratings['postbit']),
                "image" => $db->escape_string($ratings['image']),
                "selected_ranks_use" => $db->escape_string($ratings['selected_ranks_use']),
                "selected_ranks_see" => $db->escape_string($ratings['selected_ranks_see']),
                "selected_forum_use" => $db->escape_string($ratings['selected_forum_use']),
                "disporder" => $i
            );
            $db->insert_query("ratemf", $insert);
            $i++;
        }
    }

    $query = $db->simple_select('ratemf');
    $rates = array();
    while($rate = $db->fetch_array($query))
    {
        $rates[$rate['postbit']] = $rate;
        unset($rates[$rate['postbit']]['postbit']);
    }
    $cache->update('ratemf_rates', $rates);
}


/** 
Rate Me a Funny PLUGIN is_installed
*/
function ratemf_is_installed()
{
    global $db;

    return $db->table_exists("ratemf");
}


/** 
Rate Me a Funny PLUGIN deactivate
*/
function ratemf_deactivate()
{
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    find_replace_templatesets("showthread", "#".preg_quote('{$ratemf_js}')."#i", '', 0);
    find_replace_templatesets("showthread", "#".preg_quote('{$ratemf_style}')."#i", '', 0);
}


/** 
Rate Me a Funny PLUGIN uninstall
*/
function ratemf_uninstall()
{
    global $db;

    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "posts drop ratemf;");

    $db->delete_query("settinggroups", "name = 'ratemf'");

    $settings = array(
        "ratemf_disabled_group",
        "ratemf_multirate",
        "ratemf_double_delete",
        "ratemf_ajax",
        "ratemf_ajax_refresh"
    );
    rebuild_settings();

    $settings = "'" . implode("','", $settings) . "'";
    $db->delete_query("settings", "name IN ({$settings})");

    if($db->table_exists("ratemf"))
    {
        $db->drop_table("ratemf");
    }
}

/**

*/

function ratemf_postbit(&$post)
{
    global $db,$settings,$mybb,$cache;

    $ratemf_yes = $post['ratemf'];
    $post['ratemf'] = '';

    if($ratemf_yes)
    {
        $post['ratemf'] .= ratemf_postbit_rating($post,unserialize($ratemf_yes));
    }

    if($mybb->user['uid'] !== 0)
    {
        $post['ratemf'] .= ratemf_postbit_rate_it($post);
    }

    return $post;
}

/**

*/

function ratemf_postbit_rating($post,$ratemf)
{
    global $db,$settings,$cache;

    $namearr = array();

    $ratemf_q = $cache->read('ratemf_rates');

    foreach($ratemf as $rating)
    {   
        $count = 0;
        $name = array();
        foreach($rating as $uniq => $postbit)
        {
            if($uniq !== 'name')
            {
                $count += 1;
                $name[] = $postbit;
            } else {
                $inception = $ratemf_q[$postbit];
                $rating_stop = 0;

                if($inception['selected_ranks_see'])
                {
                    $inception['selected_ranks_see'] = explode(",",$inception['selected_ranks_see']);
                    if(!in_array($mybb->usergroup['gid'],$inception['selected_ranks_see']))
                    {
                        $rating_stop = 1;
                    }
                }
            }
        }
        if(!$rating_stop)
        {
            if($count)
            {
                $namearr[$rating['name']]['rating'] = $rating['name'];
                $namearr[$rating['name']]['names'] = $name;
                $namearr[$rating['name']]['count'] = $count;
            }
        }
        
    }

    $return_rating_var = '';
    $who_is = '';
    $who_is_arr = array();

    foreach($namearr as $rating_name)
    {
        if($rating_name['count'])
        {
            $rate_name = array();
            $rate_img = array();

            foreach($ratemf_q as $result)
            {
                $rate_name[] = $result['postbit'];
                $rate_img[] = $result['image'];
            }

            if(in_array($rating_name['rating'], $rate_name))
            {
                $get_username = array();
                foreach($rating_name['names'] as $whom)
                {

                    if($username) {
                        $get_username[] = array(get_user($whom), $whom);
                    }
                }
                $who_is_arr[$rating_name['rating']] = $get_username;
            }

            foreach($rate_name as $ratingnameid => $ratingnamename)
            {
                foreach($rate_img as $ratingimgid => $ratingimgname)
                {
                    if($ratingnameid == $ratingimgid && $ratingnamename == $rating_name['rating'])
                    {   
                        $getratingimgurl = $ratingimgname;

                        $get_count = $rating_name['count'];
                        $return_rating_var .= "<span class='rating_name_".$rating_name['rating']."' style='margin-right:10px;'><img style='position: relative;top: 4px;' src='./images/rating/$getratingimgurl'> ".ucfirst($rating_name['rating'])." x <strong>$get_count</strong></span>";

                        $get_rating_var[$rating_name['rating']] = "<img style='position: relative;top: 4px;' src='./images/rating/$getratingimgurl'> ".ucfirst($rating_name['rating'])." x <strong>$get_count</strong>";
                    }
                }
            }
        }
    }
    if($get_count)
    {
        $return_var = "<div class='ratemf'><div class='float_left' id='rating_box_".$post['pid']."'>".$return_rating_var."(<a href='#'>list</a>)</div>";
        $return_var .= "<div class='container' id='rating_box_".$post['pid']."_popup'>";

        foreach($get_rating_var as $get_rating_var_type => $newrating)
        {
            $return_var .= "<div class='declare rating_name_".$get_rating_var_type."'>$newrating
            <ul>";
            foreach($who_is_arr as $rating_type => $ids)
            {
                if($rating_type === $get_rating_var_type)
                {
                    foreach($ids as $yeesernames)
                    {
                        $return_var .= "<li><a href='./member.php?action=profile&uid=".$yeesernames[1]."'>$yeesernames[0]</a></li>";
                    }
                }
            }
            $return_var .= "
            </ul>
            </div>";
        }

            $return_var .= "</div></div></div>
<script type='text/javascript'>
<!--
    new PopupMenu('rating_box_".$post['pid']."');
// -->
</script>";

    }
if(!$return_var) {$return_var = "<div class='ratemf'><div class='float_left' id='rating_box_".$post['pid']."'></div><div class='container' id='rating_box_".$post['pid']."_popup'></div>";
$return_var .= "</div>
<script type='text/javascript'>
<!--
    new PopupMenu('rating_box_".$post['pid']."');
// -->
</script>";}
return $return_var;
}

function ratemf_postbit_rate_it($post)
{
    global $db, $settings, $mybb, $cache;

    $rate_name = '';
    $rate_img = '';

    $ratemf_cache = $cache->read('ratemf_rates');

    foreach($ratemf_cache as $inception)
    {
        $stop = 0;
        $fid = $post['fid'];

        if($inception['selected_ranks_use'])
        {
            $inception['selected_ranks_use'] = explode(",",$inception['selected_ranks_use']);
            if(in_array($mybb->usergroup['gid'],$inception['selected_ranks_use']))
            {
                $stop = 1;
            }
        }

        if($inception['selected_ranks_see'])
        {
            $inception['selected_ranks_see'] = explode(",",$inception['selected_ranks_see']);
            if(!in_array($mybb->usergroup['gid'],$inception['selected_ranks_see']))
            {
                $stop = 1;
            }
        }

        if($inception['selected_forum_use'])
        {
            $inception['selected_forum_use'] = explode(",",$inception['selected_forum_use']);
            if(!in_array($fid,$inception['selected_forum_use']))
            {
                $stop = 1;
            }
        }

        if(!$stop)
        {
            $rate_name[] = $inception['postbit'];
            $rate_img[] = $inception['image'];
        }
    }

    if(count($rate_name) === count($rate_img))
    {
        foreach($rate_name as $rate_id => $rate_display_name)
        {//?tid=".$post['tid']."&ratemf=".$post['pid'].".".$rate_id."
            if(!$settings['ratemf_ajax'])
            {
                $rtn .= "<a class='rating_link_a' href='?tid=".$post['tid']."&ratemf=".$post['pid'].".".$rate_id."'><img src='images/rating/".$rate_img[$rate_id]."' title='$rate_display_name' /></a>";
            } else {
                $rtn .= "<a class='rating_link_a' onclick=\"return rateUSER('".$post['pid']."','".$rate_id."');\" style='cursor:pointer'><img src='images/rating/".$rate_img[$rate_id]."' title='$rate_display_name' /></a>";
            }
        } 
    } else {
        $rtn = 'Rate_name =/= Rate_img';
    }

    $rtn = "<div id='rating_link_".$post['pid']."' class='float_right'>$rtn</div>";

    return $rtn;
}


/**
Rate Me a Funny PLUGIN postbit_style
*/
function ratemf_style() 
{
    global $db, $settings, $mybb, $ratemf_js, $ratemf_style;

    if ($mybb->input['page'] == 0) {
        $mybb->input['page'] = 1;
    }

    eval("\$ratemf_style = \"\n<link type='text/css' rel='stylesheet' href='inc/plugins/ratemf/ratemf.css' />\";");

    eval("\$ratemf_style .= \"\n<script type='text/javascript'>
document.observe('dom:loaded', function() {
    ratingCHECK();
    ratingCHECKuser();
});


function ratingCHECK() {
    data = new Array();
    new Ajax.Request('xmlhttp.php?action=ratemf_r&page=".$mybb->input['page']."&tid=".$mybb->input['tid']."', {
        method:'get',
        onSuccess: function(data) {
            data = data.responseText.evalJSON();
            datakey = Object.keys(data);
            datakey.each(function(id) {
                $('rating_box_'+id).innerHTML = data[id];
            });
        }
    });
    ratingCHECK.delay(".$settings['ratemf_ajax_refresh'].");
}
function ratingCHECKuser() {
    new Ajax.Request('xmlhttp.php?action=ratemf_ru&page=".$mybb->input['page']."&tid=".$mybb->input['tid']."', {
        method:'get',
        onSuccess: function(data) {
            data = data.responseText.evalJSON();
            datakey = Object.keys(data);
            datakey.each(function(id) {
                $('rating_box_'+id+'_popup').innerHTML = data[id];
            });
        }
    });
    ratingCHECKuser.delay(".$settings['ratemf_ajax_refresh'].");
}
function rateUSER(pid,rate) {
    new Ajax.Request('xmlhttp.php?action=ratemf_u&ratemf='+pid+'.'+rate);
    var getoptions = $('rating_link_'+pid).innerHTML;
    $('rating_link_'+pid).innerHTML = '<span class=\'ratemf\'><strong> Rated <3 </strong></span>'+getoptions;
}
</script>\";");
    if($mybb->input['ratemf'])
    {
        ratemf_do_rate();
    }
}

/**
Rate Me a Funny PLUGIN postbit_do_rate
*/
function ratemf_do_rate() 
{
    global $db, $settings, $mybb, $cache;

    if ($mybb->input['page'] == 0) {
        $mybb->input['page'] = 1;
    } 

    $ratemf_cache = $cache->read('ratemf_rates');
    $ratemf = $mybb->input['ratemf'];

    if($ratemf)
    {
        $ratemf_disabled_group = array();
        if($settings['ratemf_disabled_group']) {
            $ratemf_disabled_group = explode(",",$settings['ratemf_disabled_group']);
        }

        if(!in_array($mybb->user['usergroup'],$ratemf_disabled_group))
        {

            $ratemf = explode(".",$ratemf);
            $stop = 0;
            if(count($ratemf) == 2)
            {

                $querys = $db->simple_select("posts","*","pid='".$ratemf[0]."'");
                while($result=$db->fetch_array($querys,"ratemf,tid,pid")){
                    $ratemf_yes = $result['ratemf'];
                    $tid = $result['tid'];
                    $fid = $result['fid'];
                }

                $rate_name = array();

                foreach($ratemf_cache as $ratemf_postbit => $result)
                {
                    $stop_it_plz = 0;
                    if($result['selected_ranks_use'])
                    {
                        $result['selected_ranks_use'] = explode(",",$result['selected_ranks_use']);
                        if(in_array($mybb->usergroup['gid'],$result['selected_ranks_use']))
                        {
                            $stop_it_plz = 1;
                        }
                    }

                    if($result['selected_ranks_see'])
                    {
                        $result['selected_ranks_see'] = explode(",",$result['selected_ranks_see']);
                        if(!in_array($mybb->usergroup['gid'],$result['selected_ranks_see']))
                        {
                            $stop_it_plz = 1;
                        }
                    }

                    if($result['selected_forum_use'])
                    {
                        $result['selected_forum_use'] = explode(",",$result['selected_forum_use']);
                        if(!in_array($fid,$result['selected_forum_use']))
                        {
                            $stop_it_plz = 1;
                        }
                    }

                    if(!$stop_it_plz) 
                    {
                    $rate_name[] = $ratemf_postbit;
                    }
                }

                if($ratemf_yes)
                {
                    $ratemf_arr = unserialize($ratemf_yes);
                    $ratemf_original = unserialize($ratemf_yes);

                    if(!$settings['ratemf_multirate'])
                    {
                        foreach($ratemf_arr as $search_rate_id => $search_rate_name)
                        {
                            if(in_array($mybb->user['uid'],$search_rate_name))
                            {
                                $stop = 1;
                                if($search_rate_id == $rate_name[$ratemf[1]])
                                {
                                    $stop = 0;
                                }
                            }
                        }
                    }

                    if(!$stop)
                    {
                        if($ratemf_original[$rate_name[$ratemf[1]]])
                        {
                            foreach($ratemf_original[$rate_name[$ratemf[1]]] as $this_user => $user)
                            {
                                if($mybb->user['uid'] == $user)
                                {
                                    if($settings['ratemf_double_delete'])
                                    {   
                                        unset($ratemf_arr[$rate_name[$ratemf[1]]][$this_user]);
                                        unset($ratemf_original[$rate_name[$ratemf[1]]][$this_user]);
                                    }
                                    $dontdoit = 1;
                                }
                            }
                        }
                        if(empty($dontdoit))
                        {
                            if(empty($ratemf_arr[$rate_name[$ratemf[1]]]['name']))
                            {
                                $ratemf_arr[$rate_name[$ratemf[1]]]['name'] = $rate_name[$ratemf[1]];
                            }
                            $ratemf_arr[$rate_name[$ratemf[1]]][] = $mybb->user['uid'];
                        }
                    }
                } else {
                    $ratemf_arr = array();
                    $ratemf_arr[$rate_name[$ratemf[1]]]['name'] = $rate_name[$ratemf[1]];
                    $ratemf_arr[$rate_name[$ratemf[1]]][] = $mybb->user['uid'];
                }

                $insert = array(
                    "ratemf" => $db->escape_string(serialize($ratemf_arr))
                );
                $db->update_query("posts", $insert, "pid='".$ratemf[0]."'");
            }
            if(!$settings['ratemf_ajax'])
            {
                echo "<meta HTTP-EQUIV=\"REFRESH\" content=\"0; url=./showthread.php?tid=".$tid."#pid".$ratemf[0]."\">";
            } else {
                echo "Ok.";
            }
        }
    }
}


/**
Rate Me a Funny PLUGIN postbit config menu
*/
function ratemf_cfg_menu($sub_menu) 
{
    $sub_menu[] = array("id" => "ratemf", "title" => "Rate Me a Funny", "link" => "index.php?module=config-ratemf");
    return $sub_menu;
}


/**
Rate Me a Funny PLUGIN postbit config page
*/
function ratemf_cfg_page($actions) 
{
    $actions['ratemf'] = array('active' => 'ratemf', 'file' => 'ratemf.php');
    return $actions;
}


/**
Rate Me a Funny PLUGIN postbit config permission
*/
function ratemf_cfg_permission($admin_permissions) 
{
    $admin_permissions['ratemf'] = "Can use 'Rate Me a Funny' plugin?";
    return $admin_permissions;
}


/**
Rate Me a Funny PLUGIN postbit config permission
*/
function ratemf_json_request()
{
    global $mybb, $charset, $db, $cache;

    header("Content-type: text/html; charset={$charset}");

    $ratemf_disabled_group = array();
    if($settings['ratemf_disabled_group']) {
        $ratemf_disabled_group = explode(",",$settings['ratemf_disabled_group']);
    }

    $ratemf_cache = $cache->read('ratemf_rates');

    $ppp = $mybb->user['ppp'];

    if(!in_array($mybb->usergroup['gid'],$ratemf_disabled_group))
    {
        if ($mybb->input['action'] == 'ratemf_r' && $mybb->input['page'] && $mybb->input['tid'])
        {
            if($ppp == "0")
            {
                $ppp = $mybb->settings['postsperpage'];
            }

            if($mybb->input['page'] == 1)
            {
                $mybb->input['page'] = 0;
            }

            $mybb->input['page'] = ceil($mybb->input['page']/$ppp);

            $querys = $db->simple_select("posts", "*", "tid='".$mybb->input['tid']."' LIMIT ".$mybb->input['page'].",".$ppp);

            while($result=$db->fetch_array($querys))
            {
                if($result['ratemf']) 
                {
                    $result['ratemf'] = unserialize($result['ratemf']);

                    foreach($result['ratemf'] as $rate => $trash)
                    {
                        foreach($ratemf_cache as $ratemf_post => $ratemf_list) {
                            if ($ratemf_post == $rate) {
                                $rate_img = $ratemf_list['image'];
                                $allow_view = $ratemf_list['selected_ranks_see'];
                            }
                        }

                        $stop = 0;

                        if($allow_view)
                        {
                            $allow_view = explode(",",$allow_view);
                            $allow_view[] = "";
                            if(!in_array($mybb->usergroup['gid'], $allow_view))
                            {
                                $stop = 1;
                            }
                        }

                        if(!$stop)
                        {
                            $count = 0;
                            foreach($trash as $type => $user)
                            {
                                if($type !== 'name') {
                                    $count++;
                                }
                            }
                            if($count)
                            {
                                $post[$result['pid']] .= "<img style='position: relative;top: 4px;' src='./images/rating/$rate_img'> ".ucfirst($rate)." x <strong>$count</strong>";
                            }
                        }
                    }
                    if($count)
                    {
                        $post[$result['pid']] .= " (<a href='#'>list</a>)";
                    }
                }
                if(!$post[$result['pid']]) {
                    $post[$result['pid']] = "";
                }
            }
            $post = json_encode($post);
            echo $post;
        } elseif($mybb->input['action'] == 'ratemf_ru' && $mybb->input['page'] && $mybb->input['tid']) {
            if($ppp == "0")
            {
                $ppp = $mybb->settings['postsperpage'];
            }

            if($mybb->input['page'] == 1)
            {
                $mybb->input['page'] = 0;
            }

            $mybb->input['page'] = ceil($mybb->input['page']/$ppp);

            $querys = $db->simple_select("posts", "*", "tid='".$mybb->input['tid']."' LIMIT ".$mybb->input['page'].",".$ppp);
            while($result=$db->fetch_array($querys))
            {
                if($result['ratemf']) 
                {
                    foreach($ratemf_cache as $allrate)
                    {
                        $post[$result['pid']][$allrate['postbit']] = '';
                    }

                    $result['ratemf'] = unserialize($result['ratemf']);

                    foreach($result['ratemf'] as $rate => $trash)
                    {
                        foreach($ratemf_cache as $ratemf_post => $ratemf_list) {
                            if ($ratemf_post == $rate) {
                                $rate_img = $ratemf_list['image'];
                                $allow_view = $ratemf_list['selected_ranks_see'];
                            }
                        }

                        $stop = 0;

                        if($allow_view)
                        {
                            $allow_view = explode(",",$allow_view);
                            $allow_view[] = "";
                            if(!in_array($mybb->usergroup['gid'], $allow_view))
                            {
                                $stop = 1;
                            }
                        }

                        if(!$stop)
                        {
                            $user_namelist = '';
                            $count = 0;
                            foreach($trash as $type => $user)
                            {
                                if($type !== 'name') {
                                    $count++;
                                    $username = get_user($user);
                                    $username = $username['username'];
                                    $user_namelist .= "<li><a href='./member.php?action=profile&amp;uid=".$user."'>".$username."</a></li>";
                                }
                            }
                        }
                    }
                    $post[$result['pid']] = "<div class='declare rating_name_".$rate."'><img style='position: relative;top: 4px;' src='./images/rating/$rate_img'> ".ucfirst($rate)." x <strong>$count</strong><ul>$user_namelist</ul></div>";
                }
            }
            $post = json_encode($post);
            echo $post;
        } elseif($mybb->input['action'] == 'ratemf_u' && $mybb->input['ratemf']) {
            ratemf_do_rate();
        }
    }
}