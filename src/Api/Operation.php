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
    case CreateMeCheckoutSession = 'createMeCheckoutSession';
    case GetMeCheckoutSession = 'getMeCheckoutSession';
    case CreateMePortalSession = 'createMePortalSession';
    case IntentsMeStorePurchase = 'intentsMeStorePurchase';
    case TransactionsMeStorePurchase = 'transactionsMeStorePurchase';
    case ListMeSubscriptions = 'listMeSubscriptions';
    case CancelMeSubscription = 'cancelMeSubscription';
    case ResumeMeSubscription = 'resumeMeSubscription';
    case ChangeMeSubscription = 'changeMeSubscription';

    case GetClient = 'getClient';
    case ListProducts = 'listProducts';
    case GetProduct = 'getProduct';
    case CreateFulfilment = 'createFulfilment';
    case ListBackendEntitlements = 'listBackendEntitlements';
    case GetBackendEntitlement = 'getBackendEntitlement';
    case ListWebhookEndpoints = 'listWebhookEndpoints';
    case CreateWebhookEndpoint = 'createWebhookEndpoint';
    case DisableWebhookEndpoint = 'disableWebhookEndpoint';
    case EnableWebhookEndpoint = 'enableWebhookEndpoint';
    case TestDeliveriesWebhookEndpoint = 'test-deliveriesWebhookEndpoint';
    case StartWebhookEndpointsSecretRotation = 'startWebhookEndpoints.secretRotation';
    case CompleteWebhookEndpointsSecretRotation = 'completeWebhookEndpoints.secretRotation';
    case CancelWebhookEndpointsSecretRotation = 'cancelWebhookEndpoints.secretRotation';
    case ListDeliveries = 'listDeliveries';
    case RedeliverDelivery = 'redeliverDelivery';
    case CreateCheckoutSession = 'createCheckoutSession';
    case GetCheckoutSession = 'getCheckoutSession';
    case CreatePortalSession = 'createPortalSession';
    case IntentsStorePurchase = 'intentsStorePurchase';
    case TransactionsStorePurchase = 'transactionsStorePurchase';
    case AttachTestClock = 'attachTestClock';
    case AdvanceTestClock = 'advanceTestClock';
    case ListReturnTargets = 'listReturnTargets';
    case CreateReturnTarget = 'createReturnTarget';
    case DeleteReturnTarget = 'deleteReturnTarget';
    case ListFeatureKeys = 'listFeatureKeys';
    case CreateFeatureKey = 'createFeatureKey';
    case RetireFeatureKey = 'retireFeatureKey';
    case ListProductFeatureValues = 'listProductFeatureValues';
    case UpdateProductFeatureValue = 'updateProductFeatureValue';
    case DeleteProductFeatureValue = 'deleteProductFeatureValue';
    case ListRoles = 'listRoles';
    case CreateRole = 'createRole';
    case RetireRole = 'retireRole';
    case ListRoleAssignments = 'listRoleAssignments';
    case GetRoleAssignment = 'getRoleAssignment';
    case AssignRoleAssignment = 'assignRoleAssignment';
    case RevokeRoleAssignment = 'revokeRoleAssignment';
    case CreateSeat = 'createSeat';
    case DeleteSeat = 'deleteSeat';
    case ListSeatPools = 'listSeatPools';

    case RequestDeviceAuthorization = 'requestDeviceAuthorization';

    case ExchangeOAuthToken = 'exchangeOAuthToken';

    case RevokeOAuthToken = 'revokeOAuthToken';

    case GetOpenIdSigningKeys = 'getOpenIdSigningKeys';

    case GetOAuthUserInfo = 'getOAuthUserInfo';

    public function method(): string
    {
        return match ($this) {
            self::GetOAuthUserInfo => 'GET',
            self::GetOpenIdSigningKeys => 'GET',
            self::RevokeOAuthToken => 'POST',
            self::ExchangeOAuthToken => 'POST',
            self::RequestDeviceAuthorization => 'POST',
            self::RequestMagicLink, self::TokenMagicLink,
            self::CreateMeAddress, self::CreateMePushDevice, self::UnregisterMePushDevice,
            self::PushSyncMeAddress,
            self::CreateMeCheckoutSession, self::CreateMePortalSession, self::IntentsMeStorePurchase, self::TransactionsMeStorePurchase, self::CancelMeSubscription, self::ResumeMeSubscription, self::ChangeMeSubscription, self::CreateWebhookEndpoint, self::DisableWebhookEndpoint, self::EnableWebhookEndpoint, self::TestDeliveriesWebhookEndpoint, self::StartWebhookEndpointsSecretRotation, self::CompleteWebhookEndpointsSecretRotation, self::CancelWebhookEndpointsSecretRotation, self::RedeliverDelivery, self::CreateCheckoutSession, self::CreateFulfilment, self::CreatePortalSession, self::IntentsStorePurchase, self::TransactionsStorePurchase, self::AttachTestClock, self::AdvanceTestClock, self::CreateReturnTarget, self::CreateFeatureKey, self::RetireFeatureKey, self::CreateRole, self::RetireRole, self::CreateSeat => 'POST',
            self::UpdateMe, self::UpdateMeAddress => 'PATCH',
            self::UpdateProductFeatureValue, self::AssignRoleAssignment => 'PUT',
            self::DeleteMeAddress, self::DeleteReturnTarget, self::DeleteProductFeatureValue, self::RevokeRoleAssignment, self::DeleteSeat => 'DELETE',
            default => 'GET',
        };
    }

    /**
     * The path as the document writes it, with `{name}` placeholders.
     */
    public function template(): string
    {
        return match ($this) {
            self::GetOAuthUserInfo => '/oauth/userinfo',
            self::GetOpenIdSigningKeys => '/oauth/jwks.json',
            self::RevokeOAuthToken => '/oauth/revoke',
            self::ExchangeOAuthToken => '/oauth/token',
            self::RequestDeviceAuthorization => '/oauth/device/code',
            self::GetApiVersion => '/api/v1',
            self::GetOpenApiDocument => '/api/v1/openapi.json',
            self::RequestMagicLink => '/api/v1/auth/magic-link',
            self::TokenMagicLink => '/api/v1/auth/magic-link/token',
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
            self::CreateMeCheckoutSession => '/api/v1/me/checkout-sessions',
            self::GetMeCheckoutSession => '/api/v1/me/checkout-sessions/{session}',
            self::CreateMePortalSession => '/api/v1/me/portal-sessions',
            self::IntentsMeStorePurchase => '/api/v1/me/store-purchases/intents',
            self::TransactionsMeStorePurchase => '/api/v1/me/store-purchases/transactions',
            self::ListMeSubscriptions => '/api/v1/me/subscriptions',
            self::CancelMeSubscription => '/api/v1/me/subscriptions/{subscription}/cancellation',
            self::ResumeMeSubscription => '/api/v1/me/subscriptions/{subscription}/resumption',
            self::ChangeMeSubscription => '/api/v1/me/subscriptions/{subscription}/changes',
            self::GetClient => '/api/v1/client',
            self::ListProducts => '/api/v1/products',
            self::GetProduct => '/api/v1/products/{id}',
            self::CreateFulfilment => '/api/v1/fulfilments',
            self::ListBackendEntitlements => '/api/v1/entitlements',
            self::GetBackendEntitlement => '/api/v1/entitlements/{sub}',
            self::ListWebhookEndpoints, self::CreateWebhookEndpoint => '/api/v1/webhook-endpoints',
            self::DisableWebhookEndpoint => '/api/v1/webhook-endpoints/{endpoint}/disable',
            self::EnableWebhookEndpoint => '/api/v1/webhook-endpoints/{endpoint}/enable',
            self::TestDeliveriesWebhookEndpoint => '/api/v1/webhook-endpoints/{endpoint}/test-deliveries',
            self::StartWebhookEndpointsSecretRotation => '/api/v1/webhook-endpoints/{endpoint}/secret-rotations',
            self::CompleteWebhookEndpointsSecretRotation => '/api/v1/webhook-endpoints/{endpoint}/secret-rotations/complete',
            self::CancelWebhookEndpointsSecretRotation => '/api/v1/webhook-endpoints/{endpoint}/secret-rotations/cancel',
            self::ListDeliveries => '/api/v1/deliveries',
            self::RedeliverDelivery => '/api/v1/deliveries/{event}/redeliveries',
            self::CreateCheckoutSession => '/api/v1/checkout-sessions',
            self::GetCheckoutSession => '/api/v1/checkout-sessions/{session}',
            self::CreatePortalSession => '/api/v1/portal-sessions',
            self::IntentsStorePurchase => '/api/v1/store-purchases/intents',
            self::TransactionsStorePurchase => '/api/v1/store-purchases/transactions',
            self::AttachTestClock => '/api/v1/subscriptions/{subscription}/test-clock',
            self::AdvanceTestClock => '/api/v1/subscriptions/{subscription}/test-clock/advance',
            self::ListReturnTargets, self::CreateReturnTarget => '/api/v1/return-targets',
            self::DeleteReturnTarget => '/api/v1/return-targets/{target}',
            self::ListFeatureKeys, self::CreateFeatureKey => '/api/v1/feature-keys',
            self::RetireFeatureKey => '/api/v1/feature-keys/{key}/retirement',
            self::ListProductFeatureValues => '/api/v1/products/{product}/feature-values',
            self::UpdateProductFeatureValue, self::DeleteProductFeatureValue => '/api/v1/products/{product}/feature-values/{key}',
            self::ListRoles, self::CreateRole => '/api/v1/roles',
            self::RetireRole => '/api/v1/roles/{key}/retirement',
            self::ListRoleAssignments => '/api/v1/role-assignments',
            self::GetRoleAssignment => '/api/v1/role-assignments/{sub}',
            self::AssignRoleAssignment, self::RevokeRoleAssignment => '/api/v1/role-assignments/{sub}/{key}',
            self::CreateSeat => '/api/v1/seats',
            self::DeleteSeat => '/api/v1/seats/{sub}',
            self::ListSeatPools => '/api/v1/seat-pools/{payer}',
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

    /**
     * Whose credentials the site answers it for.
     */
    public function audience(): Audience
    {
        return match ($this) {
            self::GetOAuthUserInfo => Audience::Member,
            self::GetOpenIdSigningKeys => Audience::Anyone,
            self::RevokeOAuthToken => Audience::Anyone,
            self::ExchangeOAuthToken => Audience::Anyone,
            self::RequestDeviceAuthorization => Audience::Anyone,
            self::GetApiVersion, self::GetOpenApiDocument, self::RequestMagicLink, self::TokenMagicLink => Audience::Anyone,
            self::GetMe, self::UpdateMe, self::ListMeAddresses, self::CreateMeAddress, self::UpdateMeAddress,
            self::DeleteMeAddress, self::GetMeBillingEntry, self::ListMeEntitlements, self::ListMeOrders,
            self::GetMeOrder, self::CreateMePushDevice, self::UnregisterMePushDevice, self::GetMeReferral,
            self::RewardsMeReferral, self::PullSyncMeAddress, self::PushSyncMeAddress, self::PullSyncMeOrder,
            self::CreateMeCheckoutSession, self::GetMeCheckoutSession, self::CreateMePortalSession, self::IntentsMeStorePurchase, self::TransactionsMeStorePurchase, self::ListMeSubscriptions, self::CancelMeSubscription, self::ResumeMeSubscription, self::ChangeMeSubscription => Audience::Member,
            self::GetClient, self::ListProducts, self::GetProduct, self::CreateFulfilment, self::ListBackendEntitlements, self::GetBackendEntitlement, self::ListWebhookEndpoints, self::CreateWebhookEndpoint, self::DisableWebhookEndpoint, self::EnableWebhookEndpoint, self::TestDeliveriesWebhookEndpoint, self::StartWebhookEndpointsSecretRotation, self::CompleteWebhookEndpointsSecretRotation, self::CancelWebhookEndpointsSecretRotation, self::ListDeliveries, self::RedeliverDelivery, self::CreateCheckoutSession, self::GetCheckoutSession, self::CreatePortalSession, self::IntentsStorePurchase, self::TransactionsStorePurchase, self::AttachTestClock, self::AdvanceTestClock, self::ListReturnTargets, self::CreateReturnTarget, self::DeleteReturnTarget, self::ListFeatureKeys, self::CreateFeatureKey, self::RetireFeatureKey, self::ListProductFeatureValues, self::UpdateProductFeatureValue, self::DeleteProductFeatureValue, self::ListRoles, self::CreateRole, self::RetireRole, self::ListRoleAssignments, self::GetRoleAssignment, self::AssignRoleAssignment, self::RevokeRoleAssignment, self::CreateSeat, self::DeleteSeat, self::ListSeatPools => Audience::Backend,
        };
    }

    /**
     * The status the site answers a success with.
     */
    public function successStatus(): int
    {
        return $this->successStatuses()[0];
    }

    /**
     * Every status the site answers a success with, the one above first: a
     * create that finds what it would have made answers it with 200.
     *
     * @return non-empty-list<int>
     */
    public function successStatuses(): array
    {
        return match ($this) {
            self::GetOAuthUserInfo => [200],
            self::GetOpenIdSigningKeys => [200],
            self::RevokeOAuthToken => [200],
            self::ExchangeOAuthToken => [200],
            self::RequestDeviceAuthorization => [200],
            self::TokenMagicLink, self::CreateMeAddress, self::CreateSeat, self::CreateWebhookEndpoint, self::StartWebhookEndpointsSecretRotation => [201],
            self::CreateCheckoutSession, self::CreateFeatureKey, self::CreateMeCheckoutSession, self::CreateMePortalSession, self::CreatePortalSession, self::CreateReturnTarget, self::CreateRole => [201, 200],
            self::RequestMagicLink, self::RedeliverDelivery, self::ResumeMeSubscription, self::TestDeliveriesWebhookEndpoint => [202],
            self::ChangeMeSubscription => [200, 202],
            self::DeleteMeAddress, self::UnregisterMePushDevice, self::DeleteProductFeatureValue, self::DeleteReturnTarget, self::DeleteSeat, self::RevokeRoleAssignment => [204],
            default => [200],
        };
    }

    /**
     * Whether the site honours an Idempotency-Key on it: the client then
     * always sends one, and retries the write with the same key.
     */
    public function takesIdempotencyKey(): bool
    {
        return match ($this) {
            self::GetOAuthUserInfo => false,
            self::GetOpenIdSigningKeys => false,
            self::RevokeOAuthToken => false,
            self::ExchangeOAuthToken => false,
            self::RequestDeviceAuthorization => false,
            self::CreateMeAddress,
            self::AdvanceTestClock, self::AttachTestClock, self::CancelMeSubscription, self::ChangeMeSubscription, self::CreateCheckoutSession, self::CreateFulfilment, self::CreateMeCheckoutSession, self::CreateMePortalSession, self::CreatePortalSession, self::CreateWebhookEndpoint, self::RedeliverDelivery, self::ResumeMeSubscription, self::StartWebhookEndpointsSecretRotation => true,
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
            self::GetOAuthUserInfo => false,
            self::GetOpenIdSigningKeys => false,
            self::RevokeOAuthToken => false,
            self::ExchangeOAuthToken => false,
            self::RequestDeviceAuthorization => false,
            self::ListProducts,
            self::ListMeAddresses, self::ListMeOrders, self::RewardsMeReferral,
            self::ListBackendEntitlements, self::ListDeliveries, self::ListRoleAssignments, self::ListWebhookEndpoints => true,
            default => false,
        };
    }
}
