#How to Install : Rate Me a Funny

##Download
The download link for the plugin is [MyBB](http://mods.mybb.com/view/rate-me-a-funny).

##Install
Open up the zip file of the plugin and just unzip it in to the mybb root.
HINT : It has all the files and folders like : index.php , admin , archive , jscripts , inc

##Putting up the {$post['ratemf']}
Before you start, go to your Admin Control Panel.
Then goto Templates & Style -> Templates.
Pick the Template you use for the forum then look for "Post Bit Templates" and then select "postbit" and edit it.
Scroll ALL the way down and look for something like this:
<pre>&lt;tr&gt;
    &lt;td class=&quot;trow1 post_buttons {$unapproved_shade}&quot;&gt;
        &lt;div class=&quot;author_buttons float_left&quot;&gt;
            {$post['button_email']}{$post['button_pm']}{$post['button_www']}{$post['button_find']}{$post['button_rep']}
        &lt;/div&gt;
        &lt;div class=&quot;post_management_buttons float_right&quot;&gt;{$post['button_edit']}{$post['button_quickdelete']}{$post['button_quote']}{$post['button_multiquote']}{$post['button_report']}{$post['button_warn']}{$post['button_reply_pm']}{$post['button_replyall_pm']}{$post['button_forward_pm']}{$post['button_delete_pm']}
        &lt;/div&gt;
    &lt;/td&gt;
&lt;/tr&gt;</pre>

Above that add
<pre>&lt;tr&gt;&lt;td class="trow2"&gt;{$post['ratemf']}&lt;/td&gt;&lt;/tr&gt;</pre>

Now click on "Save and Continue to Listing" After doing that, if you screw up, you can always click on "options" under postbit and return back to original.

Now to edit the classic view. Under the same place look for "postbit_classic"
Look for something like
<pre>        {$post['iplogged']}
    &lt;/div&gt;
&lt;/td&gt;&lt;/tr&gt;</pre>

under it add
<pre>&lt;tr&gt;&lt;td class="trow2"&gt;{$post['ratemf']}&lt;/td&gt;&lt;/tr&gt;</pre>

Now, you should be finished!

[For support please go to the MyBB forum!](http://community.mybb.com/thread-116139.html)

[Please Report Bugs!](https://github.com/jung3o/Rate-Me-a-Funny/issues)