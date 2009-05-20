<?php
/**
*
* @package phpBB3
* @version $Id: viewcalendar.php 666 2008-11-04 07:09:58Z danopia $
* @copyright (c) 2008 Danopia
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
include($phpbb_root_path . 'includes/bbcode.' . $phpEx);

function snipper($text,$length,$tail) {
    $text = trim($text);
    $txtl = strlen($text);
    if($txtl > $length) {
        for($i=1;$text[$length-$i]!=" ";$i++) {
            if($i == $length) {
                return substr($text,0,$length) . $tail;
            }
        }
        $text = substr($text,0,$length-$i+1) . $tail;
    }
    return $text;
}


// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();
$counter = '0';
// Get the input vars (which month?)
$mode = request_var('mode', '');
$month = max(1, min(12, intval(request_var('month', date('m')))));
$year = date('Y');
$year = max($year - 1, min($year + 1, intval(request_var('year', $year))));

// Figure out which calendar (does nothing atm)
switch ($mode)
{
/*	case 'bbcode':
		$l_title = $user->lang['BBCODE_GUIDE'];
	break;*/

	default:
		// Change this as necessary
		$l_title = 'Simple Calendar for phpbb';
	break;
}

/*
 * name: mkdate
 * @param $month The month.
 * @param $day The day.
 * @param $year The year.
 * @return Returns a timestamp of midnight on the specified date.
 */
function mkdate($month, $day, $year)
{
	return mktime(0, 0, 0, $month, $day, $year);
}

$prev_month = mkdate($month - 1, 1, $year);
$this_month = mkdate($month, 1, $year);
$next_month = mkdate($month + 1, 1, $year);

// Used for highlighting the current day.
$curr_day = date('d');
$curr_month = date('m');
$curr_year = date('Y');
$today_full = 'Today is: ' . date('l, F jS, Y');

// Number of days in month
$prev_month_count = date('t', $prev_month);
$this_month_count = date('t', $this_month);
$next_month_count = date('t', $next_month);

// Set up numbers for the previous month
if ($month > 1)
{
	$prev_month_number = $month - 1;
	$prev_month_year = $year;
}
else
{
	$prev_month_number = 12;
	$prev_month_year = $year - 1;
}

// Set up numbers for the next month
if ($month < 12)
{
	$next_month_number = $month + 1;
	$next_month_year = $year;
}
else
{
	$next_month_number = 1;
	$next_month_year = $year + 1;
}

// Which day of the week the month starts on, 1-7
$first_day_dow = date('N', $this_month);

if (($month == $curr_month) && ($year == $curr_year))
{ // It's the current month.
	$today = $curr_day; // Set to the current day of month.
}
else
{
	$today = 0; // Hopefully won't show anything as "today".
}

$day = 1;
$week_count = ceil(($this_month_count + $first_day_dow) / 7);

$sql = $db->sql_build_query('SELECT', array(
	'SELECT'	=> 't.*, f.forum_name, p.post_text, p.bbcode_bitfield, p.bbcode_uid',
	'FROM'		=> array(
		POSTS_TABLE		=> 'p',
		FORUMS_TABLE		=> 'f',
		TOPICS_TABLE		=> 't'
	),
	'LEFT_JOIN'	=> array(),

	'WHERE'		=> 't.forum_id = f.forum_id
		AND t.topic_first_post_id = p.post_id
		AND f.enable_events = 1',

	'ORDER_BY'	=> 't.topic_time DESC',
));
$result = $db->sql_query($sql);

$rawevents = array();
while ($row = $db->sql_fetchrow($result))
{
	list($start_day, $start_month, $start_year) = explode('-', $row['event_start']);
	list($end_day, $end_month, $end_year) = explode('-', $row['event_end']);
	
	$skip = false;
	$skip = (((int)$start_day) == 0) ? true : $skip;
	
	if (((int)$end_day) > 0)
	{
		$skip = ((((int)$start_month) == 0) ^ (((int)$end_month) == 0)) ? true : $skip;
		$skip = ((((int)$start_year) == 0) ^ (((int)$end_year) == 0)) ? true : $skip;
		
		$skip = ((((int)$start_year) > $year) || (((int)$end_year) < $year)) ? true : $skip;
		$skip = ((((int)$start_month) > $month) || (((int)$end_month) < $month)) ? true : $skip;
	}
	else
	{
		$skip = ((((int)$start_year) > 0) && !(((int)$start_year) == $year)) ? true : $skip;
		$skip = ((((int)$start_month) > 0) && !(((int)$start_month) == $month)) ? true : $skip;
	}
	
	if ($skip)
	{
		continue;
	}
	
	// Define the global bbcode bitfield, will be used to load bbcodes
	$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['bbcode_bitfield']);

	// Is a signature attached? Are we going to display it?
	if ($row['enable_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
	{
		$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['user_sig_bbcode_bitfield']);
	}
	
	$rawevents[] = array_merge(array(
		'start_day'		=> $start_day,
		'start_month'	=> $start_month,
		'start_year'	=> $start_year,
		
		'end_day'			=> $end_day,
		'end_month'		=> $end_month,
		'end_year'		=> $end_year,
	), $row);
}
$db->sql_freeresult($result);

// Instantiate BBCode if need be
if ($bbcode_bitfield !== '')
{
	$bbcode = new bbcode(base64_encode($bbcode_bitfield));
}

$events = array();
foreach ($rawevents as $row)
{
	// viewtopic.php is awesome.	
	// Parse the message and subject
	$message = censor_text($row['post_text']);

	// Second parse bbcode here
	if ($row['bbcode_bitfield'])
	{
		$bbcode->bbcode_second_pass($message, $row['bbcode_uid'], $row['bbcode_bitfield']);
	}

	$message = bbcode_nl2br($message);
	$message = smiley_text($message);

	// Replace naughty words such as farty pants
	$row['topic_title'] = censor_text($row['topic_title']);

	if (!isset($events[$row['start_day']]))
	{
		$events[$row['start_day']] = array();
	}
	
	/* PHPBB-SEO www.phpbb-seo.com Advanced SEO rewrite toolkit BEGIN
	$result_topic_id = $row['topic_id'];
	$u_forum_id = $row['forum_id'];

	if ( empty($phpbb_seo->seo_url['topic'][$result_topic_id]) ) {
		$phpbb_seo->seo_url['topic'][$result_topic_id] = $phpbb_seo->format_url($row['topic_title']);
	}
	if ( empty($phpbb_seo->seo_url['forum'][$u_forum_id]) ) {
		$phpbb_seo->seo_url['forum'][$u_forum_id] = $phpbb_seo->set_url($row['forum_name'], $u_forum_id, $phpbb_seo->seo_static['forum']);
	}
	// www.phpBB-SEO.com SEO TOOLKIT END */
	$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$u_forum_id&amp;t=$result_topic_id" . (($u_hilit) ? "&amp;hilit=$u_hilit" : ''));


	for ($i = $row['start_day']; $i <= $row['end_day']; $i++) {
		$events[$i][$row['topic_title']] = array(
			'text'  	=> $message,
			'replies'	=> $row['topic_replies'],
			'link'  	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx?t=" . $row['topic_id'])
			//'link'  	=> $view_topic_url, //www.phpBB-SEO.com SEO TOOLKIT
		);
	}
}

// Create a new row.
$template->assign_block_vars('calendar_row', array());

// Add a box for each day in the previous month.
for ($prev_day = $prev_month_count - $first_day_dow + 1; $prev_day <= $prev_month_count; $prev_day++)
{ // The loop is weird but it works.
	$template->assign_block_vars('calendar_row.box', array(
		'DAY'			=> $prev_day,
		'TYPE'		=> 'prev',
		'SUFFIX'	=> date('S', mkdate(1, $prev_day, 2000)))
	);
}
// Loop once for each row/week.
for ($week = 0; $week < $week_count; $week++)
{
	/*
	 * Create a new row, ONLY if it's not the first week. If it's the
	 * first week, the row was created above.
	 * Also set the offset of the first box in the row, becuase as seen
	 * above ^^ the first row already has some boxes.
	 */
	if ($week > 0)
	{
		$template->assign_block_vars('calendar_row', array());
		$offset = 1;
	}
	else
	{
		$offset = $first_day_dow + 1;
	}
	// Offset has been set above, so the first statement is empty.
	for (; $offset <= 7 && $day <= $this_month_count; $offset++, $day++)
	{
		// Format the box.
		if (isset($events[$day]['']))
		{ // Temporary!
			$class = $events[$day][''];
		}
		elseif (($day == $curr_day) && ($month == $curr_month) && ($year == $curr_year))
		{ // It's the current day - highlight
			$class = 'currentday';
		}
		elseif (($offset == 1) || ($offset == 7))
		{ // It's a weekend, play time!
			$class = 'weekend';
		}
		else
		{ // None of the above.
			$class = 'plain';
		}
		// Add a new box for each day.
		$template->assign_block_vars('calendar_row.box', array(
			'DAY'			=> $day,
			'TYPE'		=> 'current',
			'CLASS'		=> $class,
			'SUFFIX'	=> date('S', mkdate(1, $day, 1)))
		);
		// check if there are events for each day
		if (isset($events[$day]))
		{ // Any events in this day?
			foreach ($events[$day] as $title => $desc) // if ($events[$day] == true)
			{ // Look through events in this day
				if ($title != '')
				{
					// Make sure title is not blank. Blank titles exist as a
					// temporary kludge to set classes.
					// Did we use this title already?
					if ($title == $prev_title) {
						$template->assign_block_vars('calendar_row.box.event', array(
							'TITLE'	=> $title,
							'TITLE_SHORT' => snipper($title, 20, '...'),
							'DESC'	=> $desc['text'],
							'NEW'   => '0',
							'COUNT' => $counter,
							'REPLIES' => $desc['replies'],
							'LINK'	=> $desc['link']));
					} else {
						$template->assign_block_vars('calendar_row.box.event', array(
							'TITLE' => $title,
							'TITLE_SHORT' => snipper($title, 20, '...'),
							'DESC'  => $desc['text'],
							'NEW'		=> '1',
							'COUNT' => $counter,
							'REPLIES' => $desc['replies'],
							'LINK'  => $desc['link']));
					}
					$counter++;
				} // if
				$prev_title = $title;
				unset ($title);
			} // foreach
		} // if
	} // for
} // for


// Add the boxes for the next month.
for ($day = 1; $offset <= 7; $offset++, $day++)
{
	$template->assign_block_vars('calendar_row.box', array(
		'DAY' => $day,
		'TYPE' => 'next',
		'SUFFIX' => date('S', mkdate(1, $day, 1)))
	);
}

// Lets build a page ...
$template->assign_vars(array(

	// Basic language elements
	'L_CALENDAR_TITLE'	=> $l_title,
	'L_BACK_TO_TOP'			=> $user->lang['BACK_TO_TOP'],
	
	// Selected month info, for headers etc.
	'MONTH_YEAR'		=> date('F, Y', $this_month),
	'MONTH_NAME'		=> date('F', $this_month),
	'MONTH_ABBR'		=> date('M', $this_month),
	'YEAR'			=> $year,
	'TODAY'			=> $today_full,
	
	// Links to the months
	'U_PREV_MONTH'		=> append_sid("{$phpbb_root_path}viewcalendar.$phpEx?month={$prev_month_number}&year={$prev_month_year}"),
	'U_NEXT_MONTH'		=> append_sid("{$phpbb_root_path}viewcalendar.$phpEx?month={$next_month_number}&year={$next_month_year}"),
	
	// Info on prev and next month, used in the back/next links and the
	// days that are in the neighboring months
	'PREV_YEAR'		=> $prev_month_year,
	'PREV_MONTH'		=> $prev_month_number,
	'PREV_MONTH_ABBR'	=> date('M', $prev_month), 
	'PREV_MONTH_NAME'	=> date('F', $prev_month), 
	'NEXT_YEAR'		=> $next_month_year,
	'NEXT_MONTH'		=> $next_month_number,
	'NEXT_MONTH_ABBR'	=> date('M', $next_month),
	'NEXT_MONTH_NAME'	=> date('F', $next_month))
);

page_header($l_title);

$template->set_filenames(array(
	'body' => 'calendar_view.html')
);
make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));

page_footer();

?>
