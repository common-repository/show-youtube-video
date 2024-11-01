<?php
/*
* Plugin Name: Show YouTube video
* Description: Simply show video from YouTube on your WordPress in responsive mode - full width. Shortcode for show video by ID or last video by channel. No API needed.
* Version: 1.1
* Author: Michal Novák
* Author URI: https://www.novami.cz
* License: GPLv3
* Text Domain: show-youtube-video
*/

/**
 * Class ShowYouTubeVideo
 */
class ShowYouTubeVideo
{
    private $pluginName;

    public function __construct()
    {
        $this->pluginName = get_file_data(__FILE__, ['Name' => 'Plugin Name'])['Name'];

        add_action('wp_enqueue_scripts', [$this, 'loadStyle']);

        add_shortcode('syvLast', [$this, 'getLastVideoByChannel']);
        add_shortcode('syv', [$this, 'getVideoById']);
    }

    private function getIframe($url)
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);

        return sprintf('<div class="siv-container"><iframe src="%s" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>', $url);
    }

    public function getLastVideoByChannel($atts)
    {
        $id = [];
        $error = true;
        $errorMsg = sprintf('<p>%s</p>', __('Couldn\'t get last video', 'show-youtube-video'));

        if (!isset($atts['channel'])) {
            return $errorMsg;
        }

        $cacheKey = sprintf('%s_last_%s', sanitize_title($this->pluginName), $atts['channel']);
        $cache = get_transient($cacheKey);
        if ($cache) {
            $id[1] = $cache;
            $error = false;
        } else {
            $today = date('Ymd');
            $cookie = new WP_Http_Cookie('CONSENT');
            $cookie->name = 'CONSENT';
            $cookie->value = sprintf('YES+cb.%s-17-p0.en+F+886', $today);
            $cookie->expires = mktime(0, 0, 0, date('m'), date('d'), date('Y') + 1);
            $cookie->path = '/';
            $cookie->domain = '.youtube.com';

            $source = wp_remote_get(sprintf('https://www.youtube.com/c/%s/videos', $atts['channel']), ['limit_response_size' => 1000 * 300, 'cookies' => [$cookie]]);
            if (intval(wp_remote_retrieve_response_code($source)) === 200) {
                //file_put_contents(dirname(__FILE__) . '/response.txt', wp_remote_retrieve_body($source));
                preg_match('/watch\?v=([a-zA-Z0-9_-]{5,15})/s', wp_remote_retrieve_body($source), $id);

                if ($id[1]) {
                    set_transient($cacheKey, $id[1], 60 * 60);
                    $error = false;
                }
            }
        }

        if ($error) {
            return $errorMsg;
        } else {
            return $this->getVideoById(['id' => $id[1]]);
        }
    }

    public function getVideoById($atts)
    {
        $url = sprintf('https://www.youtube-nocookie.com/embed/%s', $atts['id']);

        return $this->getIframe($url);
    }

    public function loadStyle()
    {
        $stylePath = sprintf('%sstyle.css', plugin_dir_url(__FILE__));
        $styleVersion = (int)filemtime(sprintf('%s/style.css', plugin_dir_path(__FILE__)));
        wp_enqueue_style($this->pluginName, $stylePath, [], $styleVersion);
    }
}

new ShowYouTubeVideo();
