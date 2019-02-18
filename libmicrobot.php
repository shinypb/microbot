<?php

// A simple object-oriented wrapper around the Slack API
require 'slack.php';

// A mapping from username to Slack member object
$GLOBALS['slack_users_by_username'] = [];

// Where to store temporary files
define(MICROBOT_TMP_DIR, '/home/shinyplasticbag/.tmp');

// How many posts to fetch and display on member timelines
define(MICROBOT_POST_COUNT, 20);

/**
 * Returns the list of all members on this Slack team that have microbot posts.
 * @param string $token - Slack API token.
 * @return string[] - array of usernames
*/
function microbot_get_all_users($token) {
	$slack = new Slack($token);
	$resp = $slack->call('users.list', []);
	if (!$resp['ok']) return false;

	// Build mapping of user id → username
	$users_by_id = [];
	foreach ($resp['members'] as $member) {
		$users_by_id[$member['id']] = $member['profile']['display_name_normalized'];
	}

	// Build list of users with microbot IMs
	$resp = $slack->call('im.list', []);
	if (!$resp['ok']) return false;

	$users = [];
	foreach ($resp['ims'] as $im) {
		if ($im['user'] === 'USLACKBOT') continue;
		$users[] = $users_by_id[$im['user']];
	}

	return $users;
}

/**
 * Returns the MICROBOT_POST_COUNT most recent posts from the given user's microbot timeline.
 * @param string $token - Slack API token
 * @param string $source_channel - channel ID for @microbot user's DM with $source_user
 * @param string $source_user - user ID of user we are getting posts from
 * @return object[] - microbot post objects
 */
function microbot_get_posts($token, $source_channel, $source_user) {
	$slack = new Slack($token);
	$resp = $slack->call('conversations.history', [
		'channel' => $source_channel,
		'limit' => MICROBOT_POST_COUNT,
	]);
	if (!$resp['ok']) return false;

	$posts = [];
	foreach ($resp['messages'] as $msg) {
		if ($msg['type'] !== 'message') continue;
		if ($msg['subtype'] && $msg['subtype'] !== 'file_share') continue;
		if ($msg['user'] !== $source_user) continue;

		$posts[] = microbot_format_msg_as_post($msg);
	}

	return $posts;
}

/**
 * Returns the MICROBOT_POST_COUNT most recent posts for the give user's microbot timeline.
 * @param string $token - Slack API token
 * @param string $username - Name of user to get microbot posts for
 * @return object[] - microbot post objects
*/
function microbot_get_posts_by_username($token, $username) {
	$im = _slack_get_im_by_username($token, $username);
	if (!$im) return false;

	return microbot_get_posts($token, $im['id'], $im['user']);
}

function microbot_get_post_by_username($token, $username, $ts) {
	$im = _slack_get_im_by_username($token, $username);
	if (!$im) return false;

	return microbot_get_post($token, $im['id'], $im['user'], $ts);
}

function microbot_get_post($token, $source_channel, $source_user, $ts) {
    $cache_key = __FUNCTION__ . '-' . sha1(implode('-', [$token, $source_channel, $source_user, $ts]));
    $cache_filename = MICROBOT_TMP_DIR . '/' . $cache_key;
    if (file_exists($cache_filename)) {
        $resp = json_decode(file_get_contents($cache_filename), true);
    } else {
        $slack = new Slack($token);
        $resp = $slack->call('conversations.history', [
            'channel' => $source_channel,
            'oldest' => ($ts - 1),
            'latest' => ($ts + 1)
        ]);
        file_put_contents($cache_filename, json_encode($resp));
    }
	if (!$resp['ok']) return false;

	$posts = [];
	foreach ($resp['messages'] as $msg) {
		if ($msg['user'] !== $source_user) continue;
		if ($msg['type'] !== 'message') continue; // TODO: add support for images
		if (intval($msg['ts']) !== intval($ts)) continue;

		return microbot_format_msg_as_post($msg);
	}

	return $posts;

}

function microbot_format_msg_as_post($msg) {
	$output = [
		'text' => $msg['file'] ? $msg['file']['title'] : $msg['text'],
		'ts' => intval($msg['ts']),
	];

    if ($msg['files'] && count($msg['files']) === 1) {
		$file = $msg['files'][0];
        $output['media_url_private'] = $file['thumb_1024'];
        $output['media_type'] = $file['mimetype'];
        $output['media_h'] = $file['thumb_1024_h'];
        $output['media_w'] = $file['thumb_1024_w'];
    }

    return $output;
}

function microbot_format_image_link($username, $msg) {
    $url = microbot_format_permalink($username, $msg['ts'], true);
    return "<a class=\"image\" href=\"" . htmlentities($url) . "\"><img style=\"max-width: 100%;\" src=\"" . htmlentities($url) . "\" alt=\"" . htmlentities($msg['text']) . "\"></a>";
}

function microbot_format_text_as_html($text) {
	$text = preg_replace_callback("/<(https?:\/\/.+)>/", function($matches) {
		$url = $matches[1];
		$url_pretty = rtrim(rtrim(explode("://", $url, 2)[1], "?"), "/");
		return "<a href=\"" . htmlentities($url) . "\">" . htmlentities($url_pretty) . "</a>";
	}, $text);

    return nl2br(trim($text));
}

function microbot_format_text_as_plain_text($text) {
	$text = preg_replace_callback("/<(https?:\/\/.+)>/", function($matches) {
		$url = $matches[1];
        return $url;
	}, $text);

    return trim($text);
}

function microbot_format_permalink($username, $ts = NULL, $is_media_link = false) {
	return "https://whimsicalifornia.com/microbot/user/{$username}" . ($ts ? "/{$ts}" : "") . ($is_media_link ? "/media.jpg" : "");
}

function microbot_format_timestamp($ts) {
	return date('F jS, Y \a\t g:i a', $ts);
}

function microbot_get_user_description($token, $username) {
	return _slack_get_user_by_username($token, $username)['profile']['title'];
}

function _slack_get_im_by_username($token, $username) {
	$slack = new Slack($token);
	$user = _slack_get_user_by_username($token, $username);
	$resp = $slack->call('im.list', []);
	if (!$resp['ok']) return false;

	foreach ($resp['ims'] as $im) {
		if ($im['user'] === $user['id']) {
			return $im;
		}
	}

	return false;
}

function _slack_get_user_by_username($token, $username) {
	if (isset($GLOBALS['slack_users_by_username'][$username])) {
		return $GLOBALS['slack_users_by_username'][$username];
	}

	$slack = new Slack($token);
	$resp = $slack->call('users.list', []);
	if (!$resp['ok']) return false;

	$user = null;
	foreach($resp['members'] as $member) {
		if ($member['profile']['display_name'] === $username) {
			$GLOBALS['slack_users_by_username'][$username] = $member;
			return $member;
		}
	}

	return false;
}
