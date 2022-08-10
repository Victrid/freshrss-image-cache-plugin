# Image Cache Extension

This FreshRSS extension allows you to cache feeds’ pictures in your own facility.

To use it, upload this entire directory to the FreshRSS `./extensions` directory on your server and enable it on the extension panel in FreshRSS. 

There is a Cloudflare worker implementation of the cache utilizing its Cache API. Check this [repo](https://github.com/Victrid/image-cache-worker) (It's can be run on free tier).

## Configuration settings

-   `cache_url` (default: `https://example.com/pic?url=`): The URL of the image used to load when the user reads the feed article.

-   `post_url` (default: `https://example.com/prepare`): Address used to inform the caching service when FreshRSS fetches a new article.

    The plugin will send a JSON POST request to this address in this format:

    ```json
    {
        "url": "https://http.cat/418.jpg",
        "access_token": "YOUR_ACCESS_TOKEN"
    }
    ```

-   `access_token` (default: `""`): See the JSON request above.

-   `url_encode` (default: `1`): whether to URL-encode (RFC 3986) the proxied URL.

## Important Note

Your cache implementation should not rely on the `post`-method, in other words, the `cache_url` should support cache-miss situations.

## See Also

[ImageProxy](https://github.com/FreshRSS/Extensions/tree/master/xExtension-ImageProxy) plugin: Don’t need a cache, just proxy? Use ImageProxy plugin instead.

This extension is based on ImageProxy plugin, and is licensed under GPLv3.
