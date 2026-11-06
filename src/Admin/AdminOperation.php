<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin;

use Appsolutely\Sdk\Exception\InvalidArgumentValueException;

/**
 * The Administrator API operations this client types, each by the
 * operationId the pinned document gives it; the contract test holds every
 * case to that document's method, path, scope, success status,
 * Idempotency-Key and If-Match. An operation not here is still reachable
 * through AdminClient::raw().
 */
enum AdminOperation: string
{
    case ListArticles = 'articles.index';
    case GetArticle = 'articles.show';
    case CreateArticle = 'articles.store';
    case UpdateArticle = 'articles.update';
    case DeleteArticle = 'articles.destroy';
    case ListPages = 'pages.index';
    case GetPage = 'pages.show';
    case ListProducts = 'products.index';
    case GetProduct = 'products.show';
    case ListOrders = 'orders.index';
    case GetOrder = 'orders.show';
    case ListFormEntries = 'form-entries.index';
    case GetFormEntry = 'form-entries.show';
    case ListWebhookDeliveries = 'webhook-deliveries.index';
    case GetWebhookDelivery = 'webhook-deliveries.show';
    case RedeliverWebhookDelivery = 'webhook-deliveries.redeliver';

    public function method(): string
    {
        return match ($this) {
            self::CreateArticle, self::RedeliverWebhookDelivery => 'POST',
            self::UpdateArticle => 'PATCH',
            self::DeleteArticle => 'DELETE',
            default => 'GET',
        };
    }

    /**
     * The path as the document writes it, under AdminClient::BASE_PATH, with
     * its placeholders.
     */
    public function template(): string
    {
        return AdminClient::BASE_PATH . match ($this) {
            self::ListArticles, self::CreateArticle => 'articles',
            self::GetArticle, self::UpdateArticle, self::DeleteArticle => 'articles/{article}',
            self::ListPages => 'pages',
            self::GetPage => 'pages/{page}',
            self::ListProducts => 'products',
            self::GetProduct => 'products/{product}',
            self::ListOrders => 'orders',
            self::GetOrder => 'orders/{order}',
            self::ListFormEntries => 'form-entries',
            self::GetFormEntry => 'form-entries/{form_entry}',
            self::ListWebhookDeliveries => 'webhook-deliveries',
            self::GetWebhookDelivery => 'webhook-deliveries/{webhook_delivery}',
            self::RedeliverWebhookDelivery => 'webhook-deliveries/{webhook_delivery}/actions/redeliver',
        };
    }

    /**
     * The path with each placeholder filled in, encoded as one segment.
     *
     * @param array<string, string> $parameters
     */
    public function path(array $parameters = []): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', static function (array $match) use ($parameters): string {
            $value = $parameters[$match[1]] ?? '';
            if ($value === '' || $value === '.' || $value === '..') {
                throw new InvalidArgumentValueException(sprintf('The %s of an Administrator API path is a record\'s id; got "%s".', $match[1], $value));
            }

            return rawurlencode($value);
        }, $this->template());
    }

    /** The Administrator API scope the operation asks the credential for. */
    public function scope(): string
    {
        return match ($this) {
            self::ListArticles, self::GetArticle => 'admin:articles:read',
            self::CreateArticle, self::UpdateArticle => 'admin:articles:write',
            self::DeleteArticle => 'admin:articles:delete',
            self::ListPages, self::GetPage => 'admin:pages:read',
            self::ListProducts, self::GetProduct => 'admin:products:read',
            self::ListOrders, self::GetOrder => 'admin:orders:read',
            self::ListFormEntries, self::GetFormEntry => 'admin:form-entries:read',
            self::ListWebhookDeliveries, self::GetWebhookDelivery => 'admin:webhook-deliveries:read',
            self::RedeliverWebhookDelivery => 'admin:webhook-deliveries:redeliver',
        };
    }

    public function successStatus(): int
    {
        return match ($this) {
            self::CreateArticle => 201,
            self::DeleteArticle => 204,
            default => 200,
        };
    }

    /** A write a retry could repeat, sent with an Idempotency-Key. */
    public function takesIdempotencyKey(): bool
    {
        return $this === self::CreateArticle || $this === self::RedeliverWebhookDelivery;
    }

    /** A write made only over the version read, named by its ETag in If-Match. */
    public function requiresIfMatch(): bool
    {
        return $this === self::UpdateArticle || $this === self::DeleteArticle;
    }

    public function isList(): bool
    {
        return in_array($this, [self::ListArticles, self::ListPages, self::ListProducts, self::ListOrders, self::ListFormEntries, self::ListWebhookDeliveries], true);
    }
}
