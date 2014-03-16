#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';
require_once $_SERVER['HOME'].'/lib/common.php';
require_once $_SERVER['HOME'].'/lib/curl.php';
require_once $_SERVER['HOME'].'/lib/db.php';

// 引数でブログ指定
if (empty($argv[1])) {
    echo "[ERROR] empty blog argument\n";
    exit(1);
}
$blog = $argv[1];
require_once $_SERVER['HOME'].'/conf/mtm/constant_blog.php';

// 検索用パターン
define('ANCHOR_PATTERN', '@(&gt;&gt;(?P<anchor>\d{1,3}(</a>)?[,-]?\d{0,3}))@');
define('ANCHOR_ONLY_RES_PATTERN', '|^ <a href="../test/read.cgi/'.$blog.'/\d{10}/\d{1,3}" target="_blank">&gt;&gt;\d{1,3}</a> $|');
define('IMG_PATTERN', '@(?P<img>h?ttp://([\w\?\+\.\-/:!~&=_%#]+)\.(jpg|jpeg|png|gif))@i');
//define('LINK_PATTERN', '@(?P<link>h?ttps?://([\w\?\+\.\-/,:!~&=_%#]+))@i');
// 置換用パターン
//define('ANCHOR_LINK_PATTERN', '@(<a href=\"../test/read.cgi/'.$blog.'/\d{10}/[\d\-]{1,9}\" target=\"_blank\"\>|</a>)@');
define('ANCHOR_LINK_PATTERN', '@(<a href=\"../test/read.cgi/'.$blog.'/\d{10}/[\d\-]+\" target=\"_blank\"\>|</a>)@');
define('BE_IMG_URL_PATTERN', '|\w{3}p://img\.2ch\.net/ico/.+\.gif <br>|');
define('IMG_URL_PATTERN', '@>(http://([\w\?\+\.\-/,;:!~&=_%#]+)\.(jpg|jpeg|png|gif))@i');
define('YOUTUBE_URL_PATTERN', '@<a href=[\w\-:/\.\?=&#\" ]+>(http://www\.youtube\.com/watch\?v=([\w\-\?=&#]+))</a>@i');
define('NICONICO_URL_PATTERN', '@<a href=[\w:/\.=\" ]+>(http://www\.nicovideo\.jp/watch/(\w+))</a>@i');
define('LINK_URL_PATTERN', '@(https?://([\w\?\+\.\-/,;:!~&=_%#]+))@i');

if (checkKillSwitch(KILL_SWITCH)) exit;
//if (date('G') == 4) sleep(30); // 4時DB不安定のため
echoLogTime('s', $argv[0]);
$today = date('Ymd');
if (!checkDone(DONE_CRAWLER)) exit(1);

$db = new DB();
$db->selectDb(DB_NAME);

// URLリスト取得
$db->selectQuery(array('table' => 'url'));
$url_ary = array();
while($row = $db->fetchAssoc()) {
    $url_ary[$row['board']] = $row['url'];
}
// NG画像リスト取得
$db->selectQuery(array('table' => 'ng_image', 'order' => 'number'));
$ng_image_ary = array();
while($row = $db->fetchAssoc()) {
    $ng_image_ary[] = $row['url'];
}

// 記事化スレッドID取得
if (($id_ary = getFile(ID_LIST)) === false) exit(1);
//print_r($id_ary);

// 記事作成
foreach ($id_ary as $ia) {
    $res_ary = getResponseData($url_ary[$blog].'dat/'.$ia.'.dat');
    if ($res_ary === false) continue;
//print_r($res_ary);

    // 被アンカー取得
    foreach ($res_ary as $ra) {
        if ($ra['number'] == 0) continue;
        foreach ($ra['anchor'] as $raa) {
            if ($raa < $ra['number'])
                $res_ary[$raa]['inbound_anchor'][] = $ra['number'];
        }
    }
//print_r($res_ary);

    $article = generateArticle($res_ary, $source_url);
//echo $article."\n";
    if (!empty($article)) {
        $dir = BLOG_DIR.'/'.$today;
        if (!file_exists($dir)) mkdir($dir, 0755);
        writeFile($dir."/$ia.html", $article);
        echo "generate $ia\n";
    } else {
        echo "failed $ia\n";
    }
}

createDone(DONE_GENERATOR);
echoLogTime('e', $argv[0]);

// ---------------------------------------------------------
// datからレスデータを取得
function getResponseData($thread_url) {
    $thread = getWeb($thread_url);
    // 人大杉の場合HTML判定で弾く
    if (empty($thread) || strpos($thread, '<title>')) return false;
    $thread = convertUTF8($thread);
    $thr_ary = explode("\n", $thread);
//print_r($thr_ary);

    $response_ary = array(array('number'=>0)); // 配列引数とnumber合わせるため
    $i = 1;
    foreach ($thr_ary as $ta) {
        if (empty($ta)) break;
        list($name, $mail, $datetimeid, $res) = explode('<>', $ta);
//        $datetimeid = preg_replace('|<a href="http://2ch\.se/">.*</a> |', '', $datetimeid);
//        list($date, $time, $id, $be) = explode(' ', $datetimeid);
        preg_match('|\d{4}/\d{2}/\d{2}\(.*\) \d{2}:\d{2}:\d{2}.\d{2}|', $datetimeid, $matches);
        $datetime = $matches[0]; 
        if (preg_match('|ID:[\w\+/]{9}(●)?|', $datetimeid, $matches)) {
            $id = $matches[0]; 
        } else {
            $id = 'ID:???'; 
        }
        $anchor_ary = array();
        // アンカー解析 (ex. &gt;&gt;3-5</a>,6)
        if (preg_match_all(ANCHOR_PATTERN, $res, $matches)) {
//print_r($matches);
            foreach($matches['anchor'] as $an) {
                $an = str_replace('</a>', '', $an);
//echo "[$i : $an] ";
                $tmp_anchor = explode(',', $an);
                foreach ($tmp_anchor as $tan) {
//echo "($tan) ";
                    // -でつながっている場合を考慮
//                    list($btan, $atan) = explode('-', $tan);
                    $stan = explode('-', $tan);
                    $btan = current($stan);
                    $atan = next($stan);
                    if (!empty($atan) && $atan < $btan) {
                        $ttan = $btan; $btan = $atan; $atan = $ttan;
                    }
                    // -間が大きい場合はspamとみなす
                    if (!empty($atan) && $atan - $btan > ANCHOR_GAP_MAX) continue;
                    // -前を追加
                    if (!empty($btan) && $btan < $i) {
                        $anchor_ary[] = $btan;
//echo "$btan ";
                    }
                    // -間が小さい場合は間のレスもアンカーとみなす
                    if (!empty($atan) && $atan < $i && $atan - $btan <= ANCHOR_GAP_MIN) {
                        for ($itan = $btan+1; $itan <= $atan; $itan++) {
                            $anchor_ary[] = $itan;
//echo "$itan ";
                        }
                    }
                }
//echo "\n";
            }
        }
        // >>1のスパムのアンカーを削除
        $res_line = count(explode('<br>', $res));
        if (in_array(1, $anchor_ary)) {
            if (strpos($res, 'アフィ')) {
                echo "spam res!\n$res\n";
                $anchor_ary = array();
            } elseif ($res_line <= 2 && (strpos($res, '死ね') || strpos($res, '乙'))) {
                echo "spam res!\n$res\n";
                $anchor_ary = array();
            }
        }
        // 1行かつアンカーのみのレスのアンカーを削除
        if ($res_line == 1 && preg_match(ANCHOR_ONLY_RES_PATTERN, $res, $matches)) {
            echo "anchor only res!\n$res\n";
            $anchor_ary = array();
        }
        // 画像判定
        $img_ary = array();
        if (preg_match_all(IMG_PATTERN, $res, $matches)) {
//print_r($matches);
            $img_ary = $matches['img'];
        }
        // AA判定(マルチバイト文字とシングルバイト文字二種類)
        $aa_char_num = preg_match_all('|[／＼＿￣⌒┬┏┓┛┗┃´ヽヾゝ从乂彡≡〆∩⊂∪⊃∀∬Σ∂∇∞¶○◎■□◇★☆]|u', $res, $matches);
        $aa_char_num_second = preg_match_all('|[:;]|', $res, $matches);
        $aa_flg = ($aa_char_num > AA_CHECK_NUM || $aa_char_num_second > AA_CHECK_NUM_SECOND) ? true : false;

        $data_ary = array(
            'number'=>$i,
            'name'=>strip_tags($name),
            'mail'=>$mail,
            'datetime'=>$datetime,
            'id'=>substr($id, 3),
//            'be'=>substr($be, 3),
            'res'=>preg_replace(ANCHOR_LINK_PATTERN, '', trim($res)),
            'anchor'=>(count($anchor_ary) < SPAM_ANCHOR_NUM) ? array_unique($anchor_ary) : array(),
            'inbound_anchor'=>array(),
            'aa'=>$aa_flg,
            'img'=>$img_ary,
        );
        array_push($response_ary, $data_ary);
        $i++;
    }

    return $response_ary;
}

// レスの並びの多分木を作成
function setTree($node_ary, &$response_data_ary, &$already_ary) {
    $ret = array();
    foreach ($node_ary as $na) {
        if (!in_array($na, $already_ary)) {
            $already_ary[] = $na;
//echo "$na:\t\tleft[".implode(',',$response_data_ary[$na]['anchor'])."]\t\tright[".implode(',',$response_data_ary[$na]['inbound_anchor'])."]\n";
            $ret[$na] = array('left'=>setTree($response_data_ary[$na]['anchor'], $response_data_ary, $already_ary), 'right'=>setTree($response_data_ary[$na]['inbound_anchor'], $response_data_ary, $already_ary));
        }
    }

    return $ret;
}

// レスの並びを決定
function getResponseOrder($tree, $article_first_ary, &$order_ary, &$depth) {
    $depth++;
    foreach ($tree as $num => $t) {
        if ($depth == 1) $order_ary[] = 'x';
        if (!empty($t['left'])) {
            $order_ary[] = getResponseOrder($t['left'], $article_first_ary, $order_ary, $depth);
        }
        $order_ary[] = $num;
        if (!empty($t['right'])) {
            $order_ary[] = getResponseOrder($t['right'], $article_first_ary, $order_ary, $depth);
        }
    }
    $depth--;
}

// 記事前半部取得
function getArticleFirst($response_data_ary) {
    global $setting_ini;
    global $blog;
    $body = '';
    $number = array();
    $make_thread_name = '';
    foreach ($response_data_ary as $rda) {
        if ($rda['number'] < 1) continue;
        if ($rda['number'] >= 15) break;
        if ($rda['number'] == 1) {
            $body .= generateResponseHTML($rda, false);
            $number[] = $rda['number'];
            $make_thread_name = preg_replace('|依頼\d{0,4}＠|', '', $rda['name']);
        } elseif ($setting_ini[$blog]['cap'] && $rda['name'] == $make_thread_name) {
            $body .= generateResponseHTML($rda, false);
            $number[] = $rda['number'];
        } elseif (!$setting_ini[$blog]['cap'] && strpos($rda['res'], 'つづき') || strpos($rda['res'], 'の続き')) {
            $body .= generateResponseHTML($rda, false);
            $number[] = $rda['number'];
        }
    }
    $ret['body']   = $body;
    $ret['number'] = $number;
//print_r($ret['number']);

    return $ret;
}

// 記事作成
function generateArticle($response_data_ary, $source_url) {
    global $setting_ini;
    global $blog;
    $article_first = getArticleFirst($response_data_ary);

    $already_ary = array();
    $root_ary    = $article_first['number'];
    foreach ($response_data_ary as $rda) {
        if (in_array($rda['number'], $root_ary)) continue;
        if (!empty($rda['inbound_anchor']) && count($rda['inbound_anchor']) >= ROOT_RES_ANCHOR_NUM) {
            $root_ary[] = $rda['number'];
        }
    }
//print_r($root_ary);

    $response = array();
    $i = 0;

    $tree = array();
    $tree = setTree($root_ary, $response_data_ary, $already_ary);
//print_r($tree);
    $res_order_ary = array();
    $depth = 0;
    getResponseOrder($tree, $article_first['number'], $res_order_ary, $depth);
//print_r($res_order_ary);

    $res_cnt = count($response_data_ary) - 1;
//    $article = '<div class="res-cnt">'.$setting_ini[$blog]['board'].'板 '.$res_cnt.'レスまでのまとめ</div><br />'."\n";
    $article = '';
    $article .= $article_first['body']."<hr><!--more-->\n";
    $add_flg = true;
    foreach ($res_order_ary as $roa) {
        if (empty($roa)) {
            continue;
        } else if (in_array($roa, $article_first['number'])) {
            continue;
        } else if ($roa == 'x' && $add_flg) {
            $article .= "<br /><hr>\n";
            $add_flg = false;
        } else if ($roa != 'x') {
//        } else {
            $article .= generateResponseHTML($response_data_ary[$roa]);
            $add_flg = true;
        }
    }

    return $article;
}

// 各レスをHTMLに変換
function generateResponseHTML($response=NULL, $deco=true) {
    global $ng_image_ary;

    $number   = $response['number'];
    $res      = $response['res'];
    if (empty($res)) return '';
    // BE画像消去
    $res = preg_replace(BE_IMG_URL_PATTERN, '', $res);
    // h抜きリンク修正
    $res = preg_replace('|ttp:|', 'http:', $res);
    $res = preg_replace('|hhttp:|', 'http:', $res);
    // NG画像修正
    if (!empty($response['img'])) {
        foreach ($ng_image_ary as $ni) {
            $res = str_replace($ni, substr($ni, 1), $res);
        }
    }
    // リンク置換
    $res = preg_replace(LINK_URL_PATTERN, '<a href="${1}" target="blank" class="reslink">${1}</a>', $res);
    // YOUTUBE置換
    $res = preg_replace(YOUTUBE_URL_PATTERN, '<object width="560" height="340"><param name="movie" value="http://www.youtube.com/v/${2}?fs=1&amp;hl=ja_JP"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/${2}?fs=1&amp;hl=ja_JP" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="560" height="340"></embed></object>', $res);
    // ニコニコ置換
    $res = preg_replace(NICONICO_URL_PATTERN, '<script type="text/javascript" src="http://ext.nicovideo.jp/thumb_watch/${2}?w=520&h=340"></script><noscript>http://www.nicovideo.jp/watch/${2}</noscript>', $res);
    // 画像置換
    $res = preg_replace(IMG_URL_PATTERN, '>${1}</a><br /><a href="${1}" target="blank" class="nr"><img src="${1}" class="gz" />', $res);

    // タグ追加
    if ($response['aa']) {
        $style = 'long';
        $b_strong = $a_strong = '';
    } else if ($deco && count($response['inbound_anchor']) >= BEST_RES_ANCHOR_NUM) {
        $style = 'best';
        $b_strong = '<strong>'; $a_strong = '</strong>';
    } else if ($deco && count($response['inbound_anchor']) >= BETTER_RES_ANCHOR_NUM) {
        $style = 'better';
        $b_strong = '<strong>'; $a_strong = '</strong>';
    } else {
        $style = 'good';
        $b_strong = $a_strong = '';
    }

    $response_html = <<<__HTML__
<dt>{$number} :<span class="name">{$response['name']}</span> : {$response['datetime']} ID:{$response['id']}</dt>
<dd><span class="{$style}">{$b_strong}{$res}{$a_strong}</span></dd>

__HTML__;

    return $response_html;
}
