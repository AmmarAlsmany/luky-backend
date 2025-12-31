<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     * Rejects fake, temporary, or suspicious email addresses
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty if field is nullable
        }

        // Basic email format validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail(__('validation.custom.email.invalid_format'));
            return;
        }

        $email = strtolower($value);

        // Reject common fake/test email patterns
        $suspiciousPatterns = [
            '@test.', '@fake.', '@example.', '@temp.', '@dummy.',
            '@aaa.', '@bbb.', '@ccc.', '@xxx.', '@yyy.',
            '@asdf.', '@qwer.', '@zxcv.',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($email, $pattern)) {
                $fail(__('validation.custom.email.fake_email'));
                return;
            }
        }

        // Reject common temporary email services (expanded list)
        $tempEmailDomains = [
            // Common temp mail services
            'tempmail.com', 'temp-mail.org', '10minutemail.com', '10minutemail.net',
            'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
            'mailinator.com', 'mailinator.net', 'mailinator2.com',
            'throwaway.email', 'throwemail.com', 'tempmail.net',
            'yopmail.com', 'yopmail.net', 'yopmail.fr',
            'maildrop.cc', 'maildrop.com',
            'trashmail.com', 'trashmail.net', 'trash-mail.com',
            // Additional popular temp services
            'getnada.com', 'mohmal.com', 'mytemp.email', 'sharklasers.com',
            'spam4.me', 'mintemail.com', 'fakeinbox.com', 'dispostable.com',
            'emailondeck.com', 'spamgourmet.com', 'harakirimail.com',
            'mailnesia.com', 'mailcatch.com', 'emailfake.com',
            'fakemail.net', 'temporary-mail.net', 'disposablemail.com',
            '33mail.com', 'getairmail.com', 'fakemailgenerator.com',
            'tmailor.com', 'tmails.net', 'mailnator.com',
        ];

        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $domain = $parts[1];

            foreach ($tempEmailDomains as $tempDomain) {
                if ($domain === $tempDomain || str_ends_with($domain, '.' . $tempDomain)) {
                    $fail(__('validation.custom.email.temp_email'));
                    return;
                }
            }

            // Check for valid TLD (top-level domain)
            $domainParts = explode('.', $domain);
            if (count($domainParts) < 2) {
                $fail(__('validation.custom.email.invalid_domain'));
                return;
            }

            $tld = end($domainParts);
            if (strlen($tld) < 2) {
                $fail(__('validation.custom.email.invalid_tld'));
                return;
            }

            // Reject single letter domains (like test@a.a)
            if (strlen($domainParts[0]) < 2) {
                $fail(__('validation.custom.email.domain_appears_invalid'));
                return;
            }

            // Check for DNS MX records (verify domain can receive emails)
            // Only check if not in testing environment
            if (app()->environment() !== 'testing') {
                if (!$this->hasMXRecord($domain)) {
                    $fail(__('validation.custom.email.domain_cannot_receive_email'));
                    return;
                }
            }

            // Reject suspicious patterns in local part (before @)
            $localPart = $parts[0];
            if (preg_match('/^(test|fake|temp|dummy|trash|spam|admin|root|noreply)$/i', $localPart)) {
                $fail(__('validation.custom.email.suspicious_email'));
                return;
            }

            // Reject emails with too many consecutive dots or special characters
            if (preg_match('/\.{2,}/', $email) || preg_match('/[+]{2,}/', $email)) {
                $fail(__('validation.custom.email.invalid_email_format'));
                return;
            }
        }
    }

    /**
     * Check if domain has valid MX records
     */
    private function hasMXRecord(string $domain): bool
    {
        try {
            // Check for MX records
            $mxRecords = [];
            if (getmxrr($domain, $mxRecords)) {
                return count($mxRecords) > 0;
            }

            // Fallback: check if domain has A record (some servers use A record for mail)
            $aRecord = gethostbyname($domain);
            if ($aRecord !== $domain) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            // If DNS check fails, allow the email (network might be down)
            return true;
        }
    }
}
