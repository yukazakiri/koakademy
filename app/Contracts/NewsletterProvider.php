<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\NewsletterContact;
use App\Enums\NewsletterProvider as NewsletterProviderName;
use App\Enums\NewsletterRemoteStatus;
use App\Enums\NewsletterSubscribeResult;

interface NewsletterProvider
{
    public function name(): NewsletterProviderName;

    public function isConfigured(): bool;

    public function testConnection(): bool;

    public function status(string $email): NewsletterRemoteStatus;

    public function subscribe(NewsletterContact $contact): NewsletterSubscribeResult;
}
