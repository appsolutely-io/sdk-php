<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\FormEntry;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `form.submitted`: the entry as the site's REST API serves it, whose `form`
 * names the form by slug, and the form's name beside it.
 */
final readonly class FormSubmittedEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::FORM_SUBMITTED,
    ];

    public function __construct(
        Event $envelope,
        public FormEntry $entry,
        public string $formName,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self(
            $envelope,
            FormEntry::from($data->except('form_name')),
            $data->string('form_name'),
        );
    }
}
