<?php
set_time_limit(0);
date_default_timezone_set('UTC');

require 'vendor/autoload.php';

echo "=========================================\n";
echo "|\tInstagram Live Custom v1.2\t|\n";
echo "=========================================\n";
echo "| Masukkan username anda : "; $username = trim(fgets(STDIN));
echo "| Masukkan password anda : "; $password = trim(fgets(STDIN));
echo "| Masukkan lokasi dir ffmpeg ( Untuk windows, tambahkan dengan namafile+ekstensionnya ) : "; $ffmpegPaths = trim(fgets(STDIN));
echo "=========================================\n";
if($username && $password && $ffmpegPaths) {
    echo "| Mencoba login... ";
    $ig = new \InstagramAPI\Instagram(false, false);
	try {
	    $ig->login($username, $password);
	    echo "OK !\n";
	} catch (\Exception $e) {
        echo "GAGAL ! [ ".$e->getMessage()." ]\n";
	    die;
    }
    echo "| Masukkan link video ( direct link video ) : "; $linkvideo = trim(fgets(STDIN));
    echo "=========================================\n";
    if($linkvideo) {
        try {
            $ffmpegPath = $ffmpegPaths;
            $ffmpeg = \InstagramAPI\Media\Video\FFmpeg::factory($ffmpegPath);
            $stream = $ig->live->create();
            $broadcastId = $stream->getBroadcastId();
            echo "| Broadcast ID : $broadcastId\n";
            echo "=========================================\n";
            $ig->live->start($broadcastId);
            $streamUploadUrl = $stream->getUploadUrl();
            $broadcastProcess = $ffmpeg->runAsync(sprintf(
                '-rtbufsize 256M -re -i %s -acodec libmp3lame -ar 44100 -b:a 128k -pix_fmt yuv420p -profile:v baseline -s 720x1280 -bufsize 6000k -vb 400k -maxrate 1500k -deinterlace -vcodec libx264 -preset veryfast -g 30 -r 30 -f flv %s',
                \Winbox\Args::escape($linkvideo),
                \Winbox\Args::escape($streamUploadUrl)
            ));
    
            $lastCommentTs = 0;
            $lastLikeTs = 0;
            do {
                $commentsResponse = $ig->live->getComments($broadcastId, $lastCommentTs);
                $systemComments = $commentsResponse->getSystemComments();
                $comments = $commentsResponse->getComments();
                if (!empty($systemComments)) {
                    $lastCommentTs = $systemComments[0]->getCreatedAt();
                }
                if (!empty($comments) && $comments[0]->getCreatedAt() > $lastCommentTs) {
                    $lastCommentTs = $comments[0]->getCreatedAt();
                }
                if(!empty($comments)) {
                    foreach ($comments as $comment) {
                        echo "| [@{$comment->getUser()->getUsername()}] {$comment->getText()}\n";
                    }
                }
                $heartbeatResponse = $ig->live->getHeartbeatAndViewerCount($broadcastId);
                if ($heartbeatResponse->isIsPolicyViolation() && (int) $heartbeatResponse->getIsPolicyViolation() === 1) {
                    echo 'Instagram has flagged your content as a policy violation with the following reason: '.($heartbeatResponse->getPolicyViolationReason() == null ? 'Unknown' : $heartbeatResponse->getPolicyViolationReason())."\n";
                    if (true) {
                        $ig->live->getFinalViewerList($broadcastId);
                        $ig->live->end($broadcastId, true);
                        exit(0);
                    }
                    $ig->live->resumeBroadcastAfterContentMatch($broadcastId);
                }
                $likeCountResponse = $ig->live->getLikeCount($broadcastId, $lastLikeTs);
                $lastLikeTs = $likeCountResponse->getLikeTs();
                $ig->live->getJoinRequestCounts($broadcastId);
                sleep(2);
            } while ($broadcastProcess->isRunning());
            $ig->live->getFinalViewerList($broadcastId);
            $ig->live->end($broadcastId);
            $ig->live->addToPostLive($broadcastId);
        } catch (\Exception $e) {
            echo "| ERROR LIVE : ".$e->getMessage()."\n";
        }
        echo "=========================================\n";
    } else {
        echo "| Kalo lo gamasukin itu link, lo mau ngapain pake ni sc ?";
        echo "=========================================\n";
    }
} else {
    echo "| Mohon masukkan dengan benar.\n";
    echo "=========================================\n";
}