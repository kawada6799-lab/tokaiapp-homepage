<?php
/**
 * seo.php — 検索エンジン・SNS・計測のために <head> に出すもの
 *
 * 出すものは4つ。すべて pages.json（各ページのタイトルと説明文）を元にする。
 *
 *   1. <title> と meta description
 *      プレビュー（build.mjs）と同じ文言を本番でも出す。
 *      ページを増やしたら pages.json に1行足せば、ここは触らなくてよい
 *
 *   2. OGP / Twitter カード
 *      メール・LINE・SNS にURLを貼ったときに出る、タイトル・説明・画像。
 *      画像は「外観 → カスタマイズ → 東海App 設定」で選ぶ。未設定なら画像なし
 *
 *   3. 構造化データ（JSON-LD）
 *      Google に「これは会社のサイトで、こういうサービスを出している」と
 *      機械が読める形で伝える。★ここに書いてよいのは確認済みの情報だけ★
 *        - 会社: 名前・URL・ロゴ・所在地（〒464-0008 愛知県名古屋市千種区まで）
 *        - サービス3つ: 名前と説明文（pages.json と同じ）
 *        - ドウイナビの料金: 初期 55,000円 / 月額 11,000円（税込・確定済み）
 *      メールアドレスと電話番号は入れない（迷惑メール対策・掲載しない方針）
 *
 *   4. GA4 の計測タグ
 *      測定IDが設定されているときだけ出す。ログイン中は出さない
 *      （自分の閲覧を数えないため）
 */

if (!defined('ABSPATH')) { exit; }

/**
 * pages.json を読んで返す（1リクエストに1回だけ読む）。
 *
 * @return array<int, array{slug:string,url:string,title:string,desc:string}>
 */
function tokaiapp_pages() {
    static $pages = null;
    if ($pages !== null) { return $pages; }

    $pages = array();
    $path = get_template_directory() . '/pages.json';
    if (is_readable($path)) {
        $json = json_decode((string) file_get_contents($path), true);
        if (is_array($json)) { $pages = $json; }
    }
    return $pages;
}

/**
 * いま表示しているページの pages.json の行を返す。無ければ null。
 *
 * @return array|null
 */
function tokaiapp_page_meta() {
    $slug = tokaiapp_page_slug();
    if ($slug === '') { return null; }
    foreach (tokaiapp_pages() as $page) {
        if (isset($page['slug']) && $page['slug'] === $slug) { return $page; }
    }
    return null;
}

/** pages.json の url（/price/ など）から、そのページの行を探す */
function tokaiapp_page_by_url($url) {
    foreach (tokaiapp_pages() as $page) {
        if (isset($page['url']) && $page['url'] === $url) { return $page; }
    }
    return null;
}

/** 「料金｜東海App」→「料金」。パンくず用 */
function tokaiapp_short_title($page) {
    $title = isset($page['title']) ? (string) $page['title'] : '';
    $parts = explode('｜', $title);
    return trim($parts[0]);
}

/* 1. <title> ---------------------------------------------------------------
   pages.json に行があれば、その title をそのまま使う。
   無いページ（WordPress の投稿など）は WordPress 標準のまま。 */
add_filter('pre_get_document_title', function ($title) {
    $meta = tokaiapp_page_meta();
    return ($meta && !empty($meta['title'])) ? $meta['title'] : $title;
});

/* 2〜4. <head> への出力 ---------------------------------------------------- */
add_action('wp_head', function () {
    $meta  = tokaiapp_page_meta();
    $title = wp_get_document_title();
    $desc  = ($meta && !empty($meta['desc'])) ? $meta['desc'] : get_bloginfo('description');
    $url   = ($meta && !empty($meta['url'])) ? home_url($meta['url']) : home_url(add_query_arg(array()));
    $image = tokaiapp_setting('ogp_image');

    // --- meta description / OGP / Twitter -------------------------------
    echo "\n<!-- 東海App: 検索・SNS向け -->\n";
    if ($desc !== '') {
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }
    echo '<meta property="og:site_name" content="東海App">' . "\n";
    echo '<meta property="og:locale" content="ja_JP">' . "\n";
    echo '<meta property="og:type" content="' . (is_front_page() ? 'website' : 'article') . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    if ($desc !== '') {
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    }
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    if ($image !== '') {
        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    } else {
        echo '<meta name="twitter:card" content="summary">' . "\n";
    }

    // --- 構造化データ（JSON-LD） ----------------------------------------
    $graph = tokaiapp_jsonld_graph($meta, $title, $desc, $url, $image);
    echo '<script type="application/ld+json">'
       . wp_json_encode(array('@context' => 'https://schema.org', '@graph' => $graph),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
       . "</script>\n";

    // --- GA4 ------------------------------------------------------------
    $ga4 = tokaiapp_setting('ga4_id');
    if ($ga4 !== '' && !is_user_logged_in()) {
        $id = esc_attr($ga4);
        echo "<!-- Google アナリティクス（GA4） -->\n";
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $id . '"></script>' . "\n";
        echo "<script>\n"
           . "window.dataLayer = window.dataLayer || [];\n"
           . "function gtag(){dataLayer.push(arguments);}\n"
           . "gtag('js', new Date());\n"
           . "gtag('config', '" . $id . "');\n"
           . "</script>\n";
    }
}, 1);

/**
 * JSON-LD の中身を組み立てる。
 *
 * すべてのページ: Organization
 * トップ:         WebSite
 * 下層ページ:     BreadcrumbList（pages.json の url の階層から作る）
 * サービス詳細:   Service（ドウイナビだけ料金つき）
 */
function tokaiapp_jsonld_graph($meta, $title, $desc, $url, $image) {
    $home   = home_url('/');
    $org_id = $home . '#organization';

    $org = array(
        '@type' => 'Organization',
        '@id'   => $org_id,
        'name'  => '東海App',
        'url'   => $home,
        'logo'  => get_template_directory_uri() . '/assets/img/logo.png',
        'address' => array(
            '@type'           => 'PostalAddress',
            'postalCode'      => '464-0008',
            'addressRegion'   => '愛知県',
            'addressLocality' => '名古屋市千種区',
            'addressCountry'  => 'JP',
        ),
    );
    $graph = array($org);

    if (is_front_page()) {
        $graph[] = array(
            '@type'       => 'WebSite',
            '@id'         => $home . '#website',
            'url'         => $home,
            'name'        => '東海App',
            'description' => $desc,
            'publisher'   => array('@id' => $org_id),
            'inLanguage'  => 'ja',
        );
        return $graph;
    }

    if (!$meta || empty($meta['url'])) { return $graph; }

    // パンくず: /service/douinavi/ → ホーム › サービス › ドウイナビ
    $items = array(array('@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => $home));
    $segments = array_filter(explode('/', trim($meta['url'], '/')));
    $path = '';
    foreach ($segments as $seg) {
        $path .= '/' . $seg;
        $page = tokaiapp_page_by_url($path . '/');
        if (!$page) { continue; }
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => count($items) + 1,
            'name'     => tokaiapp_short_title($page),
            'item'     => home_url($page['url']),
        );
    }
    if (count($items) > 1) {
        $graph[] = array('@type' => 'BreadcrumbList', 'itemListElement' => $items);
    }

    // サービス詳細ページ
    if (strpos($meta['slug'], 'service-') === 0) {
        $service = array(
            '@type'       => 'Service',
            'name'        => tokaiapp_short_title($meta),
            'description' => $desc,
            'url'         => $url,
            'provider'    => array('@id' => $org_id),
            'serviceType' => '医療DX',
        );
        if ($image !== '') { $service['image'] = $image; }

        // 料金を出しているのはドウイナビだけ（docs/要確認リスト.md の決定事項）
        if ($meta['slug'] === 'service-douinavi') {
            $service['offers'] = array(
                array('@type' => 'Offer', 'name' => '初期費用（税込）', 'price' => '55000', 'priceCurrency' => 'JPY'),
                array('@type' => 'Offer', 'name' => '月額（税込）',     'price' => '11000', 'priceCurrency' => 'JPY'),
            );
        }
        $graph[] = $service;
    }

    return $graph;
}
