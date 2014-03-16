#!/usr/bin/php
<?php
require_once $_SERVER['HOME'].'/conf/mtm/constant.php';

$blog_ary = explode("\t", BLOG_CHECK_STR);
//print_r($blog_ary);

$subject = '[ERROR] '.date('Y/m/d H:i:s');
$message = '';
foreach($blog_ary as $blog) {
    $log = LOG_DIR.'/'.$blog.'.log';
    $cmd = '/bin/cat '.$log.' | grep ERROR';
    $error = `$cmd`;
    if ($error) {
        $message .= $blog.'.log'."\n".$error."\n\n";
    }
}

if (!empty($message)) {
    mail(MAIL_ADDRESS, $subject, $message);
}
