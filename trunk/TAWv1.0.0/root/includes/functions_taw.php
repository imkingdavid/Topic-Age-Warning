<?php
/**
*
*===================================================================
*
*  Topic Age Warning Install File
*-------------------------------------------------------------------
*	Script info:
* Version:		1.0.0
* Copyright:	(C) 2010 | David
* License:		http://opensource.org/licenses/gpl-2.0.php | GNU Public License v2
* Package:		phpBB3
*
*===================================================================
*
*/

/**
* @ignore
*/
if(!defined('IN_PHPBB'))
{
	exit;
}

class taw
{	
	private $day;
	private $week;
	private $month;
	private $year;
	private $topic_id;
	private $lock;
	private $quickreply;
	function __construct($post_data, $mode = 'posting')
	{
		global $config, $user, $auth;
		// Set class variables with operations and such
		$time = time();
		$this->day = 60 * 60 * 24; // aka 86,400 seconds. Broken into seconds * minutes * hours = 1 day.
		$this->week = $this->day * 7;
		$this->month = $this->day * 30.436875; //30.436875 = average length of a month in days (according to wikipedia)
		$this->year = $this->month * 12; // 12 months in the year
        
		$this->topic_id = $post_data['topic_id'];
		$this->lock = $config['taw_lock'];
		$this->quickreply = $config['taw_quickreply']; // Bool; true means show quickreply when enabled, but display warning. False means don't show autoreply in old topics.
		
		$forum_id = $post_data['forum_id'];
		$check_time = $post_data[ ($config['taw_last_post']) ? 'topic_last_post_time' : 'topic_time' ];
		$current_interval = $time - $check_time;
		$author_exempt = (($post_data['topic_poster'] === $user->data['user_id']) && $config['taw_author_exempt']) ? true : false;
		
		$interval_value = $config['taw_interval'];
		$interval_type = $config['taw_interval_type'];
		$last_post = $config['taw_last_post'];
		
		$this->pretty_interval = $this->compare_dates($check_time, $time);
		$interval = $this->get_interval($interval_type, $interval_value);
		
		$conditional = (!$author_exempt && $interval && !$auth->acl_get('m_', $forum_id) && ($current_interval > $interval));
		
		//Let's do it!
		if($conditional)
		{
			if($mode == 'posting')
			{
				$this->go_posting();
			}
			else
			{
				$this->go_viewtopic();
			}
		}
	}
	
	function get_interval($interval_type = 'd', $interval_value = 0)
	{
		$interval = 0;
		if ($interval_type == 'y') // year
		{
			$interval = $interval_value * $this->year;
		}
		else if ($interval_type == 'm') // month
		{
			$interval = $interval_value * $this->month;
		}
		else // day
		{
			$interval = $interval_value * $this->day;
		}
		return $interval;
	}
	
	// METHOD STOLEN (and adapted) FROM http://php.net/manual/en/ref.datetime.php
	function compare_dates($date1, $date2) 
	{
        // just so you know, the word "levels" is used to refer to the types of time (i.e. year, month, week, day)
		global $user;
		$blocks = array( 
			array('name' => 'YEAR',		'amount' => $this->year), 
			array('name' => 'MONTH',	'amount' => $this->month),
			array('name' => 'WEEK',		'amount' => $this->week), 
			array('name' => 'DAY',		'amount' => $this->day), 
		);
		$diff = abs($date1 - $date2); 
        // @todo: make this dynamic
		$levels = get_config('taw_levels');
        // how specific to be; 1 = "1 year"; 2 = "1 year and 2 months"; 3 = "1 year and 2 months and 1 week"; etc.
        $levels = (empty($levels) || $levels > count($blocks)) ? 2 : $levels; // Note that $levels should not be be more than the value of count($blocks), which at the moment is 4.
        // Levels are omitted if their value is 0. So if there are no years, it will skip years instead of saying "0 years and 1 month"
    	$current_level = 1; // start at level 1; the level is incremented at the end of the loop 
		$result = array(); 
		foreach($blocks as $block) 
		{ 
			if ($current_level > $levels)
			{
				break; // stop the loop
			}
            // if there are 1 or more of a level
			if ($diff / $block['amount'] >= 1) 
			{ 
				$amount = floor($diff / $block['amount']);
				//fix the plurals issue. instead of just adding "s" to the word, add it to the language key
				// so that other languages that don't just add "s" can use their own plural words
                // Instead, just add the S to the language key to access the plural language value
				$result[] = $amount . ' ' . $user->lang($block['name'] . (($amount > 1) ? 'S' : ''));
				$diff -= $amount * $block['amount'];
				$current_level++;
			}
		}
		return strtolower(implode(' ' . $user->lang['AND'] . ' ', $result));
	}
	
	function go_posting()
	{
		global $user, $template, $db;
		$langkey = 'TOPIC_AGE_WARNING';
		if($this->lock) // If they want the topic to be locked, lock it.
		{
			$sql = 'UPDATE ' . TOPICS_TABLE . '
				SET topic_status = ' . ITEM_LOCKED . '
				WHERE topic_id = '  . (int) $this->topic_id . '
					AND topic_moved_id = 0';
			$db->sql_query($sql);
			$langkey .= '_LOCK';
		}
		$message = $user->lang($langkey, $this->pretty_interval);
		if($this->lock)
		{
			trigger_error($message);
		}
		//enable the warning
		$template->assign_vars(array(
			'S_TOPIC_AGE_WARNING'	=> true,
			'TOPIC_AGE_WARNING'		=> $message,
		));
	}
	function go_viewtopic()
	{
		global $user, $template;
		$message = $user->lang('TOPIC_AGE_WARNING', $this->pretty_interval);
		//enable the warning
		$template->assign_vars(array(
			'S_TOPIC_AGE_WARNING'	=> true,
			'TOPIC_AGE_WARNING'		=> $message,
			'S_DISABLE_QR'			=> ($this->quickreply || $this->lock) ? false : true,
		));
	}
}