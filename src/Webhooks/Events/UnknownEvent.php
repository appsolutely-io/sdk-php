<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

/**
 * A type this client does not know. Its data is `$envelope->data`, as
 * decoded. The site's advice is to acknowledge a type you do not handle with
 * a 2xx, so a newly published type never turns into failed deliveries.
 */
final readonly class UnknownEvent extends TypedEvent {}
