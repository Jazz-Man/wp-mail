<?php

namespace JazzMan\WPMail;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Class WPMail.
 */
class WPMail extends PHPMailer
{
    /**
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
     *
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function __construct($exceptions = true)
    {
        parent::__construct($exceptions);

        // Empty out the values that may be set
        $this->clearAllRecipients();
        $this->clearAttachments();
        $this->clearCustomHeaders();
        $this->clearReplyTos();
        $this->setSmtpConfig();

        $charset = self::CHARSET_UTF8;

        $this->CharSet = apply_filters('wp_mail_charset', $charset);
    }

    /**
     * Formats recipient email.
     *
     * @param string      $email
     * @param string|null $name
     *
     * @return array
     */
    public function formatEmail(string $email, string $name = null)
    {
        $emails = [];

        $bracket_pos = '/^.*[>].*[,].*$/';

        preg_match($bracket_pos, $email, $matches);

        if (!empty($matches)) {
            $_content = explode('>', $email);
            $_content = array_filter($_content);

            foreach ($_content as $item) {
                $item = ltrim($item, ',');
                $item .= '>';

                $emails[] = $this->parseEmail($item, $name);
            }
        } else {
            $emails[] = $this->parseEmail($email, $name);
        }

        return $emails;
    }

    /**
     * @param string|array $headers
     *
     * @return array
     */
    public function parseHeaders($headers)
    {
        if (\is_array($headers)) {
            $headers = implode(self::CRLF, $headers);
        }

        $headers = $this->DKIM_HeaderC($headers);

        $headers = explode(self::CRLF, $headers);

        $headers = array_filter($headers);

        $_headers = [];

        foreach ($headers as $header) {
            [$name, $content] = explode(':', trim($header), 2);

            $name = trim($name);
            $content = trim($content);

            $_headers[strtolower($name)] = $content;
        }

        return $_headers;
    }

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function setSmtpConfig()
    {
        if (!\defined('WP_MAIL_SMTP_URL')) {
            $error_message = "'WP_MAIL_SMTP_URL' no defined";

            $this->setError($error_message);
            $this->edebug($error_message);

            throw new Exception($error_message);
        }

        $dsn = (object) parse_url(WP_MAIL_SMTP_URL);

        $this->isSMTP();
        $this->Host = $dsn->host;
        $this->Port = $dsn->port;
        $this->Username = $dsn->user;
        $this->Password = $dsn->pass;
        $this->SMTPAuth = true;

        $this->SMTPSecure = $dsn->scheme;

        if (WP_DEBUG && (\defined('WP_MAIL_SMTP_DEBUG') && WP_MAIL_SMTP_DEBUG)) {
            $this->Debugoutput = 'error_log';
            $this->SMTPDebug = \defined('WP_MAIL_SMTP_DEBUG_LEVEL') ? WP_MAIL_SMTP_DEBUG_LEVEL : 1;
        }
    }

    /**
     * @param string      $email
     * @param string|null $name
     * @return array
     */
    private function parseEmail(string $email, string $name = null)
    {
        if (!$name && preg_match('/(?<name>.+)?<(?<email>(.+))>/', $email, $matches)) {
            $name = !empty($matches['name']) ? trim($matches['name']) : '';
            $email = !empty($matches['email']) ? trim($matches['email']) : '';
        }

        return compact('name', 'email');
    }
}
