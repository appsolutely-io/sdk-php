<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * Where dissolving an organisation stands: before it has started, a preview
 * of what dissolving it would do; once it has, its progress. Exactly one of
 * the two is set.
 */
final readonly class OrganisationDissolution implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationDissolution';

    /**
     * @param OrganisationDissolutionPreview|null $preview what dissolving would do, null once it has started
     * @param OrganisationDissolutionProgress|null $progress how far dissolving has got, null before it has started
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public ?OrganisationDissolutionPreview $preview,
        public ?OrganisationDissolutionProgress $progress,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        // Only the progress carries a state.
        $started = $json->has('state');

        return new self(
            preview: $started ? null : OrganisationDissolutionPreview::from($json),
            progress: $started ? OrganisationDissolutionProgress::from($json) : null,
            extra: $json->extra(),
        );
    }
}
