<?php
declare(strict_types=1);
final class ImageCacheExtension extends Minz_Extension
{
    // Defaults
    private const CACHE_URL = 'https://wsrv.nl/?url=';
    private const CACHE_POST_URL = 'https://example.com/prepare';
    private const CACHE_ACCESS_TOKEN = '';
    private const URL_ENCODE = '1';
    private const CACHE_POST_ENABLED = '';

    #[\Override]
    public function init(): void
    {
        if (!FreshRSS_Context::hasSystemConf()) {
            throw new FreshRSS_Context_Exception('System configuration not initialised!');
        }
        $this->registerHook('entry_before_display', [self::class, 'content_modification_hook']);
        $this->registerHook('entry_before_insert', [self::class, 'image_upload_hook']);
        // Defaults
        $save = false;
        if (is_null(FreshRSS_Context::userConf()->image_cache_url)) {
            FreshRSS_Context::userConf()->image_cache_url = self::CACHE_URL;
            $save = true;
        }
        if (is_null(FreshRSS_Context::userConf()->image_cache_post_url)) {
            FreshRSS_Context::userConf()->image_cache_post_url = self::CACHE_POST_URL;
            $save = true;
        }
        if (is_null(FreshRSS_Context::userConf()->image_cache_access_token)) {
            FreshRSS_Context::userConf()->image_cache_access_token = self::CACHE_ACCESS_TOKEN;
            $save = true;
        }
        if (is_null(FreshRSS_Context::userConf()->image_cache_post_enabled)) {
            FreshRSS_Context::userConf()->image_cache_post_enabled = self::CACHE_POST_ENABLED;
            $save = true;
        }
        if ($save) {
            FreshRSS_Context::userConf()->save();
        }
    }

    #[\Override]
    public function handleConfigureAction(): void
    {
        $this->registerTranslates();
        if (Minz_Request::isPost()) {
            FreshRSS_Context::userConf()->image_cache_url = Minz_Request::paramString('image_cache_url');
            FreshRSS_Context::userConf()->image_cache_post_url = Minz_Request::paramString('image_cache_post_url');
            FreshRSS_Context::userConf()->image_cache_access_token = Minz_Request::paramString('image_cache_access_token');
            FreshRSS_Context::userConf()->image_cache_post_enabled = Minz_Request::paramString('image_cache_post_enabled');
            FreshRSS_Context::userConf()->save();
        }
    }

    public static function curlPostRequest(string $url, array $data): bool
    {
        $data = json_encode($data);
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
        if ($output === false) {
            return false;
        }
        return true;
    }

    public static function is_remote_url(string $url): bool
    {
        return strcmp(substr(strtolower($url), 0, 5), "data:") != 0;
    }

    public static function send_proactive_cache_request(string $url): bool
    {
        if (FreshRSS_Context::userConf()->image_cache_post_enabled && self::is_remote_url($url)) {
            $post_url = FreshRSS_Context::userConf()->image_cache_post_url;
            return self::curlPostRequest($post_url, array("access_token" => FreshRSS_Context::userConf()->image_cache_access_token, "url" => $url));
        }
        return false;
    }

    public static function getCacheImageUri(string $url): string
    {
        if (!self::is_remote_url($url)) {
            return $url;
        }
        $url = rawurlencode($url);
        return FreshRSS_Context::userConf()->image_cache_url . $url;
    }

    /**
     * 仅缓存资源（发送缓存请求），不替换 URL
     */
    public static function cache_images(string $content): string
    {
        if (empty($content)) {
            return $content;
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $encoding = mb_detect_encoding($content);
        $doc->loadHTML('<!DOCTYPE html><meta charset="'.$encoding.'">'.$content);

        // 处理 <img> 标签
        $imgs = $doc->getElementsByTagName('img');
        foreach ($imgs as $img) {
            if ($img->hasAttribute('src')) {
                self::send_proactive_cache_request($img->getAttribute('src'));
            }
            if ($img->hasAttribute('srcset')) {
                preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/',
                    function (array $matches): string {
                        self::send_proactive_cache_request($matches[1]);
                        return '';
                    },
                    $img->getAttribute('srcset'));
            }
            // 处理 Live Photo
            if ($img->hasAttribute('data-livephoto')) {
                self::send_proactive_cache_request($img->getAttribute('data-livephoto'));
            }
        }

        // 处理 <video> 标签
        $videos = $doc->getElementsByTagName('video');
        foreach ($videos as $video) {
            if ($video->hasAttribute('poster')) {
                self::send_proactive_cache_request($video->getAttribute('poster'));
            }
            if ($video->hasAttribute('src')) {
                self::send_proactive_cache_request($video->getAttribute('src'));
            }
            $sources = $video->getElementsByTagName('source');
            foreach ($sources as $source) {
                if ($source->hasAttribute('src')) {
                    self::send_proactive_cache_request($source->getAttribute('src'));
                }
            }
        }

        return $content;
    }

    public static function cache_thumbnail(array $thumbnail): array
    {
        if (empty($thumbnail['url'])) {
            return $thumbnail;
        }
        self::send_proactive_cache_request($thumbnail['url']);
        return $thumbnail;
    }

    /**
     * 仅替换 URL，不发送缓存请求
     */
    public static function swapUris(string $content): string
    {
        if (empty($content)) {
            return $content;
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $encoding = mb_detect_encoding($content);
        $doc->loadHTML('<!DOCTYPE html><meta charset="'.$encoding.'">'.$content);

        // 处理 <img> 标签
        $imgs = $doc->getElementsByTagName('img');
        foreach ($imgs as $img) {
            if ($img->hasAttribute('src')) {
                $src = $img->getAttribute('src');
                if (strpos($src, FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
                    $newSrc = self::getCacheImageUri($src);
                    $img->setAttribute('data-xextension-imagecache-original-src', $src);
                    $img->setAttribute('src', $newSrc);
                }
            }
            if ($img->hasAttribute('srcset')) {
                $srcSet = $img->getAttribute('srcset');
                $newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/',
                    function (array $matches) {
                        $url = $matches[1];
                        if (strpos($url, FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
                            return str_replace($url, self::getCacheImageUri($url), $matches[0]);
                        }
                        return $matches[0];
                    },
                    $srcSet);
                if ($newSrcSet != null) {
                    $img->setAttribute('data-xextension-imagecache-original-srcset', $srcSet);
                    $img->setAttribute('srcset', $newSrcSet);
                }
            }
            // 处理 Live Photo
            if ($img->hasAttribute('data-livephoto')) {
                $livePhotoUrl = $img->getAttribute('data-livephoto');
                if (strpos($livePhotoUrl, FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
                    $newLivePhotoUrl = self::getCacheImageUri($livePhotoUrl);
                    $img->setAttribute('data-xextension-imagecache-original-livephoto', $livePhotoUrl);
                    $img->setAttribute('data-livephoto', $newLivePhotoUrl);
                }
            }
        }

        // 处理 <video> 标签
        $videos = $doc->getElementsByTagName('video');
        foreach ($videos as $video) {
            if ($video->hasAttribute('poster')) {
                $poster = $video->getAttribute('poster');
                if (strpos($poster, FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
                    $newPoster = self::getCacheImageUri($poster);
                    $video->setAttribute('data-xextension-imagecache-original-poster', $poster);
                    $video->setAttribute('poster', $newPoster);
                }
            }
            if ($video->hasAttribute('src')) {
                $src = $video->getAttribute('src');
                if (strpos($src, FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
                    $newSrc = self::getCacheImageUri($src);
                    $video->setAttribute('data-xextension-imagecache-original-src', $src);
                    $video->setAttribute('src', $newSrc);
                }
            }
            $sources = $video->getElementsByTagName('source');
            foreach ($sources as $source) {
                if ($source->hasAttribute('src')) {
                    $src = $source->getAttribute('src');
                    if (strpos($src, FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
                        $newSrc = self::getCacheImageUri($src);
                        $source->setAttribute('data-xextension-imagecache-original-src', $src);
                        $source->setAttribute('src', $newSrc);
                    }
                }
            }
        }

        return $doc->saveHTML();
    }

    public static function swapThumbnail(array $thumbnail): array
    {
        if (empty($thumbnail['url'])) {
            return $thumbnail;
        }
        if (strpos($thumbnail['url'], FreshRSS_Context::userConf()->image_cache_url) === false) { // 避免重复替换
            $thumbnail['url'] = self::getCacheImageUri($thumbnail['url']);
        }
        return $thumbnail;
    }

    public static function content_modification_hook($entry)
    {
        $entry->_content(
            self::swapUris($entry->content())
        );
        $entry->_attribute(
            'thumbnail',
            self::swapThumbnail($entry->thumbnail() ?? [])
        );
        return $entry;
    }

    public static function image_upload_hook($entry)
    {
        self::cache_images($entry->content());
        self::cache_thumbnail($entry->attributeArray('thumbnail') ?? []);
        return $entry;
    }
}
