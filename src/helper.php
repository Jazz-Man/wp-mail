<?php

namespace JazzMan\Mail;

use Exception;

add_action('wpcf7_spam', __NAMESPACE__.'\wpcf7_spam');

/**
 * @param bool $spam
 *
 * @return bool
 */
function wpcf7_spam($spam)
{
    try {
        $cft = new ContactFormSpamTester();

        $report = $cft->getSpamReport();

        return (bool) $report->isSpam;
    } catch (Exception $exception) {
        return $spam;
    }
}
