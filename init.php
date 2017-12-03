<?php

ensure_secure();

function ensure_secure() {
	if ($_SERVER['SERVER_PORT'] == '443') return;
	redirect_to(str_replace('http://', 'https://', $_SERVER['SCRIPT_URI']));
}

function redirect_to($uri) {
	header("HTTP/1.0 301 Moved");
	header("Location: " . $uri);
	exit;
}
