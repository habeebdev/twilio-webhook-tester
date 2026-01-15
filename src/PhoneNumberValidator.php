<?php

namespace Twilio\WebhookCli;

use Exception;

class PhoneNumberValidator
{
    /**
     * Validates a phone number against E.164 format
     *
     * @param string $phoneNumber The phone number to validate
     * @param string $fieldName The field name for error messages (e.g., 'From', 'To')
     * @return void
     * @throws Exception If the phone number is not in valid E.164 format
     */
    public static function validateE164(string $phoneNumber, string $fieldName): void
    {
        // E.164 format: +[country code][subscriber number]
        // Must start with +, followed by 1-15 digits total
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber)) {
            throw new Exception(
                "Invalid phone number format for '{$fieldName}': {$phoneNumber}\n" .
                "Please enter phone number in E.164 format (e.g., +15551234567)\n" .
                "E.164 format: +[country code][subscriber number] (1-15 digits after +)"
            );
        }
    }
}
