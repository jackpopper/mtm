#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';
require_once $_SERVER['HOME'].'/lib/common.php';
require_once $_SERVER['HOME'].'/lib/curl.php';
require_once $_SERVER['HOME'].'/lib/db.php';
require_once $_SERVER['HOME'].'/lib/bitly.php';
require_once $_SERVER['HOME'].'/lib/twitter.php';
define('BUZZ_URL', 'http://realtime.search.yahoo.co.jp/search');
define('BUZZ_PATTERN', '|<li><a href="http://realtime\.search\.yahoo\.co\.jp/search\?p=[\w\d%]+&amp;ei=UTF-8&amp;rkf=1">(?P<buzz>.{1,45})</a></li>|');
define('BUZZ_TXT', '/home/jackpopper/var/mtm/buzz.txt');

// 注目のキーワード取得
$html = getWeb(BUZZ_URL);
if (empty($html)) {
    exit(1);
}
preg_match_all(BUZZ_PATTERN, $html, $matches);
echo "注目のキーワード\n";
print_r($matches['buzz']);

// 過去分にないキーワードのみ抽出
$buzzTxt = getFile(BUZZ_TXT);
$prevBuzz = array();
foreach ($buzzTxt as $line) {
    $prevBuzz = array_merge($prevBuzz, explode("\t", $line));
}
$buzzAry = array_diff($matches['buzz'], $prevBuzz);
echo "新規の注目のキーワード\n";
print_r($buzzAry);
// buzzファイル作成
if (count($buzzTxt) >= 6) array_shift($buzzTxt);
array_push($buzzTxt, implode("\t", $matches['buzz']));
writeFile(BUZZ_TXT, $buzzTxt);

// キーワードにヒットする記事取得
$db = new DB();
$db->selectDb(DB_NAME);
$db->setTable('newsplus');
foreach ($buzzAry as $buzz) {
    $query = array(
        'where' => "article_id!=0 AND keyword like '$buzz'",
        'order' => 'created_datetime desc',
        'limit' => 1
    );
    $db->selectQuery($query);
    $num = $db->numRow();
    if ($num > 0) {
        $row = $db->fetchAssoc();
        // ツイート
        $tweet = '【'.$buzz.'】の記事'.$row['title'].' '.shortenURL(BLOG_URL.'/archives/'.$row['article_id'].'.html');
echo mb_strlen($tweet)."文字\n";
echo "$tweet\n";
//        postTwitter($tweet);
    }
}
