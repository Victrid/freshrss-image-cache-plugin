<?php

class ImageCacheExtension extends Minz_Extension {
	// Defaults
	const CACHE_URL = 'https://example.com/pic?url=';
    const CACHE_POST_URL = 'https://example.com/prepare';
    const CACHE_ACCESS_TOKEN = '';
	const URL_ENCODE = '1';

	public function init() {
		$this->registerHook('entry_before_display',
		                    array($this, 'content_modification_hook'));
        $this->registerHook('entry_before_insert',
		                    array($this, 'image_upload_hook'));
		// Defaults
		$save = false;
		FreshRSS_Context::$user_conf->image_cache_url=html_entity_decode(FreshRSS_Context::$user_conf->image_cache_url);
		if (is_null(FreshRSS_Context::$user_conf->image_cache_url)) {
			FreshRSS_Context::$user_conf->image_cache_url = self::CACHE_URL;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_cache_post_url)) {
			FreshRSS_Context::$user_conf->image_cache_post_url = self::CACHE_POST_URL;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_cache_post_url)) {
			FreshRSS_Context::$user_conf->image_cache_access_token = self::CACHE_ACCESS_TOKEN;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_cache_url_encode)) {
			FreshRSS_Context::$user_conf->image_cache_url_encode = self::URL_ENCODE;
			$save = true;
		}
		if ($save) {
			FreshRSS_Context::$user_conf->save();
		}
	}

	public function handleConfigureAction() {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			FreshRSS_Context::$user_conf->image_cache_url = Minz_Request::param('image_cache_url', self::CACHE_URL);
            FreshRSS_Context::$user_conf->image_cache_post_url = Minz_Request::param('image_cache_post_url', self::CACHE_POST_URL);
            FreshRSS_Context::$user_conf->image_cache_access_token = Minz_Request::param('image_cache_access_token', self::CACHE_ACCESS_TOKEN);
			FreshRSS_Context::$user_conf->image_cache_url_encode = Minz_Request::param('image_cache_url_encode', '');
			FreshRSS_Context::$user_conf->save();
		}
	}
	
	public static function posturl($url,$data){
        $data  = json_encode($data);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json;charset='utf-8'",
			'Content-Length: ' . strlen($data),
			"Accept: application/json")
		);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($curl);
        curl_close($curl);
        return json_decode($output, true); 
    }
	
    public static function post_request($url, $post_url) {
      return self::posturl($post_url, array("access_token" => FreshRSS_Context::$user_conf->image_cache_access_token, "url" => $url));
    }

	public static function getCacheImageUri($url) {
        $url = rawurlencode($url);
		return FreshRSS_Context::$user_conf->image_cache_url . $url;
	}
	
	
    # Used for srcset
	public static function getSrcSetUris($matches) {
		return str_replace($matches[1], self::getCacheImageUri($matches[1]), $matches[0]);
	}
	
	public static function uploadSrcSetUris($matches) {
		return str_replace($matches[1], self::post_request($matches[1], FreshRSS_Context::$user_conf->image_cache_post_url), $matches[0]);
	}
	
	public static function uploadUris($content) {
		if (empty($content)) {
			return $content;
		}
		$doc = new DOMDocument();
		libxml_use_internal_errors(true); // prevent tag soup errors from showing
		$doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		$imgs = $doc->getElementsByTagName('img');
		foreach ($imgs as $img) {
			if ($img->hasAttribute('src')) {
                self::post_request($img->getAttribute('src'), FreshRSS_Context::$user_conf->image_cache_post_url);
			}
			if ($img->hasAttribute('srcset')) {
				$newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/', 'self::uploadSrcSetUris', $img->getAttribute('srcset'));
			}
		}
	}

	public static function swapUris($content) {
		if (empty($content)) {
			return $content;
		}
		$doc = new DOMDocument();
		libxml_use_internal_errors(true); // prevent tag soup errors from showing
		$doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		$imgs = $doc->getElementsByTagName('img');
		foreach ($imgs as $img) {
			if ($img->hasAttribute('src') && 
			         (strrpos(img->getAttribute('src'), "https://" ) || (strrpos(img->getAttribute('src'), "http://" ) ) )
			       )  {
				$newSrc = self::getCacheImageUri($img->getAttribute('src'));
				$img->setAttribute('src', $newSrc);
			}
			if ($img->hasAttribute('srcset') && 
			(strrpos(img->getAttribute('srcset'), "https://" ) || (strrpos(img->getAttribute('srcset'), "http://" ) ) )

			) {
				$newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/', 'self::getSrcSetUris', $img->getAttribute('srcset'));
				$img->setAttribute('srcset', $newSrcSet);
			}
		}
		return $doc->saveHTML();
	}

	public static function content_modification_hook($entry) {
		$entry->_content(
			self::swapUris($entry->content())
		);

		return $entry;
	}
	
	public static function image_upload_hook($entry) {
		self::uploadUris($entry->content());

		return $entry;
	}
}
