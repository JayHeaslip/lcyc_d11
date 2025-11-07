<?php
use PHPMailer\PHPMailer\PHPMailer;
require 'vendor/autoload.php'; // If PHPMailer is installed via Composer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'lcyc@members.lcyc.info'; // Replace with your username
    $mail->Password = '4nvfe1VE'; // Replace with your password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom('lcyc@members.lcyc.info', 'LCYC Admin');
    $mail->addAddress('jheaslip@comcast.net');
    $mail->Subject = 'Test SMTP Email';
    $mail->Body = 'This is a test email from PHPMailer.';
    $mail->send();
    echo 'Email sent successfully!';
} catch (Exception $e) {
    echo "Email failed: {$mail->ErrorInfo}";
}