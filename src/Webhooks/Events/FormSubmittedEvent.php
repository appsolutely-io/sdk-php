<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Form;
use Appsolutely\Sdk\Model\FormEntry;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `form.submitted`: the entry as the site's REST API serves it, and the form
 * it was submitted through.
 */
final readonly class FormSubmittedEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::FORM_SUBMITTED,
    ];

    public function __construct(
        Event $envelope,
        public FormEntry $entry,
        public Form $form,
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
            FormEntry::from($data->except('form')),
            Form::from($data->object('form')),
        );
    }
}
