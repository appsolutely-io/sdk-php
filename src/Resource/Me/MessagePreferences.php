<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\MessagePreference;

final readonly class MessagePreferences
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Page<MessagePreference> */
    #[Endpoint(Operation::ListMeMessagePreferences)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListMeMessagePreferences, MessagePreference::class);
    }

    /**
     * @param list<array{category: string, channel: string, enabled: bool}> $preferences
     * @return Page<MessagePreference>
     */
    #[Endpoint(Operation::UpdateMeMessagePreference)]
    public function update(#[\SensitiveParameter] array $preferences): Page
    {
        $response = $this->caller->send(Operation::UpdateMeMessagePreference, body: ['preferences' => $preferences]);

        return Page::fromResponse($response, Operation::UpdateMeMessagePreference->path())->map(static fn(#[\SensitiveParameter] array $item): MessagePreference => MessagePreference::from(Fields::of($item, MessagePreference::SCHEMA)));
    }
}
