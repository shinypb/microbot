<?php

require 'init.php';
require 'conf.php';
require 'libmicrobot.php';

$username = strtolower($_GET['username'] ?: 'shinypb');
$ts = intval($_GET['ts']);

if (!$username || !$ts) {
	header('HTTP/1.1 400 Bad Request');
	echo "Bad request.";
	exit;
}

$post = microbot_get_post_by_username(SLACK_API_TOKEN, $username, $ts);
if (!$post) {
	header('HTTP/1.1 404 Not Found');
	echo "No such post.";
	exit;
}

if ($_GET['attachment']) {
    if (!$post['media_url_private']) {
    	header('HTTP/1.1 404 Not Found');
    	echo "No attachment on this post.";
    	exit;
    }

    header('content-type: ' . $post['media_type']);
    echo file_get_contents($post['media_url_private'], false, stream_context_create(array(
        'http' => array(
          'protocol_version' => 1.1,
          'method'           => 'GET',
          'header'           => "Authorization: Bearer " . SLACK_API_TOKEN,
        ),
      )));

    exit;
}

header('content-type: text/html');

?><!DOCTYPE html>
<html>
	<head>
		<title>@<?php echo htmlentities($username); ?>'s microblog</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/microbot/style.css">
	</head>
	<body>
		<article>
			<?php echo microbot_format_text_as_html($post['text']); ?>
            <?php if ($post['media_type']) echo microbot_format_image_link($username, $post); ?>
			<footer><?php echo htmlentities(microbot_format_timestamp($post['ts'])); ?></footer>
		</article>
		<footer>&lt;3 "escheresque" background image from subtlepatterns.com</footer>
	</body>
</html>
