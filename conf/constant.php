<?php
define('USER', 'jackpopper');
define('MAIL_ADDRESS', 'miyazaki.takayuki@i.softbank.jp');
define('BLOG_CHECK_STR', "news\tbizplus\tscienceplus\tnewsplus\tnews4plus\tmnewsplus\tmoeplus\tdqnplus");

// file,directory
define('HOME',    '/home/'.USER);
define('BIN_DIR',  HOME.'/bin/mtm');
define('CONF_DIR', HOME.'/conf/mtm');
define('LOG_DIR',  HOME.'/log/mtm');
define('VAR_DIR',  HOME.'/var/mtm');
//define('NG_IMAGE',     CONF_DIR.'/ng_image.txt');
//define('NG_TITLE',     CONF_DIR.'/ng_title.txt');
define('CATEGORY_DIR', CONF_DIR.'/category');

// ini file
$setting_ini   = parse_ini_file(CONF_DIR.'/setting.ini', true);
$threshold_ini = parse_ini_file(CONF_DIR.'/threshold.ini', true);
$category_ini  = parse_ini_file(CONF_DIR.'/category.ini', true);

// DB
define('DB_HOST', '');
define('DB_USER', USER);
define('DB_PASS', 'taka1206');
define('DB_NAME', 'mtm');

// generater
define('ANCHOR_GAP_MAX', 50);
define('ANCHOR_GAP_MIN', 5);
define('SPAM_ANCHOR_NUM', 5);
define('ROOT_RES_ANCHOR_NUM', 2);
define('BEST_RES_ANCHOR_NUM', 5);
define('BETTER_RES_ANCHOR_NUM', 2);
define('AA_CHECK_NUM', 5);
define('AA_CHECK_NUM_SECOND', 30);

// livedoor blog
define('BLOG_ID',     '');
define('BLOG_PASS',   '');
define('BLOG_APIKEY', '');
define('BLOG_URL',    '');

// blog maintenance datetime
//define('MENTE_START', 2011091401);
//define('MENTE_END',   2011091409);
