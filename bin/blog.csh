#!/bin/csh -f
if ($#argv != 1) then
    echo "empty blog arg!"
    exit
endif

set BLOG=$1
cd /home/jackpopper/bin/mtm
./crawler.php $BLOG
./generator.php $BLOG
./writer.php $BLOG
