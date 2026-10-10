<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\InvalidArgumentValueException;

/**
 * Every operation of the Site API document this client is pinned to
 * (Contract::REVISION), named by its operationId, with the method, path,
 * audience and answers the client relies on. Testing\FakeClient arranges
 * and records calls by these cases.
 */
enum Operation: string
{
    case GetApiVersion = 'getApiVersion';
    case GetOpenApiDocument = 'getOpenApiDocument';
    case RequestMagicLink = 'requestMagicLink';
    case TokenMagicLink = 'tokenMagicLink';

    case ListAccountStates = 'listAccountStates';
    case ListArticles = 'listArticles';
    case CreateArticle = 'createArticle';
    case GetArticle = 'getArticle';
    case UpdateArticle = 'updateArticle';
    case ListFormEntries = 'listFormEntries';
    case GetFormEntry = 'getFormEntry';
    case ListOrders = 'listOrders';
    case GetOrder = 'getOrder';
    case ListPages = 'listPages';
    case GetPage = 'getPage';
    case ListProducts = 'listProducts';
    case GetProduct = 'getProduct';
    case ListWebhookDeliveries = 'listWebhookDeliveries';
    case RedeliverWebhookDelivery = 'redeliverWebhookDelivery';
    case PullSyncArticle = 'pullSync.article';
    case PushSyncArticle = 'pushSync.article';
    case PullSyncFormEntry = 'pullSync.formEntry';
    case PullSyncOrder = 'pullSync.order';
    case PullSyncPage = 'pullSync.page';
    case PullSyncProduct = 'pullSync.product';

    case GetMe = 'getMe';
    case UpdateMe = 'updateMe';
    case ListMeAddresses = 'listMeAddresses';
    case CreateMeAddress = 'createMeAddress';
    case UpdateMeAddress = 'updateMeAddress';
    case DeleteMeAddress = 'deleteMeAddress';
    case GetMeBillingEntry = 'getMeBillingEntry';
    case ListMeEntitlements = 'listMeEntitlements';
    case ListMeOrders = 'listMeOrders';
    case GetMeOrder = 'getMeOrder';
    case CreateMePushDevice = 'createMePushDevice';
    case UnregisterMePushDevice = 'unregisterMePushDevice';
    case GetMeReferral = 'getMeReferral';
    case RewardsMeReferral = 'rewardsMeReferral';
    case PullSyncMeAddress = 'pullSync.meAddress';
    case PushSyncMeAddress = 'pushSync.meAddress';
    case PullSyncMeOrder = 'pullSync.meOrder';

    public function method(): string
    {
        return match ($this) {
            self::RequestMagicLink, self::TokenMagicLink, self::CreateArticle, self::RedeliverWebhookDelivery,
            self::PushSyncArticle, self::CreateMeAddress, self::CreateMePushDevice, self::UnregisterMePushDevice,
            self::PushSyncMeAddress => 'POST',
            self::UpdateArticle, self::UpdateMe, self::UpdateMeAddress => 'PATCH',
            self::DeleteMeAddress => 'DELETE',
            default => 'GET',
        };
    }

    /**
     * The path as the document writes it, with `{name}` placeholders.
     */
    public function template(): string
    {
        return match ($this) {
            self::GetApiVersion => '/api/v1',
            self::GetOpenApiDocument => '/api/v1/openapi.json',
            self::RequestMagicLink => '/api/v1/auth/magic-link',
            self::TokenMagicLink => '/api/v1/auth/magic-link/token',
            self::ListAccountStates => '/api/v1/account-states',
            self::ListArticles, self::CreateArticle => '/api/v1/articles',
            self::GetArticle, self::UpdateArticle => '/api/v1/articles/{id}',
            self::ListFormEntries => '/api/v1/form-entries',
            self::GetFormEntry => '/api/v1/form-entries/{id}',
            self::ListOrders => '/api/v1/orders',
            self::GetOrder => '/api/v1/orders/{id}',
            self::ListPages => '/api/v1/pages',
            self::GetPage => '/api/v1/pages/{id}',
            self::ListProducts => '/api/v1/products',
            self::GetProduct => '/api/v1/products/{id}',
            self::ListWebhookDeliveries => '/api/v1/webhook-subscriptions/{subscription}/deliveries',
            self::RedeliverWebhookDelivery => '/api/v1/webhook-subscriptions/{subscription}/deliveries/{event}/redeliver',
            self::PullSyncArticle, self::PushSyncArticle => '/api/v1/sync/articles',
            self::PullSyncFormEntry => '/api/v1/sync/form-entries',
            self::PullSyncOrder => '/api/v1/sync/orders',
            self::PullSyncPage => '/api/v1/sync/pages',
            self::PullSyncProduct => '/api/v1/sync/products',
            self::GetMe, self::UpdateMe => '/api/v1/me',
            self::ListMeAddresses, self::CreateMeAddress => '/api/v1/me/addresses',
            self::UpdateMeAddress, self::DeleteMeAddress => '/api/v1/me/addresses/{id}',
            self::GetMeBillingEntry => '/api/v1/me/billing-entry',
            self::ListMeEntitlements => '/api/v1/me/entitlements',
            self::ListMeOrders => '/api/v1/me/orders',
            self::GetMeOrder => '/api/v1/me/orders/{id}',
            self::CreateMePushDevice => '/api/v1/me/push-devices',
            self::UnregisterMePushDevice => '/api/v1/me/push-devices/unregister',
            self::GetMeReferral => '/api/v1/me/referral',
            self::RewardsMeReferral => '/api/v1/me/referral/rewards',
            self::PullSyncMeAddress, self::PushSyncMeAddress => '/api/v1/sync/me-addresses',
            self::PullSyncMeOrder => '/api/v1/sync/me-orders',
        };
    }

    /**
     * The path with each placeholder filled, percent-encoded as one path
     * segment.
     *
     * @param array<string, string> $parameters a value for each placeholder, and nothing else
     */
    public function path(array $parameters = []): string
    {
        $missing = [];
        $path = (string) preg_replace_callback('/\{([a-z_]+)\}/', static function (array $match) use ($parameters, &$missing): string {
            $value = $parameters[$match[1]] ?? '';
            if ($value === '') {
                $missing[] = $match[1];
            }

            return rawurlencode($value);
        }, $this->template(), -1, $filled);

        if ($missing !== []) {
            throw new InvalidArgumentValueException(sprintf('%s needs a non-empty "%s".', $this->value, implode('" and "', $missing)));
        }
        if (count($parameters) !== $filled) {
            throw new InvalidArgumentValueException(sprintf('%s takes only %s in its path.', $this->value, $filled === 0 ? 'no value' : 'the values its placeholders name'));
        }

        return $path;
    }

    public function audience(): Audience
    {
        return match ($this) {
            self::GetApiVersion, self::GetOpenApiDocument, self::RequestMagicLink, self::TokenMagicLink => Audience::Anyone,
            self::GetMe, self::UpdateMe, self::ListMeAddresses, self::CreateMeAddress, self::UpdateMeAddress,
            self::DeleteMeAddress, self::GetMeBillingEntry, self::ListMeEntitlements, self::ListMeOrders,
            self::GetMeOrder, self::CreateMePushDevice, self::UnregisterMePushDevice, self::GetMeReferral,
            self::RewardsMeReferral, self::PullSyncMeAddress, self::PushSyncMeAddress, self::PullSyncMeOrder => Audience::Member,
            default => Audience::Administrator,
        };
    }

    /**
     * The status the site answers a success with.
     */
    public function successStatus(): int
    {
        return match ($this) {
            self::TokenMagicLink, self::CreateArticle, self::CreateMeAddress => 201,
            self::RequestMagicLink, self::RedeliverWebhookDelivery => 202,
            self::DeleteMeAddress, self::UnregisterMePushDevice => 204,
            default => 200,
        };
    }

    /**
     * Whether the site honours an Idempotency-Key on it: the client then
     * always sends one, and retries the write with the same key.
     */
    public function takesIdempotencyKey(): bool
    {
        return match ($this) {
            self::CreateArticle, self::CreateMeAddress, self::RedeliverWebhookDelivery => true,
            default => false,
        };
    }

    /**
     * Whether it is a list answered a page at a time, as `{data, next_cursor}`
     * with `limit` and `cursor`. A list the site answers whole, as one page
     * that never has a cursor, is not one, and nor is a sync pull, which
     * pages a change feed by its own rules.
     */
    public function isCursorList(): bool
    {
        return match ($this) {
            self::ListArticles, self::ListFormEntries, self::ListOrders, self::ListPages, self::ListProducts,
            self::ListWebhookDeliveries, self::ListMeAddresses, self::ListMeOrders, self::RewardsMeReferral => true,
            default => false,
        };
    }
}
