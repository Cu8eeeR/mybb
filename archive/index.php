<?php
/**
 * MyBB 1.2
 * Copyright � 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";
require MYBB_ROOT."inc/functions_post.php";
// Load global language phrases
$lang->load("index");

switch($action)
{
	// Display an announcement.
	case "announcement":
		$announcement['subject'] = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

		// Build the navigation
		add_breadcrumb($announcement['subject']);
		archive_header($announcement['subject'], $announcement['subject'], $mybb->settings['bburl']."/announcement.php?aid={$id}");

		// Format announcement contents.
		$announcement['startdate'] = mydate($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $announcement['startdate']);

		// Show announcement contents.
		echo "<div class=\"post\">\n<div class=\"header\">\n<h2>{$announcement['subject']}</h2>";
		echo "<div class=\"dateline\">{$announcement['startdate']}</div>\n</div>\n<div class=\"message\">{$announcement['message']}</div>\n</div>\n";

		archive_footer();
		break;

	// Display a thread.
	case "thread":
		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

		// Fetch the forum this thread is in
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='".$thread['fid']."' AND active!='no' AND type='f' AND password=''");
		$forum = $db->fetch_array($query);
		if(!$forum['fid'])
		{
			archive_error($lang->error_invalidforum);
		}

		// Check if we have permission to view this thread
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != "yes")
		{
			archive_error_no_permission();
		}
		// Build the navigation
		build_forum_breadcrumb($forum['fid'], 1);
		add_breadcrumb($thread['subject']);

		archive_header($thread['subject'], $thread['subject'], $mybb->settings['bburl']."/showthread.php?tid=$id");

		// Paginate this thread
		$perpage = $mybb->settings['postsperpage'];
		$postcount = intval($thread['replies'])+1;
		$pages = ceil($postcount/$perpage);

		if($page > $pages)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		// Build attachments cache
		$query = $db->simple_select(TABLE_PREFIX."attachments");
		while($attachment = $db->fetch_array($query))
		{
			$acache[$attachment['pid']][$attachment['aid']] = $attachment;
		}

		// Start fetching the posts
		$query = $db->query("
			SELECT u.*, u.username AS userusername, p.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.tid='$id' AND visible='1'
			ORDER BY p.dateline
			LIMIT $start, $perpage
		");
		while($post = $db->fetch_array($query))
		{
			$post['date'] = mydate($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $post['dateline'], "", 0);
			// Parse the message
			$parser_options = array(
				"allow_html" => $forum['allow_html'],
				"allow_mycode" => $forum['allow_mycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"me_username" => $post['userusername']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			$post['message'] = $parser->parse_message($post['message'], $parser_options);

			// Is there an attachment in this post?
			if(is_array($acache[$post['pid']]))
			{
				foreach($acache[$post['pid']] as $aid => $attachment)
				{
					$post['message'] = str_replace("[attachment=$attachment[aid]]", "[<a href=\"".$mybb->settings['bburl']."/attachment.php?aid=$attachment[aid]\">attachment=$attachment[aid]</a>]", $post['message']);
				}
			}

			// Damn thats a lot of parsing, now to determine which username to show..
			if($post['userusername'])
			{
				$post['username'] = "<a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".$post['uid']."\">".$post['userusername']."</a>";
			}

			// Finally show the post
			echo "<div class=\"post\">\n<div class=\"header\">\n<div class=\"author\"><h2>{$post['username']}</h2></div>";
			echo "<div class=\"dateline\">{$post['date']}</div>\n</div>\n<div class=\"message\">{$post['message']}</div>\n</div>\n";
		}
		archive_multipage($postcount, $perpage, $page, "thread-$id");

		archive_footer();
		break;

	// Display a category or a forum.
	case "forum":

		// Check if we have permission to view this forum
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != "yes")
		{
			archive_error_no_permission();
		}

		// Paginate this forum
		$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(tid) AS threads", "fid='{$id}' AND visible='1'");
		$threadcount = $db->fetch_field($query, "threads");

		// Build the navigation
		build_forum_breadcrumb($forum['fid'], 1);

		// No threads and not a category? Error!
		if($threadcount < 1 && $forum['type'] != 'c')
		{
			archive_header($forum['name'], $forum['name'], $mybb->settings['bburl']."/forumdisplay.php?fid={$id}");
			archive_error($lang->error_nothreads);
		}

		// Build the archive header.
		archive_header($forum['name'], $forum['name'], $mybb->settings['bburl']."/forumdisplay.php?fid={$id}");

		$perpage = $mybb->settings['threadsperpage'];
		$pages = ceil($threadcount/$perpage);
		if($page > $pages)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		// Decide what type of listing to show.
		if($forum['type'] == 'f')
		{
			echo "<div class=\"listing\">\n<div class=\"header\"><h2>{$forum['name']}</h2></div>\n";
		}
		elseif($forum['type'] == 'c')
		{
			echo "<div class=\"listing\">\n<div class=\"header\"><h2>{$forum['name']}</h2></div>\n";
		}

		// Show subforums.
		$query = $db->simple_select(TABLE_PREFIX."forums", "COUNT(fid) AS subforums", "pid='{$id}' AND status='1'");
		$subforumcount = $db->fetch_field($query, "subforums");
		if($subforumcount > 0)
		{
			echo "<div class=\"forumlist\">\n";
			echo "<h3>{$lang->subforums}</h3>\n";
			echo "<ol>\n";
			$forums = build_archive_forumbits($forum['fid']);
			echo $forums;
			echo "</ol>\n</div>\n";
		}

		// Get the announcements if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$time = time();
			$query = $db->simple_select(TABLE_PREFIX."announcements", "*", "startdate < '{$time}' AND (enddate > '{$time}' OR enddate=0)");
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"announcementlist\">\n";
				echo "<h3>{$lang->forumbit_announcements}</h3>";
				echo "<ol>\n";
				while($announcement = $db->fetch_array($query))
				{
					echo "<li><a href=\"{$archiveurl}/index.php/announcement-{$announcement['aid']}.html\">{$announcement['subject']}</a></li>";
				}
				echo "</ol>\n</div>\n";
			}

		}

		// Get the stickies if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$options = array(
				'order_by' => 'sticky, lastpost',
				'order_dir' => 'desc',
				'limit_start' => $start,
				'limit' => $perpage
			);
			$query = $db->simple_select(TABLE_PREFIX."threads", "*", "fid='{$id}' AND visible='1' AND sticky='1'", $options);
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"threadlist\">\n";
				echo "<h3>{$lang->forumbit_stickies}</h3>";
				echo "<ol>\n";
				while($sticky = $db->fetch_array($query))
				{
					echo "<li>{$prefix}<a href=\"{$archiveurl}/index.php/thread-{$sticky['tid']}.html\">{$sticky['subject']}</li></a>";
				}
				echo "</ol>\n</div>\n";
			}
		}

		// Get the threads if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$options = array(
				'order_by' => 'sticky, lastpost',
				'order_dir' => 'desc',
				'limit_start' => $start,
				'limit' => $perpage
			);
			$query = $db->simple_select(TABLE_PREFIX."threads", "*", "fid='{$id}' AND visible='1' AND sticky='0'", $options);
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"threadlist\">\n";
				echo "<h3>{$lang->forumbit_threads}</h3>";
				echo "<ol>\n";
				while($thread = $db->fetch_array($query))
				{
					$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
					$prefix = "";
					if($thread['sticky'] == 1)
					{
						$prefix = "<span class=\"threadprefix\">".$lang->archive_sticky."</span> ";
					}
					if($thread['replies'] != 1)
					{
						$lang_reply_text = $lang->archive_replies;
					}
					else
					{
						$lang_reply_text = $lang->archive_reply;
					}
					echo "<li>{$prefix}<a href=\"{$archiveurl}/index.php/thread-{$thread['tid']}.html\">{$thread['subject']}</a>";
					echo "<span class=\"replycount\"> ({$thread['replies']} {$lang_reply_text})</span></li>";
				}
				echo "</ol>\n</div>\n";
			}
		}

		echo "</div>\n";

		archive_multipage($threadcount, $perpage, $page, "forum-$id");
		archive_footer();
		break;

	// Display the board home.
	default:
		// Build our forum listing
		$forums = build_archive_forumbits(0);
		archive_header("", $mybb->settings['bbname'], $mybb->settings['bburl']."/index.php");
		echo "<div class=\"forumlist\">\n<div class=\"header\">{$mybb->settings['bbname']}</div>\n<div class=\"forums\">\n<ul>\n";
		echo $forums;
		echo "\n</ul>\n</div>\n</div>";
		archive_footer();
		break;
}

/**
* Gets a list of forums and possibly subforums.
*
* @param int The parent forum to get the childforums for.
* @return array Array of information regarding the child forums of this parent forum
*/
function build_archive_forumbits($pid=0)
{
	global $db, $forumpermissions, $mybb, $lang, $archiveurl;

	// Sort out the forum cache first.
	static $fcache;
	if(!is_array($fcache))
	{
		// Fetch forums
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "active!='no' AND password=''", array('order_by' =>'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
		$forumpermissions = forum_permissions();
	}

	// Start the process.
	if(is_array($fcache[$pid]))
	{
		foreach($fcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $forumpermissions[$forum['fid']];
				if(($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no") && $forum['active'] != "no")
				{
					if($forum['linkto'])
					{
						$forums .= "<li><a href=\"{$forum['linkto']}\">{$forum['name']}</a>";
					}
					elseif($forum['type'] == "c")
					{
						$forums .= "<li><strong><a href=\"{$archiveurl}/index.php/forum-{$forum['fid']}.html\">{$forum['name']}</a></strong>";
					}
					else
					{
						$forums .= "<li><a href=\"{$archiveurl}/index.php/forum-{$forum['fid']}.html\">{$forum['name']}</a>";
					}
					if($fcache[$forum['fid']])
					{
						$forums .= "\n<ol>\n";
						$forums .= build_archive_forumbits($forum['fid']);
						$forums .= "</ol>\n";
					}
					$forums .= "</li>\n";
				}
			}
		}
	}
	return $forums;
}
?>