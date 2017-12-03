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
		<title>microbot</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/microbot/style.css">
	</head>
	<body>
		<?php foreach ($users as $user) { ?>
			<article>
				<a href="/microbot/user/<?php echo htmlentities($user);?>">@<?php echo htmlentities($user);?></a> (<a href="/microbot/user/<?php echo htmlentities($user);?>.xml">rss</a>)
			</article>
		<?php } ?>
		<footer>&lt;3 "escheresque" background image from subtlepatterns.com</footer>
	</body>
</html>
