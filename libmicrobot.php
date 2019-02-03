<?php

require 'slack.php';

$GLOBALS['slack_users_by_username'] = [];

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

function microbot_get_posts_by_username($token, $username) {
	$im = _slack_get_im_by_username($token, $username);
	if (!$im) return false;

	return microbot_get_posts($token, $im['id'], $im['user']);
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

function microbot_get_posts($token, $source_channel, $source_user) {
	$slack = new Slack($token);
	$resp = $slack->call('conversations.history', [
		'channel' => $source_channel,
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
// 
function microbot_format_msg_as_post($msg) {
	$output = [
		'text' => $msg['file'] ? $msg['file']['title'] : $msg['text'],
		'ts' => intval($msg['ts']),
	];

    if ($msg['file']) {
        $output['media_url_private'] = $msg['file']['thumb_1024'];
        $output['media_type'] = $msg['file']['mimetype'];
        $output['media_h'] = $msg['file']['thumb_1024_h'];
        $output['media_w'] = $msg['file']['thumb_1024_w'];
    }

    return $output;
}

function microbot_format_image_link($username, $msg) {
    $url = microbot_format_permalink($username, $msg['ts'], true);
    return "<a class=\"image\" href=\"" . htmlentities($url) . "\"><img src=\"" . htmlentities($url) . "\" alt=\"" . htmlentities($msg['text']) . "\"></a>";
}

function microbot_get_post_by_username($token, $username, $ts) {
	$im = _slack_get_im_by_username($token, $username);
	if (!$im) return false;

	return microbot_get_post($token, $im['id'], $im['user'], $ts);
}

function microbot_get_post($token, $source_channel, $source_user, $ts) {
	$slack = new Slack($token);
	$resp = $slack->call('conversations.history', [
		'channel' => $source_channel,
		'oldest' => ($ts - 1),
		'latest' => ($ts + 1)
	]);
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

