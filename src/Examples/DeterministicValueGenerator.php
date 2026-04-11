<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Illuminate\Support\Str;
use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\ScenarioContext;

final class DeterministicValueGenerator
{
    public function __construct(
        private readonly ResourceContextResolver $resourceContextResolver = new ResourceContextResolver,
    ) {}

    public function generate(ExampleField $field, ScenarioContext $context, ?int $index = null, ?EndpointExampleContext $endpoint = null): mixed
    {
        $salt = $field->path.($index !== null ? '#'.$index : '');

        return match ($field->semanticType) {
            'email' => $index === null ? ($context->person?->email ?? $this->indexedEmail($context, $salt)) : $this->indexedEmail($context, $salt),
            'password', 'password_confirmation' => $context->auth?->password ?? 'Str0ng!Pass2026',
            'first_name' => $index === null ? ($context->person?->firstName ?? $this->indexedFirstName($context, $salt)) : $this->indexedFirstName($context, $salt),
            'last_name' => $index === null ? ($context->person?->lastName ?? $this->indexedLastName($context, $salt)) : $this->indexedLastName($context, $salt),
            'full_name' => $index === null ? ($context->person?->fullName ?? $this->indexedFullName($context, $salt)) : $this->indexedFullName($context, $salt),
            'title' => $this->titleLikeValue($field, $context, $endpoint, $salt, $index),
            'genre' => $this->pick(['Action RPG', 'Tactical Shooter', 'MOBA', 'Battle Royale', 'Kart Racer', 'Indie Platformer'], $context->seed, $salt),
            'domain' => $this->domainValue($field, $context, $endpoint, $salt, $index),
            'icon_name' => $this->descriptorProfile($field, $context, $endpoint, $salt)['icon'],
            'label' => $this->descriptorProfile($field, $context, $endpoint, $salt)['label'],
            'kind' => $this->descriptorProfile($field, $context, $endpoint, $salt)['kind'],
            'attribute_value' => $this->descriptorProfile($field, $context, $endpoint, $salt)['value'],
            'color' => $this->pick(['#FF6B6B', '#4ECDC4', '#F7B801', '#6C5CE7', '#00B894', '#0984E3'], $context->seed, $salt),
            'search_term' => $this->searchTermValue($field, $context, $endpoint, $salt),
            'page_size' => $this->pageSizeValue($context->seed, $salt),
            'platform' => $this->pick(['Twitch', 'YouTube', 'TikTok', 'Kick', 'Discord'], $context->seed, $salt),
            'language' => $this->pick(['English', 'Spanish', 'Portuguese', 'French'], $context->seed, $salt),
            'creator_role' => $this->pick(['Streamer', 'Caster', 'Analyst', 'Host', 'Coach'], $context->seed, $salt),
            'gender' => $this->pick(['Female', 'Male', 'Non-binary'], $context->seed, $salt),
            'tagline' => $this->taglineValue($field, $context, $endpoint, $salt),
            'highlight' => $this->pick(['Top 8 finish at the last major.', 'Daily ranked grind with community co-streams.', 'Featured on this week\'s tournament recap.'], $context->seed, $salt),
            'timeslot' => $this->pick(['Weeknights', 'Weekends', 'Afternoons', 'Late nights'], $context->seed, $salt),
            'message' => $this->pick(['Operation completed successfully.', 'Request accepted and queued.', 'Resource updated successfully.'], $context->seed, $salt),
            'error_message' => $this->pick(['Something went wrong while processing the request.', 'Unable to complete the action right now.', 'The server could not process this request.'], $context->seed, $salt),
            'status' => $this->pick(['active', 'live', 'draft', 'scheduled'], $context->seed, $salt),
            'note' => $this->noteValue($field, $context, $endpoint, $salt),
            'request_payload' => $this->requestPayloadValue($field, $context, $endpoint, $salt),
            'json_blob' => $this->jsonBlobValue($context, $salt),
            'username' => $index === null ? ($context->person?->username ?? $this->indexedUsername($context, $salt)) : $this->indexedUsername($context, $salt),
            'phone' => $index === null ? ($context->person?->phone ?? $this->indexedPhone($context, $salt)) : $this->indexedPhone($context, $salt),
            'company_name' => $this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToCompany: true),
            'workspace_name' => $this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToWorkspace: true),
            'project_name' => $this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToProject: true),
            'collection_name' => $this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToCollection: true),
            'search_prefix' => $this->searchPrefixValue($field, $context, $endpoint, $salt),
            'url' => $this->urlValue($field, $context),
            'slug' => Str::slug($this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToCompany: true)),
            'uuid' => $this->uuid($context->seed, $salt),
            'ulid' => $this->ulid($context->seed, $salt),
            'token' => $context->auth?->token ?? 'tok_test_8f4a1c29b2',
            'api_key' => $context->auth?->apiKey ?? 'oxc_live_3baf9c1d8a',
            'amount' => $this->decimal($context->seed, $salt, 99, 499),
            'percentage' => $this->integer($context->seed, $salt, 5, 95),
            'quantity' => $this->integer($context->seed, $salt, 1, 5),
            'foreign_key_id' => $this->integer($context->seed, $salt, 1, 999),
            'date' => $this->dateValue($context->seed, $salt),
            'datetime' => $this->dateTimeValue($context->seed, $salt),
            'postal_code' => $this->digits($context->seed, $salt, 5),
            'city' => $this->pick(['Tijuana', 'Monterrey', 'Guadalajara', 'Merida'], $context->seed, $salt),
            'state' => $this->pick(['Baja California', 'Jalisco', 'Nuevo Leon', 'Yucatan'], $context->seed, $salt),
            'country' => 'Mexico',
            'role' => $field->allowedValues[0] ?? $this->pick(['member', 'editor', 'admin'], $context->seed, $salt),
            'state' => $field->allowedValues[0] ?? $this->pick(['active', 'draft', 'archived'], $context->seed, $salt),
            'type' => $field->allowedValues[0] ?? $this->pick(['manual', 'automatic', 'primary'], $context->seed, $salt),
            'enum' => $field->allowedValues[0] ?? 'default',
            'boolean' => true,
            'integer' => $this->integer($context->seed, $salt, 1, 999),
            'number' => $this->decimal($context->seed, $salt, 1, 999),
            default => $this->fallbackValue($field, $context, $salt, $endpoint, $index),
        };
    }

    public function generateCollectionItem(ExampleField $field, ScenarioContext $context, int $index, ?EndpointExampleContext $endpoint = null): mixed
    {
        $normalizedName = $this->normalizedFieldName($field);

        if (str_contains($normalizedName, 'platform_account')) {
            return [
                'platform' => $this->pick(['Twitch', 'YouTube', 'TikTok', 'Kick'], $context->seed, $field->path.'#platform#'.$index),
                'handle' => $this->indexedUsername($context, $field->path.'#handle#'.$index),
            ];
        }

        if (str_contains($normalizedName, 'workspace') || str_ends_with($normalizedName, '_ids') || str_ends_with($normalizedName, 'ids')) {
            return $this->integer($context->seed, $field->path.'#item#'.$index, 1, 999);
        }

        if (str_contains($normalizedName, 'social_link')) {
            return $this->urlValue($field, $context);
        }

        if (str_contains($normalizedName, 'highlight')) {
            return $this->pick(['Top 8 finish at the last major.', 'Daily ranked grind with community co-streams.', 'Featured on this week\'s tournament recap.'], $context->seed, $field->path.'#item#'.$index);
        }

        if (str_contains($normalizedName, 'error')) {
            return $this->pick(['Something went wrong while processing the request.', 'Unable to complete the action right now.', 'The server could not process this request.'], $context->seed, $field->path.'#item#'.$index);
        }

        if (str_contains($normalizedName, 'tag')) {
            return $this->pick(['featured', 'weekly-pick', 'high-engagement', 'priority'], $context->seed, $field->path.'#item#'.$index);
        }

        if (! in_array($field->semanticType, ['array', 'object'], true) && $field->baseType !== 'array' && $field->baseType !== 'object') {
            return $this->generate($field, $context, $index, $endpoint);
        }

        return $this->pick([
            $this->slugPart($field->name).'-primary',
            $this->slugPart($field->name).'-secondary',
            $this->slugPart($field->name).'-featured',
        ], $context->seed, $field->path.'#item#'.$index);
    }

    private function fallbackValue(ExampleField $field, ScenarioContext $context, string $salt, ?EndpointExampleContext $endpoint = null, ?int $index = null): mixed
    {
        return match ($field->baseType) {
            'boolean' => true,
            'integer' => $this->integer($context->seed, $salt, 1, 999),
            'number' => $this->decimal($context->seed, $salt, 1, 999),
            'object' => $this->objectFallback($field, $context, $salt),
            default => $this->stringFallback($field, $context, $salt, $endpoint, $index),
        };
    }

    private function stringFallback(ExampleField $field, ScenarioContext $context, string $salt, ?EndpointExampleContext $endpoint = null, ?int $index = null): string
    {
        if ($field->allowedValues !== []) {
            return $field->allowedValues[0];
        }

        return match ($field->name) {
            'name' => $this->entityLabel($field, $context, $endpoint, $salt, $index),
            'account' => $this->indexedUsername($context, $salt),
            'list' => $this->pick(['featured-broadcasts', 'top-clips', 'partner-watchlist'], $context->seed, $salt),
            'path' => $field->location === 'path' ? 'exports/weekly-report.csv' : 'media/uploads/avatar.jpg',
            'workspace' => Str::slug($this->workspaceNameValue($context, $this->entityScopeSalt($field, $salt, $index, $endpoint))),
            'domain' => $this->domainValue($field, $context, $endpoint, $salt, $index),
            'starts_with' => $this->searchPrefixValue($field, $context, $endpoint, $salt),
            'data' => 'saved',
            default => 'example_'.$this->slugPart($field->name).'_'.$this->hashSuffix($context->seed, $salt, 4),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function objectFallback(ExampleField $field, ScenarioContext $context, string $salt): array
    {
        if ($field->semanticType === 'json_blob') {
            return [
                'region' => 'North America',
                'priority' => $this->pick(['high', 'medium', 'low'], $context->seed, $salt.':priority'),
            ];
        }

        return [
            'id' => $this->integer($context->seed, $salt.':id', 1, 999),
            'label' => ucfirst($this->slugPart($field->name)),
        ];
    }

    private function titleValue(ScenarioContext $context, string $salt): string
    {
        $prefix = $this->pick($this->gameTitlePrefixes(), $context->seed, $salt.':prefix');
        $suffix = $this->pick($this->gameTitleSuffixes(), $context->seed, $salt.':suffix');

        return $prefix.' '.$suffix;
    }

    private function titleLikeValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt, ?int $index = null): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');

        return in_array($resource, ['game', 'content', 'project'], true)
            ? $this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToProject: true)
            : $this->titleValue($context, $salt);
    }

    private function jsonBlobValue(ScenarioContext $context, string $salt): string
    {
        return json_encode([
            'region' => 'North America',
            'priority' => $this->pick(['high', 'medium', 'low'], $context->seed, $salt.':priority'),
            'focus' => $this->pick(['watch parties', 'competitive shooters', 'creator campaigns'], $context->seed, $salt.':focus'),
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function domainValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt, ?int $index = null): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');
        if (in_array($resource, ['organization', 'workspace', 'project'], true)) {
            return Str::slug($this->entityLabel($field, $context, $endpoint, $salt, $index, fallbackToCompany: true, fallbackToWorkspace: true, fallbackToProject: true)).'.gg';
        }

        $prefix = $this->pick(['arena', 'creatorlab', 'velocity', 'watchparty', 'loaded'], $context->seed, $salt);

        return $prefix.'.gg';
    }

    private function pageSizeValue(string $seed, string $salt): int
    {
        $values = [10, 12, 20, 25, 50];
        $hash = hash('sha256', $seed.'|'.$salt);
        $index = hexdec(substr($hash, 0, 8)) % count($values);

        return $values[$index];
    }

    private function indexedFirstName(ScenarioContext $context, string $salt): string
    {
        $values = ['Ana', 'Carlos', 'Elena', 'Mateo', 'Sofia', 'Diego', 'Lucia', 'Javier'];

        return $this->pick($values, $context->seed, $salt);
    }

    private function indexedLastName(ScenarioContext $context, string $salt): string
    {
        $values = ['Lopez', 'Mendez', 'Torres', 'Garcia', 'Navarro', 'Vega', 'Santos', 'Reyes'];

        return $this->pick($values, $context->seed, $salt);
    }

    private function indexedFullName(ScenarioContext $context, string $salt): string
    {
        return $this->indexedFirstName($context, $salt).' '.$this->indexedLastName($context, $salt);
    }

    private function indexedUsername(ScenarioContext $context, string $salt): string
    {
        return Str::slug($this->indexedFullName($context, $salt), '_');
    }

    private function indexedEmail(ScenarioContext $context, string $salt): string
    {
        $username = $this->indexedUsername($context, $salt);
        $domain = $context->company?->domain ?? 'example.test';

        return $username.'@'.$domain;
    }

    private function indexedPhone(ScenarioContext $context, string $salt): string
    {
        return '+52664'.$this->digits($context->seed, $salt, 7);
    }

    private function urlValue(ExampleField $field, ScenarioContext $context): string
    {
        $lowerName = strtolower($field->name);
        if (str_contains($lowerName, 'callback')) {
            return 'https://example.test/callback';
        }
        if (str_contains($lowerName, 'avatar') || str_contains($lowerName, 'image')) {
            return 'https://images.example.test/avatar.jpg';
        }

        return $context->company?->website ?? 'https://example.test';
    }

    private function searchPrefixValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');
        $label = match ($resource) {
            'organization' => $this->organizationNameValue($context, 'resource:organization:'.$salt),
            'workspace' => $this->workspaceNameValue($context, 'resource:workspace:'.$salt),
            'project' => $this->projectNameValue($context, 'resource:project:'.$salt),
            'collection' => $this->collectionNameValue($context, 'resource:collection:'.$salt),
            'game', 'content' => $this->gameTitlePrefixValue($context, $endpoint, $salt),
            default => $this->pick(['league', 'creator', 'valorant', 'fifa', 'streamer', 'tournament'], $context->seed, $salt),
        };

        $parts = preg_split('/[\s-]+/', strtolower($label)) ?: [];
        $prefix = trim((string) ($parts[0] ?? ''));

        return $prefix !== '' ? $prefix : strtolower($this->slugPart($label));
    }

    private function searchTermValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');

        return match ($resource) {
            'organization' => Str::lower($this->organizationNameValue($context, 'resource:organization:'.$salt)),
            'workspace' => Str::lower($this->workspaceNameValue($context, 'resource:workspace:'.$salt)),
            'project' => Str::lower($this->projectNameValue($context, 'resource:project:'.$salt)),
            'collection' => Str::lower($this->collectionNameValue($context, 'resource:collection:'.$salt)),
            'game', 'content' => Str::lower($this->gameTitlePrefixValue($context, $endpoint, $salt)),
            'person' => $this->pick(['creator', 'streamer', 'caster', 'host', 'analyst'], $context->seed, $salt),
            default => $this->pick(['league', 'creator', 'valorant', 'fifa', 'streamer', 'tournament'], $context->seed, $salt),
        };
    }

    private function entityLabel(
        ExampleField $field,
        ScenarioContext $context,
        ?EndpointExampleContext $endpoint,
        string $salt,
        ?int $index = null,
        bool $fallbackToCompany = false,
        bool $fallbackToWorkspace = false,
        bool $fallbackToProject = false,
        bool $fallbackToCollection = false,
    ): string {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');
        $scopeSalt = $this->entityScopeSalt($field, $salt, $index, $endpoint);

        return match ($resource) {
            'organization' => $this->organizationNameValue($context, $scopeSalt),
            'workspace' => $this->workspaceNameValue($context, $scopeSalt),
            'project' => $this->projectNameValue($context, $scopeSalt),
            'collection' => $this->collectionNameValue($context, $scopeSalt),
            'game', 'content' => $this->gameTitleValue($context, $endpoint, $scopeSalt, $index),
            'person' => $index === null ? ($context->person?->fullName ?? $this->indexedFullName($context, $scopeSalt)) : $this->indexedFullName($context, $scopeSalt),
            default => match (true) {
                $fallbackToCompany => $this->organizationNameValue($context, $scopeSalt),
                $fallbackToWorkspace => $this->workspaceNameValue($context, $scopeSalt),
                $fallbackToProject => $this->projectNameValue($context, $scopeSalt),
                $fallbackToCollection => $this->collectionNameValue($context, $scopeSalt),
                default => $index === null ? ($context->person?->fullName ?? $this->indexedFullName($context, $scopeSalt)) : $this->indexedFullName($context, $scopeSalt),
            },
        };
    }

    private function entityScopeSalt(ExampleField $field, string $salt, ?int $index = null, ?EndpointExampleContext $endpoint = null): string
    {
        $path = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $field->path) ?? $field->path;
        $path = strtolower(str_replace('.*', '[]', $path));
        $segments = array_values(array_filter(explode('.', $path)));
        array_pop($segments);
        $scope = implode('.', $segments);
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '') ?? 'resource';

        return $resource.'|'.($scope !== '' ? $scope : $field->location).($index !== null ? '#'.$index : '');
    }

    private function organizationNameValue(ScenarioContext $context, string $salt): string
    {
        $prefix = $this->pick(['Loaded', 'Northwind', 'Atlas', 'Nimbus', 'Summit', 'Vertex', 'Crimson'], $context->seed, $salt.':prefix');
        $suffix = $this->pick(['Gaming', 'Collective', 'Studios', 'League', 'Squad', 'Network', 'HQ'], $context->seed, $salt.':suffix');

        return $prefix.' '.$suffix;
    }

    private function workspaceNameValue(ScenarioContext $context, string $salt): string
    {
        $prefix = $this->pick(['Creator', 'Broadcast', 'Community', 'Partner', 'Roster', 'Launch', 'Pulse', 'Ops'], $context->seed, $salt.':prefix');
        $suffix = $this->pick(['Lab', 'Desk', 'Ops', 'Hub', 'Room', 'Watchlist', 'Control', 'Studio'], $context->seed, $salt.':suffix');

        return $prefix.' '.$suffix;
    }

    private function projectNameValue(ScenarioContext $context, string $salt): string
    {
        $prefix = $this->pick(['Creator Graph', 'Workspace Pulse', 'Roster Sync', 'Broadcast Ops', 'Partner Intel', 'Live Status'], $context->seed, $salt.':prefix');
        $suffix = $this->pick(['API', 'Gateway', 'Surface', 'Service'], $context->seed, $salt.':suffix');

        return $prefix.' '.$suffix;
    }

    private function collectionNameValue(ScenarioContext $context, string $salt): string
    {
        $prefix = $this->pick(['Featured', 'Priority', 'Partner', 'Weekly', 'Live', 'Creator'], $context->seed, $salt.':prefix');
        $suffix = $this->pick(['Watchlist', 'Roster', 'Folder', 'Collection', 'Directory', 'Board'], $context->seed, $salt.':suffix');

        return $prefix.' '.$suffix;
    }

    private function gameTitlePrefixValue(ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): string
    {
        $seed = $endpoint === null
            ? $salt
            : 'game-title-prefix|'.$endpoint->path.'|'.$endpoint->operationKind;

        return $this->pick($this->gameTitlePrefixes(), $context->seed, $seed);
    }

    private function gameTitleValue(ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt, ?int $index = null): string
    {
        $prefix = $this->gameTitlePrefixValue($context, $endpoint, $salt);
        $suffixSalt = $salt.':suffix'.($index !== null ? '#'.$index : '');
        $suffix = $this->pick($this->gameTitleSuffixes(), $context->seed, $suffixSalt);

        return $prefix.' '.$suffix;
    }

    /**
     * @return list<string>
     */
    private function gameTitlePrefixes(): array
    {
        return ['Neon', 'Phantom', 'Velocity', 'Shadow', 'Orbit', 'Crimson'];
    }

    /**
     * @return list<string>
     */
    private function gameTitleSuffixes(): array
    {
        return ['Protocol', 'Arena', 'Frontier', 'Rush', 'Echo', 'Division'];
    }

    /**
     * @return array{icon: string, label: string, kind: string, value: string}
     */
    private function descriptorProfile(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): array
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');
        $scope = $this->entityScopeSalt($field, $salt, null, $endpoint);

        $profiles = match ($resource) {
            'organization' => [
                ['icon' => 'globe', 'label' => 'Domain', 'kind' => 'profile', 'value' => $this->domainValue($field, $context, $endpoint, $scope)],
                ['icon' => 'location', 'label' => 'Region', 'kind' => 'profile', 'value' => 'North America'],
                ['icon' => 'calendar', 'label' => 'Cadence', 'kind' => 'availability', 'value' => 'Weekly planning sync'],
            ],
            'workspace' => [
                ['icon' => 'calendar', 'label' => 'Cadence', 'kind' => 'availability', 'value' => 'Weekdays at 10 AM PT'],
                ['icon' => 'globe', 'label' => 'Region', 'kind' => 'profile', 'value' => 'North America'],
                ['icon' => 'discord', 'label' => 'Community', 'kind' => 'social', 'value' => 'Discord-first ops'],
            ],
            'collection' => [
                ['icon' => 'calendar', 'label' => 'Review cadence', 'kind' => 'availability', 'value' => 'Weekly review'],
                ['icon' => 'globe', 'label' => 'Segment', 'kind' => 'profile', 'value' => 'Sponsor-ready creators'],
                ['icon' => 'discord', 'label' => 'Owner', 'kind' => 'social', 'value' => 'Creator Ops'],
            ],
            'game', 'content' => [
                ['icon' => 'globe', 'label' => 'Region', 'kind' => 'profile', 'value' => 'Global release'],
                ['icon' => 'calendar', 'label' => 'Season', 'kind' => 'highlight', 'value' => 'Current season'],
                ['icon' => 'location', 'label' => 'Category', 'kind' => 'profile', 'value' => 'Competitive title'],
            ],
            default => [
                ['icon' => 'twitch', 'label' => 'Platform', 'kind' => 'social', 'value' => 'Twitch'],
                ['icon' => 'globe', 'label' => 'Language', 'kind' => 'profile', 'value' => 'English / Spanish'],
                ['icon' => 'calendar', 'label' => 'Schedule', 'kind' => 'availability', 'value' => 'Weeknights at 7 PM PT'],
            ],
        };

        $index = hexdec(substr(hash('sha256', $context->seed.'|'.$scope.'|descriptor'), 0, 8)) % count($profiles);

        return $profiles[$index];
    }

    private function taglineValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');

        return match ($resource) {
            'organization' => $this->pick([
                'Partner-facing organization record for creator and workspace operations.',
                'Top-level organization surface for teams, workspaces, and partner workflows.',
            ], $context->seed, $salt),
            'workspace' => $this->pick([
                'Shared operating space for creator coverage, reporting, and campaign planning.',
                'Workspace-level control surface for live tracking, rosters, and creator reviews.',
            ], $context->seed, $salt),
            'collection' => $this->pick([
                'Curated shortlist of creators to review and activate this week.',
                'Saved collection used to track priority creators for follow-up.',
            ], $context->seed, $salt),
            'game', 'content' => $this->pick([
                'Competitive coverage and discovery surface for high-signal titles.',
                'Catalog-ready content example for discovery, rankings, and creator workflows.',
            ], $context->seed, $salt),
            default => $this->pick([
                'Competitive energy with community-first streams.',
                'Late-night ranked sessions and watch parties.',
                'Creator-led coverage for esports and games.',
            ], $context->seed, $salt),
        };
    }

    private function noteValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');

        return match ($resource) {
            'organization' => $this->pick([
                'Keep this organization focused on partner-ready teams and shared workspace access.',
                'Review workspace coverage before expanding member access.',
            ], $context->seed, $salt),
            'workspace' => $this->pick([
                'Keep this workspace focused on weekly creator operations and live reporting.',
                'Use this workspace for day-to-day roster and pulse review.',
            ], $context->seed, $salt),
            'collection' => $this->pick([
                'Keep this folder focused on sponsor-ready creators and short-term follow-up.',
                'Use this collection for the next review cycle only.',
            ], $context->seed, $salt),
            default => $this->pick([
                'Follow up after the next stream recap.',
                'Prioritize creators with strong watch-party engagement.',
                'Keep this list focused on weekly collaboration targets.',
            ], $context->seed, $salt),
        };
    }

    private function requestPayloadValue(ExampleField $field, ScenarioContext $context, ?EndpointExampleContext $endpoint, string $salt): string
    {
        $resource = $this->resourceContextResolver->resolve($field->path, $endpoint?->operationKind ?? '', $endpoint?->path ?? '');

        return match ($resource) {
            'organization' => $this->pick(['sync_org_workspaces', 'refresh_partner_roster', 'hydrate_org_catalog'], $context->seed, $salt),
            'workspace' => $this->pick(['hydrate_workspace_catalog', 'refresh_live_content_tables', 'sync_workspace_watchlist'], $context->seed, $salt),
            'collection' => $this->pick(['refresh_creators_list_metrics', 'hydrate_creators_list', 'sync_priority_creators'], $context->seed, $salt),
            'game', 'content' => $this->pick(['refresh_game_catalog', 'sync_featured_titles', 'hydrate_content_rankings'], $context->seed, $salt),
            default => $this->pick(['sync_recent_creators', 'refresh_live_content_tables', 'hydrate_workspace_catalog'], $context->seed, $salt),
        };
    }

    private function uuid(string $seed, string $salt): string
    {
        $hash = hash('sha256', $seed.'|'.$salt);

        return sprintf(
            '%s-%s-4%s-a%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12),
        );
    }

    private function ulid(string $seed, string $salt): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $hash = hash('sha256', $seed.'|'.$salt);
        $value = '01';

        for ($i = 0; strlen($value) < 26; $i++) {
            $value .= $alphabet[hexdec($hash[$i % strlen($hash)]) % strlen($alphabet)];
        }

        return substr($value, 0, 26);
    }

    private function integer(string $seed, string $salt, int $min, int $max): int
    {
        $range = max(1, $max - $min + 1);
        $hash = hash('sha256', $seed.'|'.$salt);

        return $min + (hexdec(substr($hash, 0, 8)) % $range);
    }

    private function decimal(string $seed, string $salt, int $min, int $max): float
    {
        $whole = $this->integer($seed, $salt.':whole', $min, $max);
        $fraction = $this->integer($seed, $salt.':fraction', 0, 99);

        return (float) sprintf('%d.%02d', $whole, $fraction);
    }

    private function dateValue(string $seed, string $salt): string
    {
        $day = $this->integer($seed, $salt.':day', 1, 27);
        $month = $this->integer($seed, $salt.':month', 1, 12);

        return sprintf('2026-%02d-%02d', $month, $day);
    }

    private function dateTimeValue(string $seed, string $salt): string
    {
        $date = $this->dateValue($seed, $salt);
        $hour = $this->integer($seed, $salt.':hour', 8, 18);
        $minute = $this->integer($seed, $salt.':minute', 0, 59);

        return sprintf('%sT%02d:%02d:00Z', $date, $hour, $minute);
    }

    /**
     * @param  list<string>  $values
     */
    private function pick(array $values, string $seed, string $salt): string
    {
        $hash = hash('sha256', $seed.'|'.$salt);
        $index = hexdec(substr($hash, 0, 8)) % count($values);

        return $values[$index];
    }

    private function digits(string $seed, string $salt, int $length): string
    {
        $hash = hash('sha256', $seed.'|'.$salt);
        $digits = '';

        for ($i = 0; strlen($digits) < $length && $i < strlen($hash); $i++) {
            $digits .= (string) (hexdec($hash[$i]) % 10);
        }

        return substr(str_pad($digits, $length, '5'), 0, $length);
    }

    private function slugPart(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'field';
    }

    private function normalizedFieldName(ExampleField $field): string
    {
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $field->name) ?? $field->name;

        return strtolower(str_replace('-', '_', $name));
    }

    private function hashSuffix(string $seed, string $salt, int $length): string
    {
        return substr(hash('sha256', $seed.'|'.$salt), 0, $length);
    }
}
