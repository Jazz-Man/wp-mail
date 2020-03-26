<?php

use JazzMan\WPMail\WPMail;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send mail, similar to PHP's mail.
 *
 * A true return value does not automatically mean that the user received the
 * email successfully. It just only means that the method used was able to
 * process the request without any errors.
 *
 * Using the two 'wp_mail_from' and 'wp_mail_from_name' hooks allow from
 * creating a from address like 'Name <email@address.com>' when both are set. If
 * just 'wp_mail_from' is set, then just the email address will be used with no
 * name.
 *
 * The default content type is 'text/plain' which does not allow using HTML.
 * However, you can set the content type of the email by using the
 * {@see 'wp_mail_content_type'} filter.
 *
 * The default charset is based on the charset used on the blog. The charset can
 * be set using the {@see 'wp_mail_charset'} filter.
 *
 * @param string|array $to array or comma-separated list of email addresses to send message
 * @param string $subject Email subject
 * @param string $message Message contents
 * @param string|array $headers Optional. Additional headers.
 * @param string|array $attachments Optional. Files to attach.
 *
 * @throws \PHPMailer\PHPMailer\Exception
 *
 * @return bool whether the email contents were sent successfully
 *
 * @since 1.2.1
 *
 * @global PHPMailer   $phpmailer
 */
function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
{
    /**
     * Filters the wp_mail() arguments.
     *
     * @param array $args a compacted array of wp_mail() arguments, including the "to" email,
     *                    subject, message, headers, and attachments values
     *
     * @since 2.2.0
     */
    $atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

    if (isset($atts['to'])) {
        $to = $atts['to'];
    }

    if (isset($atts['subject'])) {
        $subject = $atts['subject'];
    }

    if (isset($atts['message'])) {
        $message = $atts['message'];
    }

    if (isset($atts['headers'])) {
        $headers = $atts['headers'];
    }

    if (isset($atts['attachments'])) {
        $attachments = $atts['attachments'];
    }

    if (!is_array($attachments)) {
        $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
    }

    global $phpmailer;

    $phpmailer = new WPMail();

    try {
        $recipients = $phpmailer->formatEmail($to);

        foreach ($recipients as $recipient) {
            $phpmailer->addAddress($recipient['email'], $recipient['name']);
        }

        if (!empty($headers)) {
            $headers = $phpmailer->parseHeaders($headers);

            foreach ($headers as $header => $content) {
                switch ($header) {
                    case 'from':
                        $from = $phpmailer->formatEmail($content);

                        if (!empty($from)) {
                            $from = reset($from);

                            $phpmailer->setFrom($from['email'], $from['name']);
                        }

                        break;
                    case 'cc':

                        $cc = $phpmailer->formatEmail($content);

                        foreach ($cc as $item) {
                            $phpmailer->addCC($item['email'], $item['name']);
                        }

                        break;
                    case 'bcc':
                        $bcc = $phpmailer->formatEmail($content);

                        foreach ($bcc as $item) {
                            $phpmailer->addBCC($item['email'], $item['name']);
                        }
                        break;
                    case 'reply-to':

                        $reply_to = $phpmailer->formatEmail($content);

                        foreach ($reply_to as $item) {
                            $phpmailer->addReplyTo($item['email'], $item['name']);
                        }

                        break;
                    default:
                        $phpmailer->addCustomHeader($header, $content);
                        break;
                }
            }
        }

        // Set mail's subject and body
        $phpmailer->Subject = $subject;
        $phpmailer->msgHTML($message);

        if ('root@localhost' === $phpmailer->From) {
            $admin_email = get_bloginfo('admin_email');
            $from_name = get_bloginfo('name');

            if (!empty($admin_email)) {
                $from_email = $admin_email;
            } else {
                // Get the site domain and get rid of www.
                $sitename = strtolower($_SERVER['SERVER_NAME']);
                $sitename = ltrim($sitename, 'www.');

                $from_email = "wordpress@{$sitename}";
            }

            $from_email = apply_filters('wp_mail_from', $from_email);

            $from_name = apply_filters('wp_mail_from_name', $from_name);

            $phpmailer->setFrom($from_email, $from_name);
        }

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $phpmailer->addAttachment($attachment);
            }
        }

        /*
         * Fires after PHPMailer is initialized.
         *
         * @since 2.2.0
         *
         * @param PHPMailer $phpmailer The PHPMailer instance (passed by reference).
         */
        do_action_ref_array('phpmailer_init', [&$phpmailer]);

        return $phpmailer->send();
    } catch (Exception $e) {
        $mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
        $mail_error_data['phpmailer_exception_code'] = $e->getCode();

        /*
         * Fires after a phpmailerException is caught.
         *
         * @since 4.4.0
         *
         * @param WP_Error $error A WP_Error object with the phpmailerException message, and an array
         *                        containing the mail recipient, subject, message, headers, and attachments.
         */
        do_action('wp_mail_failed', new \WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));

        if (WP_DEBUG) {
            error_log($e);
        }

        return false;
    }
}
