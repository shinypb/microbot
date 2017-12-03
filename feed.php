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

function emit($str) {
	echo htmlspecialchars($str, ENT_XML1, 'UTF-8');
}

header('content-type: application/rss+xml');

$title = "@{$username}'s microbot blog";
$description = microbot_get_user_description(SLACK_API_TOKEN, $username);

?><rss version="2.0">
    <channel>
        <title><?php emit($title); ?></title>
        <description><?php emit($description); ?></description>
        <link><?php emit(microbot_format_permalink($username)); ?></link>
        <?php foreach ($posts as $post) { ?>
        <item>
            <title></title>
            <description><![CDATA[
                <?php echo microbot_format_text_as_html($post['text']); ?>
            ]]></description>
            <pubDate><?php emit(date('r', $post['ts'])); ?></pubDate>
            <guid isPermalink="true"><?php emit(microbot_format_permalink($username, $post['ts'])); ?></guid>
            <link><?php emit(microbot_format_permalink($username, $post['ts'])); ?></link>
            <author>@<?php emit($username);?></author>
        </item>
        <?php } ?>
    </channel>
</rss>