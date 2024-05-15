<?php

return array(
	'imagecache' => array(
		'cache_url' => 'Cache URL (for user to fetch)',
        'url_encode' => 'Encode the URL',
		'post_url' => 'Post URL (for freshRSS to inform)',
		'access_token' => 'Access Token (for freshRSS to inform)',
        'proactive_cache' => 'FreshRSS Proactive Cache Settings',
        'proactive_cache_enabled' => 'Enable proactive cache',
        'proactive_cache_desc' => 'The proactive cache allows FreshRSS to notify cache server to download pictures ' .
            'when new article is fetched. This will improve picture loading speed.',
	),
);
