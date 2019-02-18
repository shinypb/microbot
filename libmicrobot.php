<?php

// A simple object-oriented wrapper around the Slack API
require 'slack.php';

// A mapping from username to Slack member object
$GLOBALS['slack_users_by_username'] = [];

// Where to store temporary files
define('MICROBOT_TMP_DIR', '/home/shinyplasticbag/.tmp');

// How many posts to fetch and display on member timelines
define('MICROBOT_POST_COUNT', 20);

// Base URL to use when generating URLs; do not include a trailing slash
define('MICROBOT_BASE_URL', 'https://whimsicalifornia.com/microbot');

// User-facing name of this microbot instance
define('MICROBOT_INSTANCE_NAME', 'Whimsicalifornia');

// Constants for cache TTLs
define('MICROBOT_TTL_1_MINUTE', 60);
define('MICROBOT_TTL_1_HOUR', 60 * 60);
define('MICROBOT_TTL_1_DAY', 24 * 60 * 60);

/**
 * Returns the list of all members on this Slack team that have microbot posts.
 * @param string $token - Slack API token.
 * @return string[] - array of usernames
 */
function microbot_get_all_users($token) {
    $slack = new Slack($token);
    $resp = _slack_call_api_cached($slack, 'users.list', [], MICROBOT_TTL_1_HOUR);
    if (!$resp['ok']) return false;

    // Build mapping of user id → username
    $users_by_id = [];
    foreach ($resp['members'] as $member) {
        $users_by_id[$member['id']] = $member['profile']['display_name_normalized'];
    }

    // Build list of users with microbot IMs
    $resp = _slack_call_api_cached($slack, 'im.list', [], MICROBOT_TTL_1_HOUR);
    if (!$resp['ok']) return false;

    $users = [];
    foreach ($resp['ims'] as $im) {
        if ($im['user'] === 'USLACKBOT') continue;
        $users[] = $users_by_id[$im['user']];
    }

    return $users;
}

/**
 * Get's the user's self-provided description.
 * @param string $token - Slack API token
 * @param string $username - Name of user to get the description for
 * @return {string}
 */
function microbot_get_user_description($token, $username) {
    return _slack_get_user_by_username($token, $username)['profile']['title'];
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
    $resp = _slack_call_api_cached($slack, 'conversations.history', [
        'channel' => $source_channel,
        'limit' => MICROBOT_POST_COUNT,
    ], MICROBOT_TTL_1_MINUTE);
    if (!$resp['ok']) return false;

    $posts = [];
    foreach ($resp['messages'] as $msg) {
        if ($msg['type'] !== 'message') continue;
        if ($msg['subtype'] && $msg['subtype'] !== 'file_share') continue;
        if ($msg['user'] !== $source_user) continue;

        $posts[] = _microbot_format_slack_message_as_microbot_post($msg);
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

/**
 * Fetches a specific post from the given user's microbot timeline.
 * @param string $token - Slack API token
 * @param string $source_channel - channel ID for @microbot user's DM with $source_user
 * @param string $source_user - user ID of user we are getting posts from
 * @param string $ts - message timestamp
 * @return object - microbot post object, if it exists, or false if an error occurs or the post does not exist
 */
function microbot_get_single_post($token, $source_channel, $source_user, $ts) {
    $slack = new Slack($token);
    $resp = _slack_call_api_cached($slack, 'conversations.history', [
        'channel' => $source_channel,
        'oldest' => ($ts - 1),
        'latest' => ($ts + 1)
    ], MICROBOT_TTL_1_MINUTE);
    if (!$resp['ok']) return null;

    foreach ($resp['messages'] as $msg) {
        if ($msg['user'] !== $source_user) continue;
        if ($msg['type'] !== 'message') continue;
        if (intval($msg['ts']) !== intval($ts)) continue;

        return _microbot_format_slack_message_as_microbot_post($msg);
    }

    return null;
}

/**
 * Fetches a specific post from the given user's microbot timeline.
 * @param string $token - Slack API token
 * @param string $username - Name of user to get microbot post for
 * @param string $ts - message timestamp
 * @return object|falnullse - microbot post object, if it exists, or false if an error occurs or the post does not exist
 */
function microbot_get_single_post_by_username($token, $username, $ts) {
    $im = _slack_get_im_by_username($token, $username);
    if (!$im) return null;

    return microbot_get_single_post($token, $im['id'], $im['user'], $ts);
}

/**
 * Returns the HTML to link to the given post's image, or null if the post does not have an image.
 * @param string $username
 * @param object $post - microbot post object
 * @return string|null
 */
function microbot_html_image_link($username, $post) {
    if (!$post['media_url_private']) return null;

    $is_media_link = true;
    $url = microbot_format_permalink($username, $post['ts'], $is_media_link);
    $url_escaped = htmlentities($url);
    $text_escaped = htmlentities($post['text']);
    $height_escaped = htmlentities($post['media_h']);
    $width_escaped = htmlentities($post['media_w']);

    return <<<EOT
<a class="image" href="{$url_escaped}"><img src="{$url_escaped}" alt="{$text_escaped}" height="${height_escaped}" width="{$width_escaped}"></a>
EOT;
}

/**
 * Formats the given text from a microbot post as HTML, turning newlines into <br> tags and linkifying <Slack URLs>.
 * @param string $text
 * @return string
 */
function microbot_format_text_as_html($text) {
    $text = preg_replace_callback("/<(https?:\/\/.+?)>/", function($matches) {
        $url = $matches[1];
        $url_pretty = rtrim(rtrim(explode("://", $url, 2)[1], "?"), "/");
        $url_escaped = htmlentities($url);
        $url_pretty_escaped = htmlentities($url_pretty);
        return <<<EOT
<a href="{$url_escaped}">{$url_pretty_escaped}</a>
EOT;
    }, $text);

    return nl2br(trim($text));
}

/**
 * Generates a permalink URL for the given user, optionally for a specific post and optionally for that post's media.
 * @param string $username
 * @param string|null $ts - post timestamp, or null to just point to the user's timeline
 * @param boolean|null $is_media_link - if we are generating a link to a specific post, whether we should generate a URL for that posts media
 * @return string
 */
function microbot_format_permalink($username, $ts = NULL, $is_media_link = false) {
    $chunks = [MICROBOT_BASE_URL, "/user/{$username}"];
    if ($ts) {
        $chunks[] = "/{$ts}";
        if ($is_media_link) {
            $chunks[] = "/media.jpg"; // TODO stop hardcoding .jpg; requires changes to .htaccess as well
        }
    }

    return implode('', $chunks);
}

/**
 * Formats a timestamp as a human readable string.
 * @param string $ts
 * @return string
*/
function microbot_format_timestamp($ts) {
    return date('F jS, Y \a\t g:i a', $ts);
}

/**
 * Returns the user profile URL for the given user.
 * @param string $username
 * @param boolean $is_feed_url - whether to generate a URL for their RSS feed, rather than their profile page
 * @return string
*/
function microbot_format_user_profile_link($username, $is_feed_url) {
    $username_escaped = htmlentities($username);
    $suffix = $is_feed_url ? ".xml" : "";
    return MICROBOT_BASE_URL . "/user/{$username_escaped}{$suffix}";
}

/**
 * Returns the microbot user with the given username.
 * @param string $token - Slack API token
 * @param string $username - user to retrieve
 * @return object|null microbot user object
*/
function microbot_get_user($token, $username) {
    $user = _slack_get_user_by_username($token, $username);
    if (!$user) return null;

    return _microbot_format_slack_user_as_microbot_user($user);
}

function _microbot_format_slack_user_as_microbot_user($user) {
    return [
        'name' => $user['name'],
        'description' => $user['profile']['title'],
        'image' => $user['profile']['image_192'],
        'image_w' => 192,
        'image_h' => 192,
    ];
}

/**
 * Re-formats a Slack message object as a microbot post, which is to say, a subset of the
 * original message's fields under different names for the sake of convenience.
 * @param object $msg - Slack message object
 * @return object - microbot post object
 */
function _microbot_format_slack_message_as_microbot_post($msg) {
    $output = [
        'text' => $msg['file'] ? $msg['file']['title'] : $msg['text'],
        'ts' => intval($msg['ts']),
    ];

    if ($msg['files'] && count($msg['files']) === 1 && isset($msg['files'][0]['thumb_1024'])) {
        $file = $msg['files'][0];
        $output['media_url_private'] = $file['thumb_1024'];
        $output['media_type'] = $file['mimetype'];
        $output['media_h'] = $file['thumb_1024_h'];
        $output['media_w'] = $file['thumb_1024_w'];
    }

    return $output;
}

/**
 * Returns the channel ID for microbot's IM with the user with the given name, or null if non exists or an error occurs.
 * @param string $token - Slack API token
 * @param string $username - Username to get IM channel ID for
 * @return string|null
 */
function _slack_get_im_by_username($token, $username) {
    $slack = new Slack($token);
    $user = _slack_get_user_by_username($token, $username);
    $resp = _slack_call_api_cached($slack, 'im.list', []);
    if (!$resp['ok']) return false;

    foreach ($resp['ims'] as $im) {
        if ($im['user'] === $user['id']) {
            return $im;
        }
    }

    return false;
}

/**
 * Calls a Slack API method with the given arguments, caching the result for a certain time period.
 * @param object $slack - Slack API instance
 * @param string $method - Slack method name
 * @param object $args - arguments for method
 * @param int $ttl - number of seconds to cache response for
 * @return object
 */
function _slack_call_api_cached($slack, $method, $args, $ttl = MICROBOT_TTL_1_MINUTE) {
    if (!is_a($slack, 'Slack')) {
        throw new Exception(__FUNCTION__ . " was called with an invalid Slack instance; did you forget to pass one in?");
    }
    $cache_filename = MICROBOT_TMP_DIR . "/slack." . $method . "." . sha1(json_encode($args)) . ".ttl" . $ttl . ".json";
    if (file_exists($cache_filename) && (time() - filemtime($cache_filename)) < $ttl) {
        return json_decode(file_get_contents($cache_filename), true);
    }

    $resp = $slack->call($method, $args);
    file_put_contents($cache_filename, json_encode($resp));
    return $resp;
}

/**
 * Returns the slack member object for the user with the given username.
 * @param string $token - Slack API token
 * @param string $username - Username to get user object for
 * @return object|null
 */
function _slack_get_user_by_username($token, $username) {
    if (isset($GLOBALS['slack_users_by_username'][$username])) {
        return $GLOBALS['slack_users_by_username'][$username];
    }

    $slack = new Slack($token);
    $resp = _slack_call_api_cached($slack, 'users.list', []);
    if (!$resp['ok']) return null;

    $user = null;
    foreach($resp['members'] as $member) {
        $member_username = $member['profile']['display_name'];
        $GLOBALS['slack_users_by_username'][$member_username] = $member;
    }

    if (!isset($GLOBALS['slack_users_by_username'][$username])) {
        $GLOBALS['slack_users_by_username'][$username] = null;
    }
    return $GLOBALS['slack_users_by_username'][$username];
}
