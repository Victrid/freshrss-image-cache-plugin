<?php

return array(
	'imagecache' => array(
		'cache_url' => '缓存URL（用于用户获取）',
        'url_encode' => '对URL进行转义',                
		'post_url' => '通知URL（用于建立缓存）',
		'access_token' => '访问token（用于建立缓存）',
        'proactive_cache' => '主动缓存设置',
        'proactive_cache_enabled' => '启用主动缓存',
        'proactive_cache_desc' => '主动缓存允许FreshRSS在获取到新文章时就通知缓存服务器下载图片。这样可以加快看到图片的速度。',
	),
); 
