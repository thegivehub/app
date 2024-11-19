<?php
class Mailer {
    public function sendVerification($email, $code) {
        $subject = "Verify your GiveHub account";
        $message = "Your verification code is: $code\n\n";
        $message .= "This code will expire in 1 hour.";
        
        $msg = <<<EOT
To: {$email}
From: The Give Hub <support@thegivehub.com>
Subject: {$subject}
Bcc: cdr@netoasis.net

        {$message}
EOT;

        $msg = escapeshellarg($msg);
        $cmd = "echo {$msg} |  /usr/sbin/exim -i -t {$email}";
        $send = `$cmd`;

        file_put_contents(__DIR__."/x.log", $cmd."\n---\n".$msg."\n---\n", FILE_APPEND);

        // print $send;
    }
}
