<?php
class Mailer {
    public function sendVerification($email, $code) {
        $subject = "Verify your GiveHub account";
        $message = "Your verification code is: $code\n\nYou may also visit: https://app.thegivehub.com/api/auth/request-verification?email={$email}&code={$code} to verify your email address.\n\n";
        $message .= "This code will expire in 1 hour.";
        
        $msg = <<<EOT
To: {$email}
From: The Give Hub <support@thegivehub.com>
Subject: {$subject}
Bcc: cdr@netoasis.net

        {$message}
EOT;

        $msg = escapeshellarg($msg);
        $cmd = "echo {$msg} |  /usr/sbin/exim -i -f\"The Give Hub <support@thegivehub.com>\" {$email}";
        $send = `$cmd`;

        file_put_contents(__DIR__."/x.log", $cmd."\n---\n".$msg."\n---\n", FILE_APPEND);

        // print $send;
    }
    
    public function sendEmail($to, $subject, $tpl, $obj) {
        $message= file_get_contents($tpl);
        $message = preg_replace_callback("/\%\%(.+?)\%\%/", function($m) use ($obj) {
            return $obj->{$m[1]} ?? '';
        });
        
        $msg = <<<EOT
To: {$to}
From: The Give Hub Support <support@thegivehub.com>
Subject: {$subject}
Bcc: cdr@netoasis.net

        {$message}
EOT;

        $msg = escapeshellarg($msg);
        $cmd = "echo {$msg} |  /usr/sbin/exim -i -f\"The Give Hub Support <support@thegivehub.com>\" {$to}";
        $send = `$cmd`;

        file_put_contents(__DIR__."/x.log", $cmd."\n---\n".$msg."\n---\n", FILE_APPEND);

        // print $send;
    }

    public function sendNotification($to, $subject, $message) {
        $msg = <<<EOT
To: {$to}
From: The Give Hub <support@thegivehub.com>
Subject: {$subject}
Bcc: cdr@netoasis.net

{$message}
EOT;

        $msg = escapeshellarg($msg);
        $cmd = "echo {$msg} |  /usr/sbin/exim -i -f\"The Give Hub <support@thegivehub.com>\" {$to}";
        $send = `$cmd`;

        file_put_contents(__DIR__."/x.log", $cmd."\n---\n".$msg."\n---\n", FILE_APPEND);
    }
}
