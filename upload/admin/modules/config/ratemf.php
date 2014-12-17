<?php
if(!defined("IN_MYBB"))
{
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function ratemf_rates_cache() {
    global $db,$cache;

    $query = $db->simple_select('ratemf_rates');
    $rates = array();
    while($result = $db->fetch_array($query)) {
      $rates[$result['disporder']] = $result;
    }

    $cache->update('ratemf_rates', $rates);
}

$action = $mybb->get_input('action');
$sub_tabs = array();
$sub_tabs['ratemf'] = array(
  'title' => "Home",
  'link' => "index.php?module=config-ratemf",
  'description' => "List of avaliable ratings"
);
$sub_tabs['ratemf_add'] = array(
  'title' => "Make New Ratings",
  'link' => "index.php?module=config-ratemf&amp;action=new",
  'description' => "You can make nifty new ratings here."
);
$page->add_breadcrumb_item("Rate Me a Funny", "index.php?module=config-ratemf");

if(!$action && empty($action))
{

  $page->output_header("Rate Me a Funny");
  $page->output_nav_tabs($sub_tabs, "ratemf");

  $table = new Table;
  $table->construct_header("Icon", array("class" => "align_center","width" => 1));
  $table->construct_header("Name");
  $table->construct_header("Order");
  $table->construct_header("Controls", array("class" => "align_center", "colspan" => 2));

  $query = $db->simple_select("ratemf_rates", "*", "del_time IS NULL", array('order_by' => 'disporder'));

  $form = new Form("index.php?module=config-ratemf&amp;action=disporder", "post", "", 1);

  while($ratemf = $db->fetch_array($query))
  {
    $table->construct_cell("<img src='". $mybb->settings['bburl'] ."/images/rating/".$ratemf['image']."' width=16 height=16 />", array("class" => "align_center"));
    $table->construct_cell($ratemf['name']);
    $table->construct_cell($form->generate_text_box("display[".$ratemf['id']."]",$ratemf['disporder'],array("style"=>"width:20px")),array("class" => "align_center","width" => 1));
    $table->construct_cell("&nbsp;&nbsp;&nbsp;&nbsp;<a href='?module=config-ratemf&amp;action=edit&amp;id=".$ratemf['id']."'>Edit</a>&nbsp;&nbsp;&nbsp;&nbsp;",array("width" => 1));
    $table->construct_cell("&nbsp;&nbsp;&nbsp;<a href='?module=config-ratemf&amp;action=delete&amp;id=".$ratemf['id']."'>Delete</a>&nbsp;&nbsp;&nbsp;",array("width" => 1));
    $table->construct_row();
  }
  $table->output("Rate Me a Funny");
  $buttons[] = $form->generate_submit_button("Update rating order");

  $form->output_submit_wrapper($buttons);
  $form->end();

  $page->output_footer();

} elseif($action == 'new') {

  $forum['multiple'] = 1;

  $page->add_breadcrumb_item("Add New Ratings");
  $page->output_header("Rate Me a Funny : Add New Ratings");
  $page->output_nav_tabs($sub_tabs, "ratemf_add");

  $form = new Form("index.php?module=config-ratemf&amp;action=new_submit", "post", "", 1);
  $form_container = new FormContainer("Make New Ratings");

  $table = new Table;

  $form_container->output_row("Name<em>*</em>", "This is just an identifier", $form->generate_text_box("name"));
  $form_container->output_row("Shown Name<em>*</em>", "What will this be called on postbit?", $form->generate_text_box("postbit"));
  $form_container->output_row("Name of the image<em>*</em>", "Please upload your images to /images/rating", $form->generate_text_box("image"));

  $form_container->output_row("Groups that can't use this", "If left blank, everyone can use it.<br>CTRL to select multiple", $form->generate_group_select("ranking_use[]", 0, $forum));
  $form_container->output_row("Groups that can't see this", "If left blank, everyone can see it.<br>CTRL to select multiple", $form->generate_group_select("ranking_see[]", 0, $forum));

  $form_container->output_row("Forum that uses this", "If left blank, all the forums will use it. Also, if the forum that you choose has a child forum, it will not be transfered to those child forums.<br>CTRL to select multiple", $form->generate_forum_select("forum_use[]", 0,$forum));

  $form_container->end();
  $buttons[] = $form->generate_submit_button("Make New Rating");

  $form->output_submit_wrapper($buttons);
  $form->end();

  $page->output_footer();

} elseif($action == 'disporder') {
  if($mybb->get_input('display'))
  {
    foreach($mybb->get_input('display') as $id => $newdisplay)
    {
      $insert = array(
        "disporder" => $db->escape_string($newdisplay)
      );
      $db->update_query("ratemf_rates", $insert, "id='".$id."'");
    }
    ratemf_rates_cache();
  }
  admin_redirect("index.php?module=config-ratemf");
} elseif($action == 'delete') {
  if($mybb->get_input('id'))
  {
    $query = $db->simple_select("ratemf_rates", "*", "id='".$db->escape_string($mybb->get_input('id'))."'");
    $name = $db->fetch_field($query, "name");
    $page->output_confirm_action("index.php?module=config-ratemf&amp;action=do_delete&amp;id=".$mybb->get_input('id'), "Are you sure you want to delete \"".$name."\"");
  }
} elseif($action == 'do_delete') {
  if($mybb->request_method == "post")
  {
    if(!$mybb->get_input('no')
    {
      if($mybb->get_input('id'))
      {
        $db->update_query("ratemf_rates",
          array("del_time" => date("Y-m-d H:i:s", TIME_NOW)),
          "id=".$db->escape_string($mybb->get_input('id'))
        );
      }
    }
    ratemf_rates_cache();
  }
  admin_redirect("index.php?module=config-ratemf");
} elseif($action == 'new_submit') {
  if($mybb->request_method = "post") {
    if(isset($mybb->get_input('name')) &&
      isset($mybb->get_input('postbit')) &&
      isset($mybb->get_input('image')))
    {
      if(!empty($mybb->get_input('name')) &&
        !empty($mybb->get_input('postbit')) &&
        !empty($mybb->get_input('image')))
      {
        $ranking_use = NULL;
        $ranking_see = NULL;
        $forum_use = NULL;

        if(isset($mybb->get_input('ranking_use')))
        {
          $gid = '';
          foreach($mybb->get_input('ranking_use') as $groups)
          {
            $gid .= ','.$groups;
          }
          $ranking_use = substr($gid,1);
        }

        if(isset($mybb->get_input('ranking_see')))
        {
          $gid = '';
          foreach($mybb->get_input('ranking_see') as $groups)
          {
            $gid .= ','.$groups;
          }
          $ranking_see = substr($gid,1);
        }

        if(isset($mybb->get_input('forum_use')))
        {
          $fid = '';
          foreach($mybb->get_input('forum_use') as $forums)
          {
            $fid .= ','.$forums;
          }
          $forum_use = substr($fid,1);
        }


        $insert = array(
          "name" => $db->escape_string($mybb->get_input('name')),
          "postbit" => $db->escape_string($mybb->get_input('postbit')),
          "image" => $db->escape_string($mybb->get_input('image')),
          "selected_ranks_use" => $db->escape_string($ranking_use),
          "selected_ranks_see" => $db->escape_string($ranking_see),
          "selected_forum_use" => $db->escape_string($forum_use),
          "disporder" => 0
        );
        $db->insert_query("ratemf_rates", $insert);
        ratemf_rates_cache();
      } else {
        admin_redirect("index.php?module=config-ratemf&amp;action=new");
      }
    } else {
      admin_redirect("index.php?module=config-ratemf");
    }
  }
  admin_redirect("index.php?module=config-ratemf");
} elseif($action == 'edit') {
  if($mybb->get_input('id'))
  {
    $query = $db->simple_select("ratemf_rates", "*", "id=".$db->escape_string($mybb->get_input('id')));

    while($result=$db->fetch_array($query))
    {
      $result['selected_ranks_use'] = explode(",",$result['selected_ranks_use']);
      $result['selected_ranks_see'] = explode(",",$result['selected_ranks_see']);
      $result['selected_forum_use'] = explode(",",$result['selected_forum_use']);


      $forum['multiple'] = 1;

      $page->add_breadcrumb_item("Edit Ratings");
      $page->output_header("Rate Me a Funny : Edit Ratings");
      $page->output_nav_tabs($sub_tabs, "ratemf");

      $form = new Form("index.php?module=config-ratemf&amp;action=do_edit&amp;id=".$mybb->get_input('id'), "post", "", 1);
      $form_container = new FormContainer("Make New Changes Ratings");

      $table = new Table;

      $form_container->output_row("Name<em>*</em>", "This is just an identifier", $form->generate_text_box("name",$result['name']));
      $form_container->output_row("Shown Name<em>*</em>", "What will this be called on postbit?", $form->generate_text_box("postbit",$result['postbit']));
      $form_container->output_row("Name of the image<em>*</em>", "Please upload your images to /images/rating", $form->generate_text_box("image",$result['image']));

      $form_container->output_row("Groups that can't use this", "If left blank, everyone can use it.<br>CTRL to select multiple", $form->generate_group_select("ranking_use[]", $result['selected_ranks_use'], $forum));
      $form_container->output_row("Groups that can't see this", "If left blank, everyone can see it.<br>CTRL to select multiple", $form->generate_group_select("ranking_see[]", $result['selected_ranks_see'], $forum));

      $form_container->output_row("Forum that uses this", "If left blank, all the forums will use it. Also, if the forum that you choose has a child forum, it will not be transfered to those child forums.<br>CTRL to select multiple", $form->generate_forum_select("forum_use[]", $result['selected_forum_use'],$forum));

      $form_container->end();
      $buttons[] = $form->generate_submit_button("Submit Changes Rating");

      $form->output_submit_wrapper($buttons);
      $form->end();

      $page->output_footer();
    }
  } else {
  admin_redirect("index.php?module=config-ratemf");
  }
} elseif($action == 'do_edit') {
  if($mybb->get_input('id'))
  {
    if($mybb->get_input(
    {)      if(isset($mybb->get_input('name')) &&
        isset($mybb->get_input('postbit')) &&
        isset($mybb->get_input('image')))
      {
        if(!empty($mybb->get_input('name')) &&
          !empty($mybb->get_input('postbit')) &&
          !empty($mybb->get_input('image')))
        {
          $ranking_use = NULL;
          $ranking_see = NULL;
          $forum_use = NULL;

          if(isset($mybb->get_input('ranking_use')))
          {
            $gid = '';
            foreach($mybb->get_input('ranking_use') as $groups)
            {
              $gid .= ','.$groups;
            }
            $ranking_use = substr($gid,1);
          }

          if(isset($mybb->get_input('ranking_see')))
          {
            $gid = '';
            foreach($mybb->get_input('ranking_see') as $groups)
            {
              $gid .= ','.$groups;
            }
            $ranking_see = substr($gid,1);
          }

          if(isset($mybb->get_input('forum_use')))
          {
            $fid = '';
            foreach($mybb->get_input('forum_use') as $forums)
            {
              $fid .= ','.$forums;
            }
            $forum_use = substr($fid,1);
          }

          $query = $db->simple_select("ratemf_rates", "*", "id='".$db->escape_string($mybb->get_input('id'))."'");
          $disp = $db->fetch_field($query, "disporder");

          $insert = array(
            "name" => $db->escape_string($mybb->get_input('name')),
            "postbit" => $db->escape_string($mybb->get_input('postbit')),
            "image" => $db->escape_string($mybb->get_input('image')),
            "selected_ranks_use" => $db->escape_string($ranking_use),
            "selected_ranks_see" => $db->escape_string($ranking_see),
            "selected_forum_use" => $db->escape_string($forum_use),
            "disporder" => $disp
          );
          $db->update_query("ratemf_rates", $insert,"id=".$db->escape_string($mybb->get_input('id')));
          ratemf_rates_cache();
        } else {
          admin_redirect("index.php?module=config-ratemf&amp;action=new");
        }
      } else {
        admin_redirect("index.php?module=config-ratemf");
      }
    }
  }
  admin_redirect("index.php?module=config-ratemf");
}
?>
