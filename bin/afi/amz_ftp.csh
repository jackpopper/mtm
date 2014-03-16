#!/bin/csh -f
# amazonランキングHTMLFTP送信
set hostname=ftp.blog.livedoor.com
set user=hogehogesokuhou
set pass=takoyaki1206

lftp $hostname -u $user,$pass<< _EOD
cd amarank
put /home/jackpopper/var/amazon/html/anime-a.html
put /home/jackpopper/var/amazon/html/anime-b.html
put /home/jackpopper/var/amazon/html/game-a.html
put /home/jackpopper/var/amazon/html/game-b.html
put /home/jackpopper/var/amazon/html/comic-a.html
put /home/jackpopper/var/amazon/html/comic-b.html
put /home/jackpopper/var/amazon/html/hobby-a.html
put /home/jackpopper/var/amazon/html/hobby-b.html
put /home/jackpopper/var/amazon/html/novel-a.html
put /home/jackpopper/var/amazon/html/novel-b.html
put /home/jackpopper/var/amazon/html/fmovie-a.html
put /home/jackpopper/var/amazon/html/fmovie-b.html
put /home/jackpopper/var/amazon/html/dmovie-a.html
put /home/jackpopper/var/amazon/html/dmovie-b.html
put /home/jackpopper/var/amazon/html/idoldvd-a.html
put /home/jackpopper/var/amazon/html/idoldvd-b.html
put /home/jackpopper/var/amazon/html/idolphoto-a.html
put /home/jackpopper/var/amazon/html/idolphoto-b.html
put /home/jackpopper/var/amazon/html/all.html
bye
_EOD
