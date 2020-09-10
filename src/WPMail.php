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
     * @param string|array $address
     * @param string|null $recipient
     *
     * @return array
     */
    public function formatEmail($address, $recipient = null)
    {
        $emails = [];

        if (!\is_array($address)) {
            $address = explode(',', $address);
        }

        foreach ($address as $item) {
            if ($email = $this->parseEmail($item, $recipient)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * @param string|null $string
     *
     * @return string
     */
    public function trim($string = '')
    {
        return trim(preg_replace('/\s{2,}/siu', ' ', $string));
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
            [$name, $content] = explode(':', $this->trim($header), 2);

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
            $this->addErrore("'WP_MAIL_SMTP_URL' no defined");
        }

        $dsn = (object) parse_url(WP_MAIL_SMTP_URL);

        $this->isSMTP();

        if (empty($dsn->host)){
            $this->addErrore($this->lang('invalid_host'));
        }

        $this->Host = $dsn->host;

        if (!empty($dsn->port)){
            $this->Port = $dsn->port;
        }

        if (!empty($dsn->scheme)){
            $this->SMTPSecure = $dsn->scheme;
        }

        $add_smtp_auth = false;

        if (!empty($dsn->user)){
            $this->Username = $dsn->user;
            $add_smtp_auth = true;
        }

        if (!empty($dsn->pass)){
            $this->Password = $dsn->pass;
            $add_smtp_auth = true;
        }

        if ($add_smtp_auth){
            $this->SMTPAuth = true;
        }

        if (WP_DEBUG && (\defined('WP_MAIL_SMTP_DEBUG') && WP_MAIL_SMTP_DEBUG)) {
            $this->Debugoutput = 'error_log';
            $this->SMTPDebug = \defined('WP_MAIL_SMTP_DEBUG_LEVEL') ? WP_MAIL_SMTP_DEBUG_LEVEL : 1;
        }
    }

    /**
     * @param string $email
     * @param string|null $name
     *
     * @return array|bool
     */
    private function parseEmail($email, $name = null)
    {
        if (!$name && preg_match('/(?<name>.+)?<(?<email>(.+))>/', $email, $matches)) {
            $name = !empty($matches['name']) ? trim($matches['name']) : '';
            $email = !empty($matches['email']) ? trim($matches['email']) : '';
        }

        $email = $this->trim($email);
        $name = $this->trim($name);

        if (self::validateAddress($email)) {
            return compact('name', 'email');
        }

        return false;
    }

    protected function addErrore(string $str){

        $this->setError($str);
        $this->edebug($str);

        if ($this->exceptions) {
            throw new Exception($str);
        }
    }
}
