<?php

require 'init.php';
require 'conf.php';
require 'libmicrobot.php';

$username = strtolower($_GET['username'] ?: 'shinypb');

$posts = microbot_get_posts_by_username(SLACK_API_TOKEN, $username);
if ($posts === FALSE) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Hm, something bad happened.";
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
		<?php foreach ($posts as $post) { ?>
			<article>
				<?php echo microbot_format_text_as_html($post['text']); ?>
				<footer><?php echo htmlentities(microbot_format_timestamp($post['ts'])); ?></footer>
			</article>
		<?php } ?>
		<footer>&lt;3 "escheresque" background image from subtlepatterns.com</footer>
	</body>
</html>
