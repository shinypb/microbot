<?php

require 'init.php';
require 'conf.php';
require 'libmicrobot.php';

$users = microbot_get_all_users(SLACK_API_TOKEN);
if ($users === FALSE) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Hm, something bad happened.";
	exit;
}

header('content-type: text/html');

?><!DOCTYPE html>
<html>
	<head>
		<title><?php echo htmlentities(MICROBOT_INSTANCE_NAME); ?> microbots</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="icon" href="<?php echo MICROBOT_BASE_URL ?>/favicon.png">
		<link rel="stylesheet" href="<?php echo MICROBOT_BASE_URL ?>/style.css">
	</head>
	<body>
		<article>
			Hello, and welcome to the <strong><?php echo htmlentities(MICROBOT_INSTANCE_NAME); ?></strong> <a href="https://github.com/shinypb/microbot">microbot</a> index.
		</article>

		<?php foreach ($users as $username) { ?>
			<article>
				<a href="<?php echo microbot_format_user_profile_link($username);?>">@<?php echo htmlentities($username);?></a> (<a href="<?php echo microbot_format_user_profile_link($username, true);?>">rss</a>)
			</article>
		<?php } ?>
		<footer>&lt;3 "escheresque" background image from subtlepatterns.com</footer>
	</body>
</html>
