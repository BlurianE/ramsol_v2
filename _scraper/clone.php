<?php
/**
 * clone.php — Static site cloner for ramsol.com
 *
 * Usage:
 *   php clone.php                 (all 15 pages)
 *   php clone.php salas muebles   (specific pages; 'index' = homepage)
 */

set_time_limit(0);
ini_set('memory_limit', '1024M');

// ── Configuration ────────────────────────────────────────────────────────────
define('BASE_URL',     'https://ramsol.com');
define('OUTPUT_DIR',   '/Applications/XAMPP/xamppfiles/htdocs/site_ramsol/');
define('SCRAPER_DIR',  OUTPUT_DIR . '_scraper/');
define('MANIFEST_FILE', SCRAPER_DIR . 'manifest.json');
define('USER_AGENT',   'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36');
define('PAGE_DELAY',   2);       // seconds delay between pages to avoid rate limiting
define('MAX_RETRIES',  3);

// Target pages: slug => output filename
$PAGES = [
    ''                       => 'index.html',
    'ramsol-grupo-industrial'=> 'ramsol-grupo-industrial.html',
    'ecologia-ramsol'        => 'ecologia-ramsol.html',
    'tecnologia-ramsol'      => 'tecnologia-ramsol.html',
    'pergolas-ramsol'        => 'pergolas-ramsol.html',
    'space-terraza-loft'     => 'space-terraza-loft.html',
    'muebles'                => 'muebles.html',
    'comedores'              => 'comedores.html',
    'mesas-cubiertas'        => 'mesas-cubiertas.html',
    'salas'                  => 'salas.html',
    'tumbonas'               => 'tumbonas.html',
    'productos-todo-terreno' => 'productos-todo-terreno.html',
    'tiendas-showroom'       => 'tiendas-showroom.html',
    'catalogos'              => 'catalogos.html',
    'aviso-de-privacidad'    => 'aviso-de-privacidad.html',
];

// Slug aliases for internal link rewriting
$SLUG_ALIASES = [
    'space'                            => 'space-terraza-loft.html',
    'pergolas-y-muebles-todo-terreno'  => 'index.html',
    'v2/tecnologia-ramsol'             => 'tecnologia-ramsol.html',
];

// Allowed hosts for resource downloads
$ALLOWED_HOSTS = [
    'ramsol.com',
    'www.ramsol.com',
    'cdnjs.cloudflare.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
];

// File extension to folder mapping
$EXT_MAP = [
    // images
    'jpg' => 'images', 'jpeg' => 'images', 'png' => 'images', 'gif' => 'images',
    'webp' => 'images', 'svg' => 'images', 'ico' => 'images', 'bmp' => 'images', 'avif' => 'images',
    // css
    'css' => 'css',
    // js
    'js' => 'js', 'mjs' => 'js',
    // fonts
    'woff' => 'fonts', 'woff2' => 'fonts', 'ttf' => 'fonts', 'eot' => 'fonts', 'otf' => 'fonts',
    // media
    'mp4' => 'media', 'webm' => 'media', 'mp3' => 'media', 'wav' => 'media',
    'lottie' => 'media', 'wasm' => 'media',
];

// Analytics keywords to strip
$ANALYTICS_PATTERNS = [
    'googletagmanager',
    'google_gtagjs',
    'googletagmanager.com',
    'gtag',
    'clarity.ms',
    'tracker.metricool',
    'independent-analytics',
    'iawp-click-endpoint',
];

// ── Global State ─────────────────────────────────────────────────────────────
$manifest        = [];
$resourceQueue   = [];  // url => true
$downloadedCount = 0;
$errorLog        = [];

// Ensure output directories exist
foreach (['images', 'css', 'js', 'fonts', 'media', '_scraper/partials'] as $sub) {
    $dir = OUTPUT_DIR . $sub;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Load existing manifest
if (file_exists(MANIFEST_FILE)) {
    $manifest = json_decode(file_get_contents(MANIFEST_FILE), true) ?: [];
}

// ── Helper: cURL fetch with retry ────────────────────────────────────────────
function curlFetch($url, $retries = MAX_RETRIES) {
    for ($i = 0; $i < $retries; $i++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '', // accept all encodings (gzip, deflate)
        ]);

        // Request modern fonts for Google Fonts
        if (strpos($url, 'fonts.googleapis.com') !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/css,*/*;q=0.1',
            ]);
        }

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $code >= 200 && $code < 400) {
            return $body;
        }

        echo "  [Retry " . ($i + 1) . "/$retries] HTTP $code, Error: $err for: $url\n";
        usleep(500000); // 0.5s pause before retry
    }
    return false;
}

// ── Helper: Canonicalize path ────────────────────────────────────────────────
function canonicalizePath($path) {
    $parts = explode('/', $path);
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($stack);
        } else {
            $stack[] = $part;
        }
    }
    return '/' . implode('/', $stack);
}

// ── Helper: URL cleaner ──────────────────────────────────────────────────────
function cleanUrl($url) {
    $url = trim($url);
    if (empty($url)) return '';

    // Decode HTML entities (e.g. &amp; -> &)
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Unescape JSON-escaped slashes
    $url = str_replace('\\/', '/', $url);

    // Protocol-relative
    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }

    // Strip URL fragment
    $url = preg_replace('/#.*$/', '', $url);

    return trim($url);
}

// ── Helper: Check allowed host ───────────────────────────────────────────────
function isAllowedHost($url) {
    global $ALLOWED_HOSTS;
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    foreach ($ALLOWED_HOSTS as $ah) {
        if (strcasecmp($host, $ah) === 0) return true;
    }
    return false;
}

// ── Helper: Determine category folder for URL ────────────────────────────────
function categorizeUrl($url) {
    global $EXT_MAP;
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    // Google Fonts CSS endpoint has no .css extension
    if (strpos($url, 'fonts.googleapis.com/css') !== false) {
        return 'css';
    }

    // SVG in font paths
    if ($ext === 'svg' && (stripos($path, '/fonts/') !== false || stripos($path, 'avia_fonts') !== false)) {
        return 'fonts';
    }

    return $EXT_MAP[$ext] ?? null;
}

// ── Helper: Generate safe local filename with conflict resolution ────────────
function safeFilename($url, $category) {
    global $manifest;

    $path = parse_url($url, PHP_URL_PATH) ?: '';

    // Google Fonts CSS
    if (strpos($url, 'fonts.googleapis.com/css') !== false) {
        $basename = 'google-fonts-' . substr(md5($url), 0, 8) . '.css';
    } else {
        $basename = basename($path);
        if (empty($basename) || $basename === '/' || strpos($basename, '.') === false) {
            $ext = (categorizeUrl($url) === 'css') ? 'css' : 'bin';
            $basename = substr(md5($url), 0, 12) . '.' . $ext;
        }
    }

    // Sanitize characters
    $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);

    // Check conflict: same filename from a different URL (ignoring minor query diffs)
    $localPath = $category . '/' . $basename;
    $conflict = false;
    $urlBase = strtok($url, '?');

    foreach ($manifest as $mUrl => $mFile) {
        if ($mFile === $localPath) {
            $mUrlBase = strtok($mUrl, '?');
            if ($mUrlBase !== $urlBase) {
                $conflict = true;
                break;
            }
        }
    }

    if ($conflict) {
        $info   = pathinfo($basename);
        $suffix = substr(md5($url), 0, 6);
        $ext    = isset($info['extension']) ? '.' . $info['extension'] : '';
        $basename = $info['filename'] . '-' . $suffix . $ext;
    }

    return $basename;
}

// ── Helper: Resolve relative URL against base ────────────────────────────────
function resolveUrl($rel, $base) {
    $rel = trim($rel);
    if (empty($rel) || strpos($rel, 'data:') === 0 || strpos($rel, '#') === 0 || strpos($rel, 'javascript:') === 0 || strpos($rel, 'mailto:') === 0 || strpos($rel, 'tel:') === 0) {
        return null;
    }

    if (strpos($rel, '//') === 0) {
        $rel = 'https:' . $rel;
    }
    if (preg_match('#^https?://#i', $rel)) {
        return cleanUrl($rel);
    }

    $parsed = parse_url($base);
    $scheme = $parsed['scheme'] ?? 'https';
    $host   = $parsed['host'] ?? 'ramsol.com';
    $path   = $parsed['path'] ?? '/';

    $relParts = explode('?', $rel, 2);
    $relPath  = $relParts[0];
    $query    = isset($relParts[1]) ? '?' . $relParts[1] : '';

    if (strpos($relPath, '/') === 0) {
        $fullPath = canonicalizePath($relPath);
    } else {
        $dir = dirname($path);
        if ($dir === '.' || $dir === '/') $dir = '';
        $fullPath = canonicalizePath($dir . '/' . $relPath);
    }

    return cleanUrl("$scheme://$host$fullPath$query");
}

// ── Helper: Queue resource for downloading ───────────────────────────────────
function queueResource($url) {
    global $resourceQueue;
    $url = cleanUrl($url);
    if (empty($url)) return;
    if (isAllowedHost($url) && categorizeUrl($url)) {
        $resourceQueue[$url] = true;
    }
}

// ── Discover resources in HTML ───────────────────────────────────────────────
function discoverHtmlResources($html) {
    // 1. Tag attributes
    $attrs = ['src', 'href', 'data-src', 'data-lazy-src', 'data-original', 'poster', 'content'];
    foreach ($attrs as $attr) {
        if (preg_match_all('/\b' . $attr . '\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $val) {
                $url = resolveUrl($val, BASE_URL . '/');
                if ($url) queueResource($url);
            }
        }
    }

    // 2. srcset / data-lazy-srcset
    if (preg_match_all('/\b(?:srcset|data-lazy-srcset)\s*=\s*["\']([^"\']+)["\']/is', $html, $m)) {
        foreach ($m[1] as $srcset) {
            $entries = preg_split('/\s*,\s*/', $srcset);
            foreach ($entries as $entry) {
                $parts = preg_split('/\s+/', trim($entry));
                if (!empty($parts[0])) {
                    $url = resolveUrl($parts[0], BASE_URL . '/');
                    if ($url) queueResource($url);
                }
            }
        }
    }

    // 3. Inline style url(...) — allows commas (for fonts)
    if (preg_match_all('/url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $html, $m)) {
        foreach ($m[1] as $val) {
            $url = resolveUrl($val, BASE_URL . '/');
            if ($url) queueResource($url);
        }
    }
}

// ── Parse CSS for embedded resources ─────────────────────────────────────────
function parseCssForResources($css, $cssUrl) {
    // url(...) — allows commas
    if (preg_match_all('/url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $css, $m)) {
        foreach ($m[1] as $val) {
            if (strpos($val, 'data:') === 0) continue;
            $url = resolveUrl($val, $cssUrl);
            if ($url) queueResource($url);
        }
    }

    // @import
    if (preg_match_all('/@import\s+(?:url\(\s*)?["\']?([^"\'\)\s]+)["\']?\s*\)?/i', $css, $m)) {
        foreach ($m[1] as $val) {
            $url = resolveUrl($val, $cssUrl);
            if ($url) queueResource($url);
        }
    }
}

// ── Discover URLs in JS files / inline scripts ───────────────────────────────
function discoverJsResources($js) {
    // Standard URLs
    if (preg_match_all('#https?://(?:ramsol\.com|www\.ramsol\.com|cdnjs\.cloudflare\.com|fonts\.googleapis\.com|fonts\.gstatic\.com)[^\s"\'<>\\\}\]\)]+#i', $js, $m)) {
        foreach ($m[0] as $url) {
            $url = resolveUrl($url, BASE_URL . '/');
            if ($url) queueResource($url);
        }
    }

    // Escaped URLs in JSON: https:\/\/ramsol.com\/...
    if (preg_match_all('#https?:\\\\/\\\\/(?:ramsol\.com|www\.ramsol\.com|cdnjs\.cloudflare\.com|fonts\.googleapis\.com|fonts\.gstatic\.com)\\\\/[^\s"\'<>\\\}\]\)]+#i', $js, $m)) {
        foreach ($m[0] as $rawUrl) {
            $url = resolveUrl($rawUrl, BASE_URL . '/');
            if ($url) queueResource($url);
        }
    }
}

// ── Download single resource ─────────────────────────────────────────────────
function downloadResource($url) {
    global $manifest, $downloadedCount, $errorLog;

    $url = cleanUrl($url);
    if (empty($url) || !isAllowedHost($url)) return null;

    $category = categorizeUrl($url);
    if (!$category) return null;

    // Check manifest cache
    if (isset($manifest[$url])) {
        $localPath = $manifest[$url];
        if (file_exists(OUTPUT_DIR . $localPath)) {
            return $localPath;
        }
    }

    $filename  = safeFilename($url, $category);
    $localPath = $category . '/' . $filename;
    $fullPath  = OUTPUT_DIR . $localPath;

    echo "  ↓ [$category] $url → $localPath\n";

    $data = curlFetch($url, MAX_RETRIES);
    if ($data === false) {
        $errorLog[] = "Download failed: $url";
        echo "  ✗ FAILED: $url\n";
        return null;
    }

    file_put_contents($fullPath, $data);

    // Save in manifest (both with query and without query for fast lookups)
    $manifest[$url] = $localPath;
    $urlNoQuery = strtok($url, '?');
    if (!isset($manifest[$urlNoQuery])) {
        $manifest[$urlNoQuery] = $localPath;
    }

    $downloadedCount++;

    // Parse CSS for further nested resources (e.g. fonts inside google-fonts or theme css)
    if ($category === 'css') {
        parseCssForResources($data, $url);
    }

    return $localPath;
}

// ── Rewrite internal page links ──────────────────────────────────────────────
function rewriteInternalLinks($html) {
    global $PAGES, $SLUG_ALIASES;

    $map = [];
    foreach ($PAGES as $slug => $file) {
        $map[trim($slug, '/')] = $file;
    }
    foreach ($SLUG_ALIASES as $alias => $file) {
        $map[trim($alias, '/')] = $file;
    }

    return preg_replace_callback(
        '#\bhref\s*=\s*(["\'])(.*?)\1#i',
        function($matches) use ($map) {
            $quote = $matches[1];
            $url   = $matches[2];

            // Extract hash
            $hash = '';
            if (strpos($url, '#') !== false) {
                list($url, $hash) = explode('#', $url, 2);
                $hash = '#' . $hash;
            }

            $path = null;
            if (preg_match('#^https?://(?:www\.)?ramsol\.com(?:/(.*))?$#i', $url, $m)) {
                $path = isset($m[1]) ? $m[1] : '';
            } elseif (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
                $path = substr($url, 1);
            }

            if ($path !== null) {
                $trimmed = trim(strtok($path, '?'), '/');

                if ($trimmed === '') {
                    return 'href=' . $quote . 'index.html' . $hash . $quote;
                }
                if (isset($map[$trimmed])) {
                    return 'href=' . $quote . $map[$trimmed] . $hash . $quote;
                }
                if (strpos($trimmed, 'v2/') === 0) {
                    $sub = substr($trimmed, 3);
                    if (isset($map[$sub])) {
                        return 'href=' . $quote . $map[$sub] . $hash . $quote;
                    }
                }
            }

            return $matches[0];
        },
        $html
    );
}

// ── Rewrite resource URLs in HTML ────────────────────────────────────────────
function rewriteHtmlResourceUrls($html) {
    global $manifest;

    // Attributes: src, href, data-*, poster, content
    $html = preg_replace_callback(
        '#\b((?:src|data-src|data-lazy-src|data-original|poster|content|href)\s*=\s*["\'])([^"\']+)(["\'])#i',
        function($m) use ($manifest) {
            $prefix   = $m[1];
            $origUrl  = $m[2];
            $quote    = $m[3];

            $cleaned = cleanUrl($origUrl);
            if (isset($manifest[$cleaned])) {
                return $prefix . $manifest[$cleaned] . $quote;
            }

            $noQuery = strtok($cleaned, '?');
            if (isset($manifest[$noQuery])) {
                return $prefix . $manifest[$noQuery] . $quote;
            }

            return $m[0];
        },
        $html
    );

    // srcset and data-lazy-srcset
    $html = preg_replace_callback(
        '#\b((?:srcset|data-lazy-srcset)\s*=\s*["\'])([^"\']+)(["\'])#is',
        function($m) use ($manifest) {
            $prefix  = $m[1];
            $quote   = $m[3];
            $entries = preg_split('/\s*,\s*/', $m[2]);
            $rewritten = [];

            foreach ($entries as $entry) {
                $parts = preg_split('/\s+/', trim($entry), 2);
                $url   = cleanUrl($parts[0]);
                $desc  = isset($parts[1]) ? ' ' . $parts[1] : '';

                if (isset($manifest[$url])) {
                    $rewritten[] = $manifest[$url] . $desc;
                } else {
                    $noQuery = strtok($url, '?');
                    if (isset($manifest[$noQuery])) {
                        $rewritten[] = $manifest[$noQuery] . $desc;
                    } else {
                        $rewritten[] = $parts[0] . $desc;
                    }
                }
            }

            return $prefix . implode(', ', $rewritten) . $quote;
        },
        $html
    );

    // Inline style url(...)
    $html = preg_replace_callback(
        '#url\(\s*["\']?([^"\')\s]+)["\']?\s*\)#i',
        function($m) use ($manifest) {
            $url = cleanUrl($m[1]);
            if (strpos($url, 'data:') === 0) return $m[0];

            if (isset($manifest[$url])) {
                return 'url(' . $manifest[$url] . ')';
            }
            $noQuery = strtok($url, '?');
            if (isset($manifest[$noQuery])) {
                return 'url(' . $manifest[$noQuery] . ')';
            }
            return $m[0];
        },
        $html
    );

    return $html;
}

// ── Rewrite escaped URLs in JSON ─────────────────────────────────────────────
function rewriteEscapedUrls($content) {
    global $manifest;
    return preg_replace_callback(
        '#https?:\\\\/\\\\/(?:ramsol\.com|www\.ramsol\.com|cdnjs\.cloudflare\.com|fonts\.googleapis\.com|fonts\.gstatic\.com)\\\\/[^\s"\'<>\\\}\]\)]+#i',
        function($m) use ($manifest) {
            $raw       = $m[0];
            $unescaped = cleanUrl($raw);
            if (isset($manifest[$unescaped])) {
                return str_replace('/', '\\/', $manifest[$unescaped]);
            }
            $noQuery = strtok($unescaped, '?');
            if (isset($manifest[$noQuery])) {
                return str_replace('/', '\\/', $manifest[$noQuery]);
            }
            return $raw;
        },
        $content
    );
}

// ── Rewrite CSS URLs (relative to css/ folder: ../images/, ../fonts/) ────────
function rewriteCssUrls($css, $cssUrl) {
    global $manifest;

    // url(...)
    $css = preg_replace_callback(
        '#url\(\s*["\']?([^"\')\s]+)["\']?\s*\)#i',
        function($m) use ($manifest, $cssUrl) {
            $raw = $m[1];
            if (strpos($raw, 'data:') === 0) return $m[0];

            $url = resolveUrl($raw, $cssUrl);
            if ($url) {
                $target = $manifest[$url] ?? $manifest[strtok($url, '?')] ?? null;
                if ($target) {
                    if (strpos($target, 'css/') === 0) {
                        return 'url(' . basename($target) . ')';
                    }
                    return 'url(../' . $target . ')';
                }
            }
            return $m[0];
        },
        $css
    );

    // @import
    $css = preg_replace_callback(
        '#@import\s+(?:url\(\s*)?["\']?([^"\'\)\s]+)["\']?\s*\)?#i',
        function($m) use ($manifest, $cssUrl) {
            $url = resolveUrl($m[1], $cssUrl);
            if ($url) {
                $target = $manifest[$url] ?? $manifest[strtok($url, '?')] ?? null;
                if ($target) {
                    if (strpos($target, 'css/') === 0) {
                        return '@import "' . basename($target) . '"';
                    }
                    return '@import "../' . $target . '"';
                }
            }
            return $m[0];
        },
        $css
    );

    return $css;
}

// ── Rewrite JS URLs ──────────────────────────────────────────────────────────
function rewriteJsUrls($js) {
    global $manifest;

    // Normal URLs
    $js = preg_replace_callback(
        '#https?://(?:ramsol\.com|www\.ramsol\.com|cdnjs\.cloudflare\.com|fonts\.googleapis\.com|fonts\.gstatic\.com)/[^\s"\'<>\\\}\]\),;]+#i',
        function($m) use ($manifest) {
            $url = cleanUrl($m[0]);
            $target = $manifest[$url] ?? $manifest[strtok($url, '?')] ?? null;
            if ($target) {
                return $target;
            }
            return $m[0];
        },
        $js
    );

    // Escaped URLs
    $js = rewriteEscapedUrls($js);

    return $js;
}

// ── Remove analytics scripts and beacons ─────────────────────────────────────
function removeAnalytics($html) {
    global $ANALYTICS_PATTERNS;

    // Remove <script> tags containing analytics
    $html = preg_replace_callback(
        '#<script[^>]*>.*?</script>#is',
        function($m) use ($ANALYTICS_PATTERNS) {
            foreach ($ANALYTICS_PATTERNS as $pattern) {
                if (stripos($m[0], $pattern) !== false) {
                    return '<!-- analytics script removed -->';
                }
            }
            return $m[0];
        },
        $html
    );

    // Remove self-closing or lone <script src="..."> tags
    $html = preg_replace_callback(
        '#<script[^>]*src=["\'][^"\']*["\'][^>]*>#i',
        function($m) use ($ANALYTICS_PATTERNS) {
            foreach ($ANALYTICS_PATTERNS as $pattern) {
                if (stripos($m[0], $pattern) !== false) {
                    return '<!-- analytics script removed -->';
                }
            }
            return $m[0];
        },
        $html
    );

    // Remove <noscript> tracking tags
    $html = preg_replace_callback(
        '#<noscript[^>]*>.*?</noscript>#is',
        function($m) use ($ANALYTICS_PATTERNS) {
            foreach ($ANALYTICS_PATTERNS as $pattern) {
                if (stripos($m[0], $pattern) !== false) {
                    return '<!-- analytics noscript removed -->';
                }
            }
            return $m[0];
        },
        $html
    );

    return $html;
}

// ── Inject head scripts ──────────────────────────────────────────────────────
function injectHeadScripts($html) {
    $inject = "\n<script src=\"js/local-file.js\"></script>\n<script src=\"js/analitica.js\"></script>\n";
    $html = preg_replace('#(<head\b[^>]*>)#i', '$1' . $inject, $html, 1);
    return $html;
}

// ── Replace Contact Form 7 container with balanced div parser ────────────────
function replaceWpcf7($html, $replacement) {
    if (!preg_match('/<div[^>]*class=["\'][^"\']*\bwpcf7\b[^"\']*["\'][^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
        return $html;
    }
    $startPos = $matches[0][1];

    $len = strlen($html);
    $depth = 0;
    $pos = $startPos;
    $endPos = false;

    while ($pos < $len) {
        if (preg_match('/<(\/?)div\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $tag = $m[0][0];
            $tagOffset = $m[0][1];
            $isClose = ($m[1][0] === '/');

            if ($isClose) {
                $depth--;
                if ($depth === 0) {
                    $endPos = $tagOffset + strlen($tag);
                    break;
                }
            } else {
                $depth++;
            }
            $pos = $tagOffset + strlen($tag);
        } else {
            break;
        }
    }

    if ($endPos !== false) {
        return substr($html, 0, $startPos) . $replacement . substr($html, $endPos);
    }

    return $html;
}

// ── Tiendas Showroom specific transformations ────────────────────────────────
function handleTiendasShowroom($html) {
    // 1. Replace Contact Form 7
    $partialFile = SCRAPER_DIR . 'partials/contact-form.html';
    if (file_exists($partialFile)) {
        $partial = file_get_contents($partialFile);
        $html = replaceWpcf7($html, $partial);
        echo "    ✓ Contact Form 7 replaced with custom form partial\n";
    } else {
        echo "    ⚠ Partial not found: $partialFile\n";
    }

    // 2. Remove reCAPTCHA scripts
    $html = preg_replace('#<script[^>]*recaptcha[^>]*>.*?</script>#is', '<!-- reCAPTCHA removed -->', $html);
    $html = preg_replace('#<script[^>]*grecaptcha[^>]*>.*?</script>#is', '<!-- reCAPTCHA removed -->', $html);
    $html = preg_replace('#<script[^>]*src=["\'][^"\']*recaptcha[^"\']*["\'][^>]*>(\s*</script>)?#i', '<!-- reCAPTCHA removed -->', $html);
    echo "    ✓ reCAPTCHA scripts removed\n";

    // 3. Add css/contacto.css + js/contacto.js before </head>
    $inject = '<link rel="stylesheet" href="css/contacto.css">' . "\n"
            . '<script src="js/contacto.js"></script>' . "\n";
    $html = str_ireplace('</head>', $inject . '</head>', $html);
    echo "    ✓ css/contacto.css and js/contacto.js injected before </head>\n";

    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// MAIN PIPELINE
// ══════════════════════════════════════════════════════════════════════════════

echo "═══════════════════════════════════════════════════════════════\n";
echo "  RAMSOL.COM STATIC CLONER\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Copy support files to js/ if present in partials
if (file_exists(SCRAPER_DIR . 'partials/local-file.js') && !file_exists(OUTPUT_DIR . 'js/local-file.js')) {
    @copy(SCRAPER_DIR . 'partials/local-file.js', OUTPUT_DIR . 'js/local-file.js');
}
if (file_exists(SCRAPER_DIR . 'partials/analitica.js') && !file_exists(OUTPUT_DIR . 'js/analitica.js')) {
    @copy(SCRAPER_DIR . 'partials/analitica.js', OUTPUT_DIR . 'js/analitica.js');
}

// Select target pages from CLI arguments
$selectedPages = [];
$args = array_slice($argv, 1);

if (empty($args)) {
    $selectedPages = $PAGES;
    echo "Mode: Cloning ALL " . count($PAGES) . " pages\n\n";
} else {
    foreach ($args as $arg) {
        $slug = ($arg === 'index') ? '' : $arg;
        if (isset($PAGES[$slug])) {
            $selectedPages[$slug] = $PAGES[$slug];
        } else {
            echo "⚠ Unknown slug: '$arg' — skipping\n";
        }
    }
    echo "Mode: Cloning " . count($selectedPages) . " specified pages\n\n";
}

if (empty($selectedPages)) {
    die("Error: No valid pages selected.\n");
}

// ── Phase 1: Fetch HTML pages and discover initial assets ────────────────────
echo "━━━ Phase 1: Fetching pages & discovering assets ━━━━━━━━━━━━━━\n";
$pageContents = [];
$fetchedPagesCount = 0;

$pageKeys = array_keys($selectedPages);
$lastSlug = end($pageKeys);

foreach ($selectedPages as $slug => $filename) {
    $url = BASE_URL . '/' . ($slug ? $slug . '/' : '');
    echo "  📄 Fetching: $url → $filename ... ";

    $html = curlFetch($url);
    if ($html === false) {
        $errorLog[] = "Failed to fetch page: $url";
        echo "FAILED\n";
        continue;
    }

    $len = strlen($html);
    echo "OK ($len bytes)\n";
    $pageContents[$slug] = ['filename' => $filename, 'html' => $html, 'url' => $url];
    $fetchedPagesCount++;

    // Discover resources
    discoverHtmlResources($html);
    discoverJsResources($html);

    // Politeness delay
    if ($slug !== $lastSlug && PAGE_DELAY > 0) {
        sleep(PAGE_DELAY);
    }
}

echo "\nDiscovered initial resources: " . count($resourceQueue) . "\n\n";

// ── Phase 2: Recursively download assets ──────────────────────────────────────
echo "━━━ Phase 2: Downloading resources ━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$processedResources = [];
$pass = 0;
$maxPasses = 10;

while (!empty($resourceQueue) && $pass < $maxPasses) {
    $pass++;
    $currentBatch = array_keys($resourceQueue);
    $resourceQueue = [];

    echo "  Batch $pass: processing " . count($currentBatch) . " resources...\n";

    foreach ($currentBatch as $url) {
        if (isset($processedResources[$url])) continue;
        $processedResources[$url] = true;
        downloadResource($url);
    }

    if (!empty($resourceQueue)) {
        echo "  → Discovered " . count($resourceQueue) . " additional resources from batch $pass\n";
    }
}

// ── Phase 3: Rewrite CSS files ───────────────────────────────────────────────
echo "\n━━━ Phase 3: Rewriting CSS files ──────────────────────────────\n";
$rewrittenCss = 0;
foreach ($manifest as $url => $localPath) {
    if (strpos($localPath, 'css/') === 0) {
        $fullPath = OUTPUT_DIR . $localPath;
        if (file_exists($fullPath)) {
            $css = file_get_contents($fullPath);
            $newCss = rewriteCssUrls($css, $url);
            if ($newCss !== $css) {
                file_put_contents($fullPath, $newCss);
                $rewrittenCss++;
            }
        }
    }
}
echo "  Rewritten $rewrittenCss CSS files with relative paths\n";

// ── Phase 4: Rewrite JS files ────────────────────────────────────────────────
echo "\n━━━ Phase 4: Rewriting JS files ───────────────────────────────\n";
$rewrittenJs = 0;
foreach ($manifest as $url => $localPath) {
    if (strpos($localPath, 'js/') === 0) {
        $fullPath = OUTPUT_DIR . $localPath;
        if (file_exists($fullPath)) {
            $js = file_get_contents($fullPath);
            $newJs = rewriteJsUrls($js);
            if ($newJs !== $js) {
                file_put_contents($fullPath, $newJs);
                $rewrittenJs++;
            }
        }
    }
}
echo "  Rewritten $rewrittenJs JS files with relative paths\n";

// ── Phase 5: Transform and write HTML pages ──────────────────────────────────
echo "\n━━━ Phase 5: Processing and saving HTML pages ─────────────────\n";
foreach ($pageContents as $slug => $page) {
    $filename = $page['filename'];
    $html     = $page['html'];

    echo "  ✎ Processing $filename...\n";

    // 1. Remove analytics
    $html = removeAnalytics($html);

    // 2. Rewrite internal links
    $html = rewriteInternalLinks($html);

    // 3. Rewrite resource URLs
    $html = rewriteHtmlResourceUrls($html);

    // 4. Rewrite escaped JSON URLs
    $html = rewriteEscapedUrls($html);

    // 5. Inject head scripts
    $html = injectHeadScripts($html);

    // 6. Handle tiendas-showroom.html specific overrides
    if ($filename === 'tiendas-showroom.html') {
        $html = handleTiendasShowroom($html);
    }

    // Save final HTML
    file_put_contents(OUTPUT_DIR . $filename, $html);
    echo "    ✓ Saved $filename (" . strlen($html) . " bytes)\n";
}

// ── Save manifest ────────────────────────────────────────────────────────────
file_put_contents(MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\n✓ manifest.json updated with " . count($manifest) . " total mappings\n";

// ── Final Summary ────────────────────────────────────────────────────────────
echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  CLONING SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Pages processed:       $fetchedPagesCount / " . count($selectedPages) . "\n";
echo "  New resources grabbed: $downloadedCount\n";
echo "  Total in manifest:     " . count($manifest) . "\n";
echo "  Errors encountered:    " . count($errorLog) . "\n";

if (!empty($errorLog)) {
    echo "\n  Error details:\n";
    foreach ($errorLog as $err) {
        echo "    - $err\n";
    }
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  COMPLETED\n";
echo "═══════════════════════════════════════════════════════════════\n";
