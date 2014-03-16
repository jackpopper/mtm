#!/usr/bin/php
<?php
/*
 * livedoor blog atompub api checker
 * param: operation (get|del)
 * param: article_id (null OK)
 *
 */
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';
require_once $_SERVER['HOME'].'/lib/atompub_livedoor.php';

$ac = new LivedoorAtomPubClient(BLOG_ID, BLOG_APIKEY);
$resorce_uri = $ac->getResorceUri();
if (empty($resorce_uri)) {
    echo "[ERROR] failed get resorce uri\n";
    exit(1);
}

if ($argv[1] == 'get') {
    if (empty($argv[2])) {
        $response = $ac->get($resorce_uri['article']['uri']);
    } else {
        $article_id = $argv[2];
        $response = $ac->get($resorce_uri['article']['uri'].'/'.$article_id);
    }
} else if ($argv[1] == 'del') {
    if (!empty($argv[2])) {
        $article_id = $argv[2];
        $response = $ac->delete($resorce_uri['article']['uri'].'/'.$article_id);
    }
} else {
    echo "set argv[1] get or del\n";
    print_r($resorce_uri);
    exit(1);
}
echo $response;
