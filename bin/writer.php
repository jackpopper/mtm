#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';
require_once $_SERVER['HOME'].'/lib/common.php';
require_once $_SERVER['HOME'].'/lib/curl.php';
require_once $_SERVER['HOME'].'/lib/db.php';
require_once $_SERVER['HOME'].'/lib/amazon.php';
require_once $_SERVER['HOME'].'/lib/atompub_livedoor.php';
define('RETRY', 3);

// livedoor blog メンテナンス用
//if (date('YmdH') >= MENTE_START && date('YmdH') <= MENTE_END) exit;
if (empty($argv[1])) {
    echo "[ERROR] empty blog argument\n";
    exit(1);
}
$blog = $argv[1];
require_once $_SERVER['HOME'].'/conf/mtm/constant_blog.php';

if (checkKillSwitch(KILL_SWITCH)) exit;
//if (date('G') == 4) sleep(30); // 4時DB不安定のため
echoLogTime('s', $argv[0]);
$today = date('Ymd');
if (!checkDone(DONE_GENERATOR)) exit(1);

// リソースURI取得
$ac = new LivedoorAtomPubClient(BLOG_ID, BLOG_APIKEY);
for ($i = 0; $i < RETRY; $i++) {
    $resorce_uri = $ac->getResorceUri();
    if (!empty($resorce_uri)) break;
    sleep(1);
}
if (empty($resorce_uri)) {
    echo "[ERROR] failed get resorce uri\n";
    exit(1);
}

// 記事化スレッドID取得
if (($id_ary = getFile(ID_LIST)) === false) exit(1);
//print_r($id_ary);

// スマホ用AD作成(hogehogesokuhou)
$sp_ad = <<<__AD__
<script src='http://a.t.webtracker.jp/js/a.js' type='text/javascript' charset='utf-8'></script>
<div class="ad_frame sid_beb1baa89e74f21ebd6176a42c402fadcd4af7d2607f6233 container_div color_#0000CC-#444444-#FFFFFF-#0000FF-#009900 sp"></div>
__AD__;

// DB接続
$db = new DB();
$db->selectDb(DB_NAME);
$db->setTable($blog);

// URLリスト取得
$db->selectQuery(array('table' => 'url'));
$url_ary = array();
while($row = $db->fetchAssoc()) {
    $url_ary[$row['board']] = $row['url'];
}

// 記事投稿
foreach ($id_ary as $ia) {
    // DBからデータ取得
    $thread_data_ary = getThreadDataFromDb($db, $ia);
    if (empty($thread_data_ary)) {
        continue;
    }
//print_r($thread_data_ary);
    $title  = $thread_data_ary['title'];
    $cat_id = $thread_data_ary['category'];
    $res    = $thread_data_ary['res'];
    // カテゴリ取得
    if (!empty($category_ini[$cat_id]['name'])) $category = $category_ini[$cat_id]['name'];
    else $category = 'ニュース';
    $article_id = $thread_data_ary['article_id'];
    $content_file = BLOG_DIR."/$today/$ia.html";
    // HTMLファイルが見つからない場合とばす
    if (!file_exists($content_file)) {
        echo "[ERROR] not found ".$ia.".html\n";
        continue;
    }
    $content = `/bin/cat $content_file`;
    list($body, $more) = preg_split('/<!--more-->/', $content);
echo "id:$ia title:$title\n";
    // キーワードからAmazonリンク作成、追加
    if (!empty($thread_data_ary['keyword'])) {
        $keyword_ary = explode(',', $thread_data_ary['keyword']);
        foreach ($keyword_ary as $key) {
            $key = str_replace(' ', '', $key);
            $amazon = searchAmazon($key);
            if ($amazon) {
                $more .= genAmazonHTML($amazon);
echo "key:$key\n";
                break;
            }
        }
    }
    // 引用元追記
    $source_url = $url_ary[$blog].'test/read.cgi/'.$blog.'/'.$ia.'/';
    $more .= '<div class="quote">引用元：<a href="'.$source_url.'">'.$source_url.'</a></div>';

    // スマホ用AD追加
//    $body = $sp_ad."\n".$body;

    // 記事作成時間
//    $published = date('c', strtotime($thread_data_ary['created_datetime']));

    $entry = array(
        'title' => $title,
        'body'  => $body,
        'more'  => $more,
    );
    sleep(1);
    if (empty($article_id)) { // post
        $entry['category'] = array($category, $setting_ini[$blog]['board']);
        $post_flag = false;
        for ($i = 0; $i < RETRY; $i++) {
            $response = $ac->post($resorce_uri['article']['uri'], new LivedoorAtomPubEntry($entry));
            if ($response !== false) {
                echo '[POST] '.$entry['title']."\n";
                $post_flag = true;
                break;
            }
        }
        if (!$post_flag) {
            echo "[WARN] failed post\n";
            continue;
        }
        $obj = simplexml_load_string($response);
        preg_match('|\d{7,9}|', $obj->id, $matches);
        $article_id = $matches[0];
        insertArticleId($db, $ia, $article_id);
    } else { // put
        $response = $ac->put($resorce_uri['article']['uri'].'/'.$article_id, new LivedoorAtomPubEntry($entry));
        if ($response === false) {
            echo "[ERROR] failed put article_id=".$article_id."\n";
            continue;
        } else {
            echo '[PUT] '.$resorce_uri['article']['uri'].'/'.$article_id."\n\n";
        }
    }
    updatePreRes($db, $ia, $res);
//echo $response;
}

echoLogTime('e', $argv[0]);

// ---------------------------------------------------------
// DBからスレッドデータ取得
function getThreadDataFromDb($db, $id) {
    $db->selectQuery(array('where' => "id=$id"));
    $num = $db->numRow();
    if ($num > 0) {
        $row = $db->fetchAssoc();
        $thread_data_ary = array(
            'id' => $row['id'],
            'title' => $row['title'],
            'res' => $row['res'],
            'keyword' => $row['keyword'],
            'category' => $row['category'],
            'article_id' => $row['article_id'],
            'created_datetime' => $row['created_datetime'],
        );
    } else {
        echo "[ERROR] not found thread data id=$id in DB\n";
        return false;
    }

    return $thread_data_ary;
}

// DBに記事ID追加
function insertArticleId($db, $id, $article_id) {
    $value = array('article_id' => $article_id);
    $db->updateQuery(array('value' => $value, 'where' => "id=$id"));
}

// DBに記事追加時点でのレス数追加
function updatePreRes($db, $id, $res) {
    $value = array('pre_res' => $res);
    $db->updateQuery(array('value' => $value, 'where' => "id=$id"));
}

// AmazonHTML作成
function genAmazonHTML($data) {
    if (empty($data['ImageURL'])) $data['ImageURL'] = 'http://g-ec2.images-amazon.com/images/G/09/nav2/dp/no-image-no-ciu._V192259616_AA100_.gif';
    $html = <<<__HTML__
<br />
<table class="amz"><tr><td>
<a href="{$data['DetailPageURL']}" target="_blank" class="nr"><img src="{$data['ImageURL']}" alt="{$data['Title']}"></a>
</td><td>
<a href="{$data['DetailPageURL']}" target="_blank" class="nr">{$data['Title']}</a>
</td></tr></table>
__HTML__;
    return $html;
}
