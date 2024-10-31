<?php
/*
Plugin Name: New posts popup
Plugin URI: http://www.matusiak.eu/numerodix/blog/index.php/2007/09/15/new-posts-popup/
Description: Displays new posts/comments in an overlay layer for the duration of the session (15min typically). It's a notification window for new events. Activate with &lt;?php new_posts_popup(); ?&gt;.
Version: 0.3
Author: Martin Matusiak
Licence: GNU General Public License
Author URI: http://www.matusiak.eu/numerodix/blog
*/

$post_count = 3;	// number of posts to show

$cookie_lastvisit_lifetime =  (3600 * 24) * 14;	// set expiry to x days

$session_duration = (60) * 15;		// x minutes

$post_width = 44;	// x characters allowed for post title
$comment_width = 48;	// x chars allowed for comment
$comment_hwidth = (($comment_width - 3) / 2);

$opacity_low = 10;	// fade out opacity
$opacity_high = 90;		// fade in opacity

$mysql_tz = "UNIX_TIMESTAMP(UTC_TIMESTAMP())-UNIX_TIMESTAMP(NOW())";


function insert_opacity_switch($low, $high) {
	$attr_name = "style.opacity";
	$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	if (stristr($user_agent, "msie"))
		$attr_name = "filters.alpha.opacity";
	else { $low = $low/100; $high = $high/100; }
	$sw = " onmouseover='this.$attr_name=$high;' ";
	$sw .= " onmouseout='this.$attr_name=$low;' ";
	return $sw;
}

function get_rounded_corners_box($s, $classes, $attr) {
	$b = "<div class='roundedwindow $classes' $attr>
	<b class='rwtop'><b class='rw1 roundedwindow_bd2'></b><b class='rw2 roundedwindow_bg roundedwindow_bd'></b><b
	class='rw3 roundedwindow_bg roundedwindow_bd'></b><b class='rw4 roundedwindow_bg roundedwindow_bd'></b></b>
	<div class='roundedwindowcontent roundedwindow_bg roundedwindow_bd roundedwindow_fg'> 
	<div class='roundedwindow_inner'>$s</div>
	</div> <b class='rwbottom'><b class='rw4 roundedwindow_bg roundedwindow_bd'></b><b class='rw3 roundedwindow_bg roundedwindow_bd'></b><b
	class='rw2 roundedwindow_bg roundedwindow_bd'></b><b class='rw1 roundedwindow_bd2'></b></b></div>";
	return $b;
}

function human_date($unixtime) {
	return strftime("%A %e/%m", $unixtime);
}

function get_time() {
	// wordpress gmt time
	return current_time("timestamp", $gmt=1);
}

function get_blog_host() {
	$url = parse_url(get_bloginfo('url'));
	return $url['host'];
}

function get_blog_path() {
	$url = parse_url(get_bloginfo('url'));
	return $url['path'];
}

function get_elapsed($post_date_gmt_unix) {
	$t = $post_date_gmt_unix;

	$min = 60; $hour=$min*60; $day=$hour*24; 
	$week=$day*7; $month=$week*4; $year=$month*12;
	
	if ($t > $year) return floor($t/$year)."y";
	else if ($t > $month) return floor($t/$month)."mo";
	else if ($t > $week) return floor($t/$week)."w";
	else if ($t > $day) return floor($t/$day)."d";
	else if ($t > $hour) return floor($t/$hour)."h";
	else if ($t > $min) return floor($t/$min)."min";
	else if ($t > 0) return $t."s";
	
	return $elap;
}

function abbrev($s, $len=48) {
	if (strlen($s) > $len) $s = substr($s, 0, $len) . "..";
	return $s;
}

function escape($s) {
	return htmlspecialchars(strip_tags($s), ENT_QUOTES);
}

function link_line($pre, $title, $class, $title_url, $author, $len) {
	$title_full = abbrev($title, 100);
	if (strlen("$pre $title $author") > $len) {
		if (strlen($author) > ($len/2)) 
			$author = "";
		if (strlen(trim("$pre $title $author")) > $len) {
			$title = abbrev($title, $len - strlen("$pre  $author"));
		}
	}
	$s = "<a href='$title_url' class='$class' title='"
	.escape($title_full)."'>\n\t".escape($title)."</a> ". escape($author);
	return $s;
}

function set_cookies() {
	$user_session = get_cookie_session();
	if (!$user_session) {
		set_cookie_session(get_post_period());
	} else {
		set_cookie_session($user_session);
	}
	set_cookie_lastvisit();
}

function set_cookie_session($value) {
	global $session_duration;
	setcookie("new_posts_popup_session", $value, 
		get_time() + $session_duration, get_blog_path(), get_blog_host());
}

function set_cookie_lastvisit() {
	global $cookie_lastvisit_lifetime;
	setcookie("new_posts_popup_lastvisit", get_time(), 
		get_time() + $cookie_lastvisit_lifetime, get_blog_path(), get_blog_host());
}

// sql injection no thank you
function chkint($input) {
	$val =	(strval(intval($input)) == $input) ? $input : 0;
	return $val;
}

function get_cookie_session() {
	if(isset($_COOKIE["new_posts_popup_session"])) 
		return chkint($_COOKIE["new_posts_popup_session"]);
}

function get_cookie_lastvisit() {
	if(isset($_COOKIE["new_posts_popup_lastvisit"])) 
		return chkint($_COOKIE["new_posts_popup_lastvisit"]);
}

function get_post_period() {
	global $cookie_lastvisit_lifetime;
	
	$period = get_cookie_session();
	if (!$period)
		$period = get_cookie_lastvisit();
	if (!$period)
		$period = get_time() - $cookie_lastvisit_lifetime;
	return $period;
}

function new_posts_popup() {
	global $cookie_lastvisit_lifetime, $post_count,
		$post_width, $comment_width, $comment_hwidth, $opacity_low, $opacity_high;
	$number_of_posts = $post_count;

	// read cookies
	$post_backdate_unix = get_post_period();

	// set cookie to update session and lastvisit
	set_cookies();

	$posts = fetch_new_posts($post_backdate_unix); // get posts 14 days back
	if ($posts) {
		foreach ($posts as $post) {
			if (($number_of_posts) 
				&& (($post->post_date_unix > $post_backdate_unix) 
					|| ($post->comment_content1)))
			{
				$number_of_posts--;
				if ($post->comment_content1) {

					$comment_new_count = "($post->comment_count_new) ";
					$comment_line = "\n\t\t<div class='s'><span class='newcomments'>$comment_new_count</span>";

					$url1 = get_permalink($post->post_id)."#comment-".$post->comment_ID1;
					if (!$post->comment_content2) {
						$comment_line .= link_line($post->comment_count_new,
							$post->comment_content1, "comment", $url1,
							"-$post->comment_author1", $comment_width)."</div>";
					} else {
						$url2 = get_permalink($post->post_id)."#comment-".$post->comment_ID2;
						$comment_line .= link_line($post->comment_count_new,
							$post->comment_content2, "comment", $url2,
							"-$post->comment_author2", $comment_hwidth)."\n\t | ";
						$comment_line .= link_line($post->comment_count_new,
							$post->comment_content1, "comment", $url1, 
							"-$post->comment_author1", $comment_hwidth)."</div>";
					}
				} else {
					if ($post->comment_count) $comment_count = "| $post->comment_count comments ";
					else $comment_count = "";
					$comment_line = "\n\t\t<div class='s'>[ $post->cat_name ";
					$comment_line .= "| ".human_date($post->post_date_unix)." $comment_count]</div>";
				}
				if ($post->post_date_unix > $post_backdate_unix) $read_status = 'unread';
				else $read_status = 'read';
				$url = get_permalink($post->post_id);

				$elapsed = get_elapsed($post->post_age_unix);
				$link = link_line("x", $post->post_title, $read_status, $url, "", 
					$post_width - strlen($elapsed) - 1);
				$post_line = "\t&raquo; $link \n\t<span class='elapsed'>$elapsed</span>\n\t$comment_line";

				$posts_lines .= "\n\t<div class='content'>\n\t$post_line\n\t</div>";
			}
		}
	}
	if ($posts_lines) {
		$cls = "new_posts_popup";
		$attr = insert_opacity_switch($opacity_low, $opacity_high);
		$output = get_rounded_corners_box($posts_lines, $cls, $attr);
		
		echo "$output\n\t";
	}
}

function fetch_new_posts($date_user_unix, $comment_count=2) {
	global $wpdb, $post_count, $mysql_tz;
	
	$fields = array("comment_ID", "comment_author", "comment_content");
	foreach ($fields as $field) {
		for ($i = 0; $i < $comment_count; $i++) {
			$cm = get_fetch_a_comment_field($date_user_unix, "p.ID", $field, $i);
			$subs .= " ($cm) as $field".($i+1).",";
		}
	}
	$subs .= " GREATEST((".get_fetch_a_comment_field($date_user_unix,
		"p.ID", "comment_date_gmt")."), p.post_date_gmt) as datesort, ";
		
	$subs .= " (".get_fetch_comment_count_new($date_user_unix, "p.ID").") as comment_count_new";
	
	$sql = "SELECT p.ID as post_id, p.post_title,";
	$sql .=  " p.comment_count, t.name as cat_name,";
	$sql .=  " p.post_date_gmt as post_date_mysql,";
	$sql .=  " (UNIX_TIMESTAMP(p.post_date_gmt) - ($mysql_tz)) as post_date_unix,";
	$sql .=  " (UNIX_TIMESTAMP(UTC_TIMESTAMP()) - UNIX_TIMESTAMP(p.post_date_gmt)) as post_age_unix,";
	$sql .= $subs;
    $sql .= " FROM  $wpdb->posts as p, $wpdb->term_relationships as rel,";
	$sql .=  " $wpdb->term_taxonomy as tax, $wpdb->terms as t";
    $sql .= " WHERE p.ID = rel.object_id";
	$sql .=  " AND rel.term_taxonomy_id = tax.term_taxonomy_id";
	$sql .=  " AND tax.term_id = t.term_id";
	$sql .=  " AND tax.taxonomy = 'category'";
	$sql .= "  AND p.post_status = 'publish'";
	$sql .= " ORDER BY datesort DESC";
	$sql .= " LIMIT $post_count";
//	echo "<!-- $sql -->"; return;
	return $wpdb->get_results($sql);
}

function get_fetch_comment_count_new($date_unix, $post_id) {
	global $wpdb, $mysql_tz;
	$sql = "SELECT COUNT(co.comment_ID)";
	$sql .= " FROM $wpdb->comments as co";
	$sql .= " WHERE co.comment_approved = '1'";
	// try to avoid local pingbacks, they are not very interesting
	$sql .=  " AND co.comment_author NOT LIKE '%".get_bloginfo('title')."%'";
	$sql .=  " AND UNIX_TIMESTAMP(co.comment_date_gmt) - ($mysql_tz) > '$date_unix'";
	$sql .=  " AND co.comment_post_ID = $post_id";
	return $sql;
}

function get_fetch_a_comment_field($date_unix, $post_id, $field, $offset=0) {
	global $wpdb, $mysql_tz;
	$sql = "SELECT co.$field";
	$sql .= " FROM $wpdb->comments as co";
	$sql .= " WHERE co.comment_approved = '1'";
	// try to avoid local pingbacks, they are not very interesting
	$sql .=  " AND co.comment_author NOT LIKE '%".get_bloginfo('title')."%'";
	$sql .=  " AND UNIX_TIMESTAMP(co.comment_date_gmt) - ($mysql_tz) > '$date_unix'";
	$sql .=  " AND co.comment_post_ID = $post_id";
	$sql .= " ORDER BY co.comment_date_gmt ASC"; 
	$sql .= " LIMIT $offset,1";
	return $sql;
}

?>
