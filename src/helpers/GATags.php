<?php
namespace strong2much\google\helpers;

/**
 * Helper class to work with GA tags
 *
 * @author   Denis Tatarnikov <tatarnikovda@gmail.com>
 */
class GATags
{
    /**
     * @var array additional params for query
     */
    public static $qVars = [];

    /**
     * @var string searching pattern
     */
    public static $linkPattern = "<\s*a\s+([^>]*)";

    /**
     * @var string link attrs pattern
     */
    public static $attrPattern = "((id|href)\s*=\s*[\"']?([^\"']+))+";

    /**
     * @var string marker sequence
     */
    public static $marker = "__lnk__";

    /**
     * Apply GA tags to links in specified source
     * @param string $source       source text to apply tags to
     * @param string $utm_source   value for utm_source tag
     * @param string $utm_campaign value for utm_campaign tag
     * @return string processed source with GA tags
     */
    public static function apply($source, $utm_source, $utm_campaign)
    {
        $source    = htmlspecialchars_decode($source, ENT_QUOTES);
        $linksList = [];
        if (!preg_match_all(
            "/".self::$linkPattern."/is", $source, $linksList, PREG_SET_ORDER
        )) {
            return $source;
        }

        $markers     = [];
        $origins     = [];
        $replacement = [];

        foreach ($linksList as $link) {
            if (empty($link[1])) {
                continue;
            }
            if (!preg_match_all(
                "/" . self::$attrPattern . "/is", $link[1], $attributes, PREG_SET_ORDER
            )) {
                continue;
            }
            $elId  = '';
            $elUrl = '';
            foreach ($attributes as $attr) {
                if ($attr[2] == 'id') {
                    $elId = $attr[3];
                } else if ($attr[2] == 'href' && $attr[3]!='#') {
                    $origins[] = '/(<\s*a[^>]*?)[^'.self::$marker.']'.self::escapeString($elUrl = $attr[3]).'([^>]*>)/ism';
                }
            }
            
            $urlScheme = parse_url($elUrl, PHP_URL_SCHEME);
            $markers[self::$marker.$urlScheme] = $urlScheme;
            $replacement[] = '${1}"'.self::$marker.self::processUrl($elUrl, $utm_source, $utm_campaign, (empty($elId) ? null : $elId)).'${2}';
        }
        
        return str_replace(array_keys($markers), array_values($markers), preg_replace($origins, $replacement, $source, 1));
    }

    /**
     * Process URL and add GA tags
     * @param string $url URL to process
     * @param string $utm_source utm_source value
     * @param string $utm_campaign utm_campaign value
     * @param string $utm_content utm_content value
     * @return string processed URL
     */
    public static function processUrl($url, $utm_source, $utm_campaign, $utm_content = null)
    {
        $urlComps = parse_url($url);
        $urlComps['path'] = isset($urlComps['path']) ? $urlComps['path'] : '/';

        if (!function_exists('http_build_url')) {
            $urlBuilder = 'self::buildUrl';
        } else {
            $urlBuilder = 'http_build_url';
        }

        parse_str(
            (isset($urlComps['query']) ? $urlComps['query'] : ''), $qVars
        );

        $qVars['utm_medium']   = empty($qVars['utm_medium']) ? 'email' : $qVars['utm_medium'];
        $qVars['utm_source']   = empty($qVars['utm_source']) ? $utm_source : $qVars['utm_source'];
        $qVars['utm_campaign'] = empty($qVars['utm_campaign']) ? $utm_campaign : $qVars['utm_campaign'];
        $qVars['utm_content']  = empty($qVars['utm_content']) ? $utm_content : $qVars['utm_content'];

        $qVars = array_merge($qVars, self::$qVars);

        return call_user_func_array($urlBuilder, [$urlComps, ['query' => http_build_query($qVars)]]);
    }

    /**
     * Build URL from given params
     * @param array $comps URL components
     * @param array $parts same as above
     * @return string URL
     */
    public static function buildUrl($comps, $parts)
    {
        $default = [
            "scheme" => "http",
            "host" => "",
            "user" => "",
            "pass" => "",
            "path" => "/",
            "query" => "",
            "fragment" => ""
        ];

        $p   = array_merge($default, $comps, $parts);

        if (empty($p['host'])) {
            $url = empty($p['path']) ? '/' : $p['path'];
            if (!empty($p['query'])) {
                $url .= '?'.$p['query'];
            }
        } else {
            $url = empty($p['scheme']) ? '' : $p['scheme'].'://';

            if (!empty($p['user'])) {
                $url .= $p['user'];
                if (!empty($p['pass'])) {
                    $url .= ':'.$p['pass'];
                }
                $url .= '@';
            }

            $url .= $p['host'];
            $url .= empty($p['path']) ? '/' : $p['path'];

            if (!empty($p['query'])) {
                $url .= '?'.$p['query'];
            }
        }

        return $url;
    }

    /**
     * Escape special characters
     * @param string $str source string
     * @return string escaped string
     */
    public static function escapeString($str)
    {
        $patterns = ['/\//', '/\^/', '/\./', '/\$/', '/\|/',
            '/\(/', '/\)/', '/\[/', '/\]/', '/\*/', '/\+/',
            '/\?/', '/\{/', '/\}/', '/\,/'];
        $replace = ['\/', '\^', '\.', '\$', '\|', '\(', '\)',
            '\[', '\]', '\*', '\+', '\?', '\{', '\}', '\,'];

        return preg_replace($patterns, $replace, $str);
    }
}