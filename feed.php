<?php

require 'init.php';
require 'conf.php';
require 'libmicrobot.php';

$username = strtolower($_GET['username']);

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

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8" ?>';

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
                <?php if ($post['media_type']) echo "<br>" . microbot_html_image_link($username, $post); ?>
            ]]></description>
            <?php if ($post['media_type']) { ?>
                <media:content 
                    xmlns:media="http://search.yahoo.com/mrss/" 
                    url="<?php emit(microbot_format_permalink($username, $post['ts'], true)); ?>" 
                    medium="image" 
                    type="<?php emit($post['media_type']); ?>" 
                    width="<?php emit($post['media_w']); ?>" 
                    height="<?php emit($post['media_h']); ?>" />
            <?php } ?>
            <pubDate><?php emit(date('r', $post['ts'])); ?></pubDate>
            <guid isPermalink="true"><?php emit(microbot_format_permalink($username, $post['ts'])); ?></guid>
            <link><?php emit(microbot_format_permalink($username, $post['ts'])); ?></link>
            <author>@<?php emit($username);?></author>
        </item>
        <?php } ?>
    </channel>
</rss>
