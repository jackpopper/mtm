#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';
require_once $_SERVER['HOME'].'/lib/common.php';
require_once $_SERVER['HOME'].'/lib/curl.php';
require_once $_SERVER['HOME'].'/lib/atompub_ameba.php';
// ameba blog
define('AMEBA_BLOG_ID',   'hpf-news');
define('AMEBA_BLOG_PASS', 'takoyaki1206');
define('HPF',             'hogehogesokuhou');

echoLogTime('s', $argv[0]);

// RSSからHTML作成
$now_hour = date('G');
$html = '<ul class="hpf">';
$cnt = 0;
foreach (explode(',', HPF) as $blog) {
    $url = 'http://'.$blog.'.ldblog.jp/atom.xml';
    $rss = getWebAPI($url);
    $xml = simplexml_load_string($rss);                 
    foreach ($xml->entry as $entry) {
//print_r($entry);
        $time = strtotime($entry->issued);
        $hour = date('G', $time);
        if ($hour != $now_hour) break;
        $title = $entry->title;
        $link  = $entry->link->attributes()->href;
echo "[$title] $link\n";
        $html .= '<li><a href="'.$link.'" target="_blank">'.$title.'</a></li>';
        $cnt++;
    }
}
$html .= '</ul>';
// 更新なし
if ($cnt == 0) exit;

// 記事投稿
$ac = new AmebaAtomPubClient(AMEBA_BLOG_ID, AMEBA_BLOG_PASS);
$resorce_uri = $ac->getResorceUri();
if (empty($resorce_uri)) {
    echo "[ERROR] failed get resorce uri\n";
    exit(1);
}
$entry = array(
    'title'   => date('n月j日 G時').'のニュース',
    'content' => $html
);
$response = $ac->post($resorce_uri['post'], new AmebaAtomPubEntry($entry));
if ($response === false) echo "[ERROR] failed post\n";
else                     echo "[POST] success post\n";
//$obj = simplexml_load_string($response);
//print_r($obj);

echoLogTime('e', $argv[0]);
