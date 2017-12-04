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
	$slack = new Slack($token);

	$user = _get_slack_user_by_username($token, $username);
	$resp = $slack->call('im.list', []);
	if (!$resp['ok']) return false;
	
	foreach ($resp['ims'] as $im) {
		if ($im['user'] === $user['id']) {
			return microbot_get_posts($token, $im['id'], $im['user']);
		}
	}
	
	return false;
}

function _get_slack_user_by_username($token, $username) {
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
		if ($msg['type'] !== 'message') continue; // TODO: add support for images
		if ($msg['user'] !== $source_user) continue;

		$posts[] = [
			'text' => $msg['text'],
			'ts' => intval($msg['ts']),
		];
	}

	return $posts;
}

function microbot_format_text_as_html($text) {
	return preg_replace_callback("/<(https?:\/\/.+)>/", function($matches) {
		$url = $matches[1];
		$url_pretty = rtrim(rtrim(explode("://", $url, 2)[1], "?"), "/");
		return "<a href=\"" . htmlentities($url) . "\">" . htmlentities($url_pretty) . "</a>";
	}, $text);
}

function microbot_format_permalink($username, $ts = NULL) {
	return "https://whimsicalifornia.com/microbot/user/{$username}" . ($ts ? "/{$ts}" : "");
}

function microbot_format_timestamp($ts) {
	return date('F jS, Y \a\t g:i a', $ts);
}

function microbot_get_user_description($token, $username) {
	return _get_slack_user_by_username($token, $username)['profile']['title'];
}

