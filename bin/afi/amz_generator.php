#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/amazon/constant.php';
require_once $_SERVER['HOME'].'/lib/common.php';
require_once $_SERVER['HOME'].'/lib/db.php';
define('NOSIM', '/ref=nosim');
define('TAG', '&tag=mytk04-22');

echoLogTime('s', $argv[0]);

$category = parse_ini_file('amz_category.ini', true);
$allHtml = <<<__ALL__
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>カテゴリ別Amazonランキング - hogehoge速報</title>
<style type="text/css">
img {border:none;}
a {text-decoration:none;}
table {text-align:center;border-spacing:3px 0;font-size:16px;}
td {width:185px;padding:0;vertical-align:top;}
.rankheader {margin-bottom:5px;}
.ranktype {font-size:20px;font-weight:bold;}
</style>
</head>
<body>
__ALL__;

$db = new DB();
$db->selectDb(DB_NAME);
foreach ($category as $key => $val) {
    echo "$key : ".$val['category']." : ".$val['node']." : ".$val['name']."\n";
    $ranking = array();
    $query = array(
        'table' => $val['category'],
        'where' => "node='".$val['node']."' AND type='bestsellers'",
        'order' => 'rank',
    );
    $db->selectQuery($query);
    while ($row = $db->fetchAssoc()) {
        $ranking[] = $row;
    }

    $html = generateAmazonRankingHtml($ranking, $key, $val['name'], 0, $allHtml);
    writeFile("/home/jackpopper/var/amazon/html/".$key."-a.html", $html);
    $html = generateAmazonRankingHtml($ranking, $key, $val['name'], 1, $allHtml);
    writeFile("/home/jackpopper/var/amazon/html/".$key."-b.html", $html);
}

$allHtml .= <<<__ALL__
</body></html>
__ALL__;
writeFile("/home/jackpopper/var/amazon/html/all.html", $allHtml);

echoLogTime('s', 'amz_ftp.csh');
`/home/jackpopper/bin/mtm/afi/amz_ftp.csh`;
echoLogTime('e', 'amz_ftp.csh');
echoLogTime('e', $argv[0]);

//------------------------------------------------------------------------------------
function generateAmazonRankingHtml($ranking, $key, $name, $num = 0, &$all) {
    if ($num == 0) {
        $start = 1; $end = 5; $astart = 6; $aend = 10; $ahtml = 'b';
        $all .= <<<__ALL__
<div class="rankheader">
<span class="ranktype">Amazon{$name}ランキング</span>
</div>
<table>
<tr>
__ALL__;
    } else if ($num == 1) {
        $start = 6; $end = 10; $astart = 1; $aend = 5; $ahtml = 'a';
        $all .= <<<__ALL__
<table>
<tr>
__ALL__;
    }
    $html = <<<__HTML__
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Amazon{$name}ランキング - hogehoge速報</title>
<style type="text/css">
img {border:none;}
a {text-decoration:none;}
table {text-align:center;border-spacing:1px 0;font-size:14px;}
td {width:140px;padding:0;vertical-align:top;}
.rankheader {margin-bottom:5px;}
.ranktype {font-size:16px;font-weight:bold;}
.categorylink {float:right;font-size:16px;}
</style>
</head>
<body>
<div class="rankheader">
<span class="ranktype">Amazon{$name}ランキング</span>
<span class="categorylink"><a href="{$key}-{$ahtml}.html">{$astart}～{$aend}位</a>　<a href="/amarank/all.html" target="_blank">カテゴリ一覧</a></span>
</div>
<table>
<tr>
__HTML__;
    for ($rnum = $start; $rnum <= $end; $rnum++) {
        $html .= "<th>".$rnum."位</th>";
        $all  .= "<th>".$rnum."位</th>";
    }
    $html .= '</tr><tr>';
    $all  .= '</tr><tr>';

    $i = 0;
    foreach ($ranking as $r) {
        $i++;
        if (!($i >= $start && $i <= $end)) {
            continue;
        }
        $imageURL = ($r['image'] == '') ? AMAZON_NO_IMAGE_URL : str_replace('SL160', 'AA130', $r['image']);
        $allImageURL = ($r['image'] == '') ? AMAZON_NO_IMAGE_URL : str_replace('SL160', 'AA185', $r['image']);
        $html .= '<td><a href="'.$r['link'].TAG.'" target="_blank"><img src="'.$imageURL.'"></a>'
              .  '<br /><a href="'.$r['link'].TAG.'" target="_blank">'.$r['title'].'</a></td>'."\n";
        $all  .= '<td><a href="'.$r['link'].TAG.'" target="_blank"><img src="'.$allImageURL.'"></a>'
              .  '<br /><br /><a href="'.$r['link'].TAG.'" target="_blank">'.$r['title'].'</a></td>'."\n";
echo $r['title']."\n";
    }

    $html .= '</tr></table></body></html>';
    $all  .= '</tr></table>';
    if ($num == 1) $all .= '<hr>';

    return $html;
}
