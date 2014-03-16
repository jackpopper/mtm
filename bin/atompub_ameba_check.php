#!/usr/bin/php
<?php
/*
 * ameba blog atompub api checker
 * param: operation (get|del)
 * param: article_id (null OK)
 *
 */
require_once $_SERVER['HOME'].'/lib/atompub_ameba.php';
define('AMEBA_BLOG_ID',   'hpf-news');
define('AMEBA_BLOG_PASS', 'takoyaki1206');

$ac = new AmebaAtomPubClient(AMEBA_BLOG_ID, AMEBA_BLOG_PASS);
$resorce_uri = $ac->getResorceUri();

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
