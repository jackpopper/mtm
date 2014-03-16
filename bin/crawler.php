#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';
require_once $_SERVER['HOME'].'/lib/common.php';
require_once $_SERVER['HOME'].'/lib/curl.php';
require_once $_SERVER['HOME'].'/lib/yjdn.php';
require_once $_SERVER['HOME'].'/lib/db.php';

// 引数でブログ指定
if (empty($argv[1])) {
    echo "[ERROR] empty blog argument\n";
    exit(1);
}
$blog = $argv[1];
require_once $_SERVER['HOME'].'/conf/mtm/constant_blog.php';

if (checkKillSwitch(KILL_SWITCH)) exit;
//if (date('G') == 4) sleep(30); // 4時DB不安定のため
echoLogTime('s', $argv[0]);

$db = new DB();
$db->selectDb(DB_NAME);

// URLリスト取得
$db->selectQuery(array('table' => 'url'));                                                                           
$url_ary = array();                                                                                                  
while($row = $db->fetchAssoc()) {
    $url_ary[$row['board']] = $row['url'];                                                                           
}
// NGタイトルワード取得
$db->selectQuery(array('table' => 'ng_title', 'order' => 'number'));
$ng_title_ary = array();
while($row = $db->fetchAssoc()) {
    $ng_title_ary[] = $row['title'];
}
define('NG_PATTERN', '.*('.implode('|', $ng_title_ary).').*');
// カテゴリリスト取得
$db->selectQuery(array('table' => 'category', 'order' => 'number'));
$category_ary = array();
while($row = $db->fetchAssoc()) {
    $category_ary[] = array('category' => $row['category'], 'word' => $row['word']);
}

// スレッドデータ取得
$thr_ary = getThreadData($url_ary[$blog].'subject.txt');
if (empty($thr_ary)) {
    echo "[ERROR] empty thread data\n";
    exit;
}

// DB格納
$db->setTable($blog);

// 一旦全てのスレッドをdat落ちとする
$db->updateQuery(array('value' => array('down' => '1')));

foreach ($thr_ary as $ta) {
    $db->selectQuery(array('where' => 'id='.$ta['id']));
    $row = $db->fetchAssoc();

    if (empty($row)) {
echo 'new thread : '.$ta['title']."\n";
        $title_detail = analyzeTitle($ta['title']);
        // カテゴリ判定
        $category = '';
        if ($setting_ini[$blog]['category_check']) {
            foreach ($category_ary as $cat) {
                if (mb_ereg_match('.*'.$cat['word'].'.*', $title_detail['title'], 'ix')) {
                    $category = $cat['category'];
                    break;
                }
            } 
/*
            if (empty($category) && $setting_ini[$blog]['brackets'] && !empty($title_detail['brackets'])) {
                foreach ($category_ary as $cat) {
                    if (mb_ereg_match('.*'.$cat['word'].'.*', $title_detail['brackets'], 'ix')) {
                        $category = $cat['category'];
                        break;
                    }
                } 
            }
*/
        }
        if (empty($category)) $category = $setting_ini[$blog]['category_default'];
        $keyword = implode(',', $title_detail['keyword']);
        $value = array(
            'id' => $ta['id'],
            'title' => $db->getVarcharQueryStr($ta['title']),
            'res' => $ta['count'],
            'pre_res' => 0,
            'increased_res' => $ta['count'],
            'max_increased_res' => $ta['count'],
            'category' => $db->getVarcharQueryStr($category),
            'brackets' => $db->getVarcharQueryStr($title_detail['brackets']),
            'keyword' => $db->getVarcharQueryStr($keyword),
            'part' => $title_detail['part'],
            'ng' => $title_detail['ng'],
            'down' => 0,
            'article_id' => 0,
            'created_datetime' => 'NOW()',
            'updated_datetime' => 'NOW()',
        );
        $db->insertQuery(array('value' => $value));
    } else {
        $increased_res = $ta['count'] - $row['res'];
        $max_increased_res = ($increased_res > $row['max_increased_res'])
                                 ? $increased_res : $row['max_increased_res'];
        $value = array(
            'res' => $ta['count'],
            'pre_res' => $row['res'], // 本番投入まで
            'increased_res' => $increased_res,
            'max_increased_res' => $max_increased_res,
            'down' => 0,
            'updated_datetime' => 'NOW()',
        );
        $db->updateQuery(array('value' => $value, 'where' => 'id='.$row['id']));
    }
}

// 記事化ID取得
$id_ary = array();
$threshold_ary = $threshold_ini[$blog]['threshold'];
foreach ($threshold_ary as $th) {
    list($method_th, $res_th, $inc_res_th, $cat_th) = explode(',', $th);
    $where = 'down=0 AND ng=0 AND part=1 AND res>='.$res_th;
    if ($method_th === 'n') {
        $where .= ' AND pre_res<'.$res_th;
    } elseif ($method_th === 'v') {
        $where .= ' AND increased_res>='.$inc_res_th;
        if (!empty($cat_th)) $where .= ' AND category="'.$cat_th.'"';
    }
    $db->selectQuery(array('where' => $where, 'order' => 'increased_res DESC'));
    $num = $db->numRow();
    for($i = 0; $i < $num; $i++) {
        $row = $db->fetchAssoc();
        $id_ary[] = $row['id'];
echo $row['id'].':'.$row['title'].'('.$row['res'].")\n";
    }
}
$result_ary = array_unique($id_ary);
//print_r($result_ary);

// id_list作成
writeFile(ID_LIST, $result_ary);

createDone(DONE_CRAWLER);
echoLogTime('e', $argv[0]);

// ---------------------------------------------------------
function getThreadData($subject_url) {
    $subject = getWeb($subject_url);
    // 人大杉の場合HTML判定で弾く
    if (empty($subject) || strpos($subject, '<title>')) return false;
    $subject = convertUTF8($subject);
    $sub_ary = explode("\n", $subject);

    $thread_ary = array();
    foreach ($sub_ary as $sa) {
        if (empty($sa)) break;
        preg_match('|^(?P<id>\d{10})\.dat\<\>(?P<title>.+)\((?P<count>\d{1,4})\)$|', $sa, $matches);
        // 2ch広告スレッドは無視
        if ($matches['id'] > 2147483647) continue;
//echo $matches['id']." : ".$matches['title']." : ".$matches['count']."\n";
        $data_ary = array(
            'id'=>$matches['id'],
            'title'=>$matches['title'],
            'count'=>$matches['count']
        );
        array_push($thread_ary, $data_ary);
    }

    return $thread_ary;
}

function analyzeTitle($title) {
    // 文字列最後のTwitter関連消去(@xxx #xxx)
    $title = preg_replace('|[@#][_\w]+$|', '', $title);
    // 全角半角変換
    $title = mb_convert_kana($title, 'KVas');
    $ret['title'] = $title;
    // NGワード判定
    $ret['ng'] = 0;
    if (mb_ereg_match('.*'.NG_PATTERN.'.*', $title, 'ix')) {
        $ret['ng'] = 1;
        echo "[INFO] NG word HIT: ".$title."\n";
    }
    // YJDNからキーワード取得
    $ret['keyword'] = getKeyword($title);
    // スペース消し
    $title = str_replace(' ', '', $title);
    // パート取得
    $ret['part'] = 1;
    if (preg_match('|★[\d]+|', $title, $matches)) {
        $ret['part'] = str_replace('★', '', $matches[0]);
    }
    // 最初の【】内取得
    $ret['brackets'] = '';
    if (preg_match('|^【(?:.+)】|', $title, $matches)) {
        $ret['brackets'] = str_replace('【', '', str_replace('】', '', $matches[0]));
    }

    return $ret;
}
