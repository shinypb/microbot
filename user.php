<?php

require 'init.php';
require 'conf.php';
require 'libmicrobot.php';

if (!isset($_GET['username'])) {
	header('HTTP/1.1 404 Not Found');
	echo "Missing user?";
	exit;
}

$username = rtrim(strtolower($_GET['username']), '/');

$posts = microbot_get_posts_by_username(SLACK_API_TOKEN, $username);
if ($posts === FALSE) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Hm, something bad happened.";
	exit;
}

header('content-type: text/html');

$user = microbot_get_user(SLACK_API_TOKEN, $username);
$username_escaped = htmlentities($username);
$description_escaped = htmlentities($user['description']);
$image_escaped = htmlentities($user['image']);
$image_h_escaped = htmlentities($user['image_h']);
$image_w_escaped = htmlentities($user['image_w']);

?><!DOCTYPE html>
<html>
	<head>
		<title>@<?php echo htmlentities($username); ?>'s microblog</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="icon" href="<?php echo MICROBOT_BASE_URL ?>/favicon.png">
		<link rel="stylesheet" href="<?php echo MICROBOT_BASE_URL ?>/style.css">
        <?php if ($username === 'shinypb') { ?><link href="https://micro.blog/shinypb" rel="me"><?php } ?>
	</head>
	<body>
		<article class="user_profile">
			<img alt="@<?php echo $username_escaped;?>'s profile image" src="<?php echo $image_escaped;?>" height="<?php echo $image_h_escaped;?>" width="<?php echo $image_w_escaped;?>">
			<h1 class="username">@<?php echo $username_escaped;?>'s timeline</h1>
			<h2 class="description">“<?php echo $description_escaped;?>”</h2>
		</article>

		<?php foreach ($posts as $post) { ?>
			<article>
				<?php echo microbot_format_text_as_html($post['text']); ?>
                <?php if ($post['media_type']) echo microbot_html_image_link($username, $post); ?>
				<footer><a href="<?php echo htmlentities(microbot_format_permalink($username, $post['ts'])); ?>"><?php echo htmlentities(microbot_format_timestamp($post['ts'])); ?></a></footer>
			</article>
		<?php } ?>
		<footer>&lt;3 "escheresque" background image from subtlepatterns.com</footer>
	</body>
</html>
