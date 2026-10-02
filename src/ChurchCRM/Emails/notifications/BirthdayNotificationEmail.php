<?php

namespace ChurchCRM\Emails\notifications;

/**
 * Daily "birthdays today" summary sent to admins and configured recipients.
 *
 * Uses the same UnifiedNotificationEmail wrapper and logo handling as the
 * birthday greeting email; only the recipients and body differ.
 */
class BirthdayNotificationEmail extends BirthdayGreetingEmail
{
}
