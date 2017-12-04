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
	echo "No such status.";
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
			<footer><?php echo htmlentities(microbot_format_timestamp($post['ts'])); ?></footer>
		</article>
		<footer>&lt;3 "escheresque" background image from subtlepatterns.com</footer>
	</body>
</html>
