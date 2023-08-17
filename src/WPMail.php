<?php

namespace JazzMan\WPMail;

use Exception;
use JazzMan\AutoloadInterface\AutoloadInterface;
use PHPMailer\PHPMailer\DSNConfigurator;
use PHPMailer\PHPMailer\PHPMailer;
use WP_Error;

final class WPMail implements AutoloadInterface {

    private ?PHPMailer $phpmailer;

    private ?string $fromEmail = null;
    private ?string $fromName = null;
    private string $contentType = PHPMailer::CONTENT_TYPE_PLAINTEXT;

    private string $charset = PHPMailer::CHARSET_UTF8;

    public function load(): void {
        add_filter( 'pre_wp_mail', [ $this, 'mail' ], 10, 2 );
    }

    /**
     * @param array{to: string|array|null, subject: string|null, message: string|null, headers: string|array|null, attachments: string|array|null} $atts
     */
    public function mail( ?bool $return = null, array $atts = [] ): bool {
        $this->init();

        if ( ! $this->phpmailer instanceof PHPMailer ) {
            return $return;
        }

        /** @var string|string[] $target */
        $target = ! empty( $atts['to'] ) ? $atts['to'] : false;

        /** @var string|false $subject */
        $subject = ! empty( $atts['subject'] ) ? $atts['subject'] : false;

        /** @var string|false $message */
        $message = ! empty( $atts['message'] ) ? $atts['message'] : false;

        /** @var array<string,mixed>|false $headers */
        $headers = ! empty( $atts['headers'] ) ? $atts['headers'] : false;
        $attachments = ! empty( $atts['attachments'] ) ? $atts['attachments'] : false;

        try {
            if ( ! empty( $target ) ) {
                $recipients = self::formatEmail( $target );

                if ( ! empty( $recipients ) ) {
                    foreach ( $recipients as $recipient ) {
                        $this->phpmailer->addAddress( $recipient['address'], $recipient['name'] );
                    }
                }
            }

            if ( ! empty( $headers ) ) {
                $this->addMailHeaders( $headers );
            }

            if ( ! empty( $subject ) ) {
                // Set mail's subject and body
                $this->phpmailer->Subject = self::trim( $subject );
            }

            if ( ! empty( $message ) ) {
                $this->phpmailer->msgHTML( $message );
            }

            $this->setSender();

            if ( ! empty( $attachments ) ) {
                if ( ! \is_array( $attachments ) ) {
                    $attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
                }

                foreach ( $attachments as $filename => $attachment ) {
                    $filename = \is_string( $filename ) ? $filename : '';

                    $this->phpmailer->addAttachment( $attachment, $filename );
                }
            }

            $this->phpmailer->ContentType = apply_filters( 'wp_mail_content_type', $this->contentType );

            $this->phpmailer->CharSet = apply_filters( 'wp_mail_charset', $this->charset );

            return $this->phpmailer->send();
        } catch ( Exception $errore ) {
            $mail_error_data = compact( 'target', 'subject', 'message', 'headers', 'attachments' );
            $mail_error_data['phpmailer_exception_code'] = $errore->getCode();

            /*
             * Fires after a phpmailerException is caught.
             *
             * @since 4.4.0
             *
             * @param WP_Error $error A WP_Error object with the phpmailerException message, and an array
             *                        containing the mail recipient, subject, message, headers, and attachments.
             */
            do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $errore->getMessage(), $mail_error_data ) );

            if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( $errore );
            }
        }

        return true;
    }

    private function init(): void {
        if ( \defined( 'WP_MAIL_SMTP_URL' ) ) {
            try {
                $phpmailer = DSNConfigurator::mailer( WP_MAIL_SMTP_URL, true );

                $phpmailer::$validator = static fn ( string $email ): bool => (bool) is_email( $email );

                $this->phpmailer = $phpmailer;
            } catch ( Exception $error ) {
            }
        } else {
            $phpmailer = new PHPMailer( true );

            $phpmailer::$validator = static fn ( string $email ): bool => (bool) is_email( $email );

            $this->phpmailer = $phpmailer;
        }

        if ( $this->phpmailer instanceof PHPMailer ) {
            $this->phpmailer->isSMTP();

            if ( \defined( 'WP_MAIL_SMTP_HOST' ) ) {
                $this->phpmailer->Host = (string) WP_MAIL_SMTP_HOST;
            }

            if ( \defined( 'WP_MAIL_SMTP_PORT' ) ) {
                $this->phpmailer->Port = (int) WP_MAIL_SMTP_PORT;
            }

            if ( \defined( 'WP_MAIL_SMTP_SECURE' ) ) {
                $this->phpmailer->SMTPSecure = (string) WP_MAIL_SMTP_SECURE;
            }

            $add_smtp_auth = false;

            if ( \defined( 'WP_MAIL_SMTP_USERNAME' ) ) {
                $this->phpmailer->Username = (string) WP_MAIL_SMTP_USERNAME;

                $add_smtp_auth = true;
            }

            if ( \defined( 'WP_MAIL_SMTP_PASSWORD' ) ) {
                $this->phpmailer->Password = (string) WP_MAIL_SMTP_PASSWORD;

                $add_smtp_auth = true;
            }

            if ( $add_smtp_auth ) {
                $this->phpmailer->SMTPAuth = true;
            }

            if ( WP_DEBUG && ( \defined( 'WP_MAIL_SMTP_DEBUG' ) && WP_MAIL_SMTP_DEBUG ) ) {
                $this->phpmailer->Debugoutput = 'error_log';
                $this->phpmailer->SMTPDebug = \defined( 'WP_MAIL_SMTP_DEBUG_LEVEL' ) ? WP_MAIL_SMTP_DEBUG_LEVEL : 1;
            }

            $this->phpmailer->clearAllRecipients();
            $this->phpmailer->clearAttachments();
            $this->phpmailer->clearCustomHeaders();
            $this->phpmailer->clearReplyTos();
            $this->phpmailer->Body = '';
            $this->phpmailer->AltBody = '';
        }
    }

    private function addMailHeaders( array|string $headers ): void {
        $headers = $this->parseHeaders( $headers );

        foreach ( $headers as $header => $content ) {
            switch ( $header ) {
                case 'content-type':
                    if ( str_contains( $content, ';' ) ) {
                        [ $type, $charset_content ] = explode( ';', $content );
                        $this->contentType = self::trim( $type );

                        if ( false !== stripos( $charset_content, 'charset=' ) ) {
                            $this->charset = self::trim( str_replace( [ 'charset=', '"' ], '', $charset_content ) );
                        } elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
                            $this->charset = PHPMailer::CHARSET_UTF8;
                        }
                        // Avoid setting an empty $content_type.
                    } elseif ( '' !== self::trim( $content ) ) {
                        $this->contentType = self::trim( $content );
                    }

                    break;

                case 'from':
                    $from = self::formatEmail( $content );

                    if ( ! empty( $from ) ) {
                        $from = reset( $from );

                        $this->fromEmail = $from['address'];
                        $this->fromName = $from['name'];
                    }

                    break;

                case 'cc':

                    $ccTarget = self::formatEmail( $content );

                    if ( ! empty( $ccTarget ) ) {
                        foreach ( $ccTarget as $item ) {
                            $this->phpmailer->addCC( (string) $item['address'], (string) $item['name'] );
                        }
                    }

                    break;

                case 'bcc':
                    $bccTarget = self::formatEmail( $content );

                    if ( ! empty( $bccTarget ) ) {
                        foreach ( $bccTarget as $item ) {
                            $this->phpmailer->addBCC( $item['address'], $item['name'] );
                        }
                    }

                    break;

                case 'reply-to':

                    $replyTo = self::formatEmail( $content );

                    if ( ! empty( $replyTo ) ) {
                        foreach ( $replyTo as $item ) {
                            $this->phpmailer->addReplyTo( $item['address'], $item['name'] );
                        }
                    }

                    break;

                default:
                    $this->phpmailer->addCustomHeader( $header, $content );

                    break;
            }
        }
    }

    private function setSender(): void {
        if ( empty( $this->fromEmail ) ) {
            $admin_email = get_bloginfo( 'admin_email' );

            if ( ! empty( $admin_email ) ) {
                $this->fromEmail = $admin_email;
            } else {
                // Get the site domain and get rid of www.
                $sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );

                $sitename = ltrim( strtolower( $sitename ), 'www.' );

                $this->fromEmail = "website@{$sitename}";
            }
        }

        if ( empty( $this->fromName ) ) {
            $this->fromName = get_bloginfo( 'name' );
        }

        $this->phpmailer->setFrom(
            (string) apply_filters( 'wp_mail_from', $this->fromEmail ),
            (string) apply_filters( 'wp_mail_from_name', $this->fromName )
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders( array|string $headers ): array {
        if ( \is_array( $headers ) ) {
            $headers = implode( PHPMailer::CRLF, $headers );
        }

        $headers = $this->phpmailer->DKIM_HeaderC( $headers );

        $headers = explode( PHPMailer::CRLF, $headers );

        $headers = array_filter( $headers );

        $_headers = [];

        foreach ( $headers as $header ) {
            [ $name, $content ] = explode( ':', self::trim( $header ), 2 );

            $name = trim( $name );
            $content = trim( $content );

            $_headers[ strtolower( $name ) ] = $content;
        }

        return $_headers;
    }

    private static function trim( ?string $string = '' ): string {
        $string = preg_replace( '/\s{2,}/siu', ' ', $string );

        return trim( (string) $string );
    }

    /**
     * Formats recipient email.
     *
     * @return array<array-key,array{name:string, address: string}>
     */
    private static function formatEmail( array|string $address ): array {
        $emails = [ [] ];

        if ( ! \is_array( $address ) ) {
            $address = explode( ',', $address );
        }

        foreach ( $address as $item ) {
            $data = PHPMailer::parseAddresses( $item, true, PHPMailer::CHARSET_UTF8 );

            if ( ! empty( $data ) ) {
                $emails[] = $data;
            }
        }

        return array_merge( ...$emails );
    }
}
