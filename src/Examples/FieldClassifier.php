<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\FieldConstraints;
use Oxhq\Oxcribe\Examples\Data\FieldHints;

final class FieldClassifier
{
    public function __construct(
        private readonly ResourceContextResolver $resourceContextResolver = new ResourceContextResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $knownPaths
     */
    public function classify(string $path, string $location, array $metadata, EndpointExampleContext $endpoint, array $knownPaths = []): ExampleField
    {
        $name = $this->leafName($path);
        $baseType = $this->baseType($location, $metadata);
        $format = $this->stringValue($metadata['format'] ?? null);
        $allowedValues = $this->stringList($metadata['allowedValues'] ?? null);

        $semanticSources = [];
        $semanticVia = [];
        $confidence = 0.2;
        $semanticType = $this->semanticType($name, $path, $location, $baseType, $format, $allowedValues, $metadata, $endpoint, $semanticSources, $semanticVia, $confidence);

        $confirmedWith = null;
        if ($semanticType === 'password' && in_array($location.'.password_confirmation', $knownPaths, true)) {
            $confirmedWith = 'password_confirmation';
            $semanticSources[] = 'field_relationship';
            $semanticVia[] = 'password_confirmation';
            $confidence = min(1.0, $confidence + 0.2);
        }

        $constraints = new FieldConstraints(
            enum: $allowedValues,
            exists: $this->existsConstraint($metadata),
            confirmedWith: $confirmedWith,
            format: $format !== '' ? $format : null,
        );

        return new ExampleField(
            name: $name,
            path: $location.'.'.$path,
            location: $location,
            baseType: $baseType,
            semanticType: $semanticType,
            required: (bool) ($metadata['required'] ?? false),
            nullable: (bool) ($metadata['nullable'] ?? false),
            collection: (bool) ($metadata['collection'] ?? false) || (bool) ($metadata['isArray'] ?? false),
            itemType: $this->stringValue($metadata['itemType'] ?? null) !== '' ? $this->stringValue($metadata['itemType'] ?? null) : null,
            constraints: $constraints,
            hints: new FieldHints(
                confidence: round(min(1.0, $confidence), 2),
                source: $this->stableList($semanticSources),
                via: $this->stableList(array_merge($semanticVia, $this->metadataVia($metadata))),
            ),
            format: $format !== '' ? $format : null,
            allowedValues: $allowedValues,
        );
    }

    /**
     * @param  list<string>  $allowedValues
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $sources
     * @param  list<string>  $via
     */
    private function semanticType(
        string $name,
        string $fieldPath,
        string $location,
        string $baseType,
        string $format,
        array $allowedValues,
        array $metadata,
        EndpointExampleContext $endpoint,
        array &$sources,
        array &$via,
        float &$confidence,
    ): string {
        $normalized = $this->normalizeName($name);
        $operationKind = strtolower($endpoint->operationKind);
        $path = strtolower($endpoint->path);

        if ($format !== '') {
            $semantic = match ($format) {
                'email' => 'email',
                'uri', 'url' => 'url',
                'uuid' => 'uuid',
                'date-time' => 'datetime',
                'date' => 'date',
                default => null,
            };

            if ($semantic !== null) {
                $sources[] = 'validation_rule';
                $via[] = 'format:'.$format;
                $confidence += 0.4;

                return $semantic;
            }
        }

        if (($metadata['bindingTarget'] ?? null) !== null && $location === 'path') {
            $sources[] = 'route_binding';
            $via[] = 'binding';
            $confidence += 0.4;

            return 'foreign_key_id';
        }

        if ($allowedValues !== []) {
            $sources[] = 'validation_rule';
            $via[] = 'enum';
            $confidence += 0.4;

            if (in_array($normalized, ['role', 'status', 'state', 'type'], true)) {
                $sources[] = 'field_name';
                $via[] = $normalized;
                $confidence += 0.4;

                return $normalized;
            }

            return 'enum';
        }

        if (str_ends_with($normalized, '_ids')) {
            $sources[] = 'field_name';
            $via[] = $normalized;
            $confidence += 0.4;

            return 'foreign_key_id';
        }

        if (str_ends_with($normalized, '_id') || $normalized === 'id') {
            $sources[] = 'field_name';
            $via[] = $normalized;
            $confidence += 0.4;

            return 'foreign_key_id';
        }

        $aliasSemantic = $this->semanticAlias($normalized, $fieldPath, $operationKind, $path);
        if ($aliasSemantic !== null) {
            $sources[] = 'field_name';
            $via[] = $normalized;
            $confidence += 0.4;

            if ($aliasSemantic === 'company_name' && (str_contains($operationKind, 'companies.') || str_contains($path, '/companies'))) {
                $sources[] = 'endpoint_context';
                $via[] = 'companies';
                $confidence += 0.2;
            }

            if ($aliasSemantic === 'email' && str_contains($operationKind, 'auth.login')) {
                $sources[] = 'endpoint_context';
                $via[] = 'auth.login';
                $confidence += 0.2;
            }

            return $aliasSemantic;
        }

        if ($baseType === 'boolean') {
            return 'boolean';
        }
        if ($baseType === 'integer') {
            return 'integer';
        }
        if ($baseType === 'number') {
            if (in_array($normalized, ['amount', 'total', 'subtotal', 'price', 'cost', 'fee'], true)) {
                $sources[] = 'field_name';
                $via[] = $normalized;
                $confidence += 0.4;

                return 'amount';
            }

            return 'number';
        }
        if ($baseType === 'array') {
            return 'array';
        }
        if ($baseType === 'object') {
            return 'object';
        }

        return 'string';
    }

    private function semanticAlias(string $name, string $fieldPath, string $operationKind, string $path): ?string
    {
        if ($name === 'identifier' && str_contains($operationKind, 'auth.login')) {
            return 'email';
        }

        $aliases = [
            'password_confirmation' => ['password_confirmation'],
            'password' => ['password', 'new_password', 'current_password'],
            'email' => ['email', 'email_address', 'user_email', 'contact_email'],
            'first_name' => ['first_name', 'firstname', 'given_name'],
            'last_name' => ['last_name', 'lastname', 'family_name', 'surname'],
            'username' => ['username', 'user_name', 'handle'],
            'full_name' => ['full_name', 'display_name'],
            'phone' => ['phone', 'phone_number', 'mobile', 'cellphone', 'whatsapp'],
            'company_name' => ['company', 'company_name', 'business_name', 'organization', 'org_name'],
            'domain' => ['domain'],
            'url' => ['url', 'website', 'callback_url', 'avatar_url', 'image_url', 'image', 'avatar', 'thumbnail', 'logo', 'banner', 'cover', 'social_link', 'social_links'],
            'title' => ['title', 'headline'],
            'genre' => ['genre'],
            'icon_name' => ['icon'],
            'label' => ['label'],
            'kind' => ['type', 'kind'],
            'attribute_value' => ['value'],
            'color' => ['color', 'accent_color', 'hex_color'],
            'search_term' => ['search', 'query', 'q', 'term', 'keyword'],
            'page_size' => ['limit', 'per_page', 'page_size', 'pageSize'],
            'platform' => ['platform', 'primary_platform'],
            'language' => ['language', 'locale'],
            'role' => ['role'],
            'creator_role' => ['creator_type', 'role_type'],
            'gender' => ['gender'],
            'tagline' => ['tagline', 'bio', 'summary', 'description'],
            'highlight' => ['highlight', 'highlights'],
            'timeslot' => ['timeslot', 'availability', 'slot'],
            'search_prefix' => ['starts_with', 'begins_with', 'prefix'],
            'message' => ['message'],
            'error_message' => ['error', 'errors', 'error_message'],
            'note' => ['note', 'notes'],
            'request_payload' => ['request'],
            'json_blob' => ['properties', 'extra'],
            'status' => ['status'],
            'slug' => ['slug', 'list'],
            'uuid' => ['uuid'],
            'ulid' => ['ulid'],
            'token' => ['token', 'access_token', 'refresh_token'],
            'api_key' => ['api_key', 'apikey', 'secret'],
            'amount' => ['amount', 'total', 'subtotal', 'price', 'cost', 'fee'],
            'percentage' => ['percentage', 'percent'],
            'quantity' => ['quantity', 'qty'],
            'date' => ['date', 'start_date', 'end_date'],
            'datetime' => ['created_at', 'updated_at', 'deleted_at', 'expires_at'],
            'postal_code' => ['postal_code', 'zip', 'zip_code'],
            'city' => ['city'],
            'state' => ['state'],
            'country' => ['country'],
            'workspace_name' => ['workspace_name'],
            'project_name' => ['project_name'],
            'collection_name' => ['collection_name'],
        ];

        foreach ($aliases as $semantic => $values) {
            if (in_array($name, $values, true)) {
                return $semantic;
            }
        }

        if ($name === 'name') {
            return match ($this->resourceContextResolver->resolve($fieldPath, $operationKind, $path)) {
                'organization' => 'company_name',
                'workspace' => 'workspace_name',
                'project' => 'project_name',
                'collection' => 'collection_name',
                'game', 'content' => 'title',
                'person' => 'full_name',
                default => $this->resourcePrefersTitles($operationKind, $path) ? 'title' : 'full_name',
            };
        }

        return null;
    }

    private function resourcePrefersTitles(string $operationKind, string $path): bool
    {
        foreach (['games', 'posts', 'articles', 'pages', 'videos', 'series', 'streams', 'movies', 'episodes'] as $needle) {
            if (str_contains($operationKind, $needle.'.') || str_contains($path, '/'.$needle)) {
                return true;
            }
        }

        return false;
    }

    private function baseType(string $location, array $metadata): string
    {
        $scalarType = $this->stringValue($metadata['scalarType'] ?? null);
        if ($scalarType !== '') {
            return $scalarType;
        }

        $type = $this->stringValue($metadata['type'] ?? null);
        if (($metadata['collection'] ?? false) === true || ($metadata['isArray'] ?? false) === true || $type === 'array') {
            return 'array';
        }

        if ($location === 'files' || $this->stringValue($metadata['kind'] ?? null) === 'file') {
            return 'string';
        }

        if ($type !== '') {
            return $type;
        }

        return 'string';
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>|null
     */
    private function existsConstraint(array $metadata): ?array
    {
        $target = $this->stringValue($metadata['bindingTarget'] ?? null);
        if ($target === '') {
            return null;
        }

        return [
            'target' => $target,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    private function metadataVia(array $metadata): array
    {
        $via = [];
        foreach (['source', 'via'] as $key) {
            $value = $this->stringValue($metadata[$key] ?? null);
            if ($value !== '') {
                $via[] = $value;
            }
        }

        return $via;
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name) ?? $name;

        return strtolower(str_replace('-', '_', $name));
    }

    private function leafName(string $path): string
    {
        $normalized = str_replace('[]', '', $path);
        $segments = array_values(array_filter(explode('.', $normalized)));

        return $segments[array_key_last($segments)] ?? $normalized;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        return $this->stableList($items);
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function stableList(array $items): array
    {
        $items = array_values(array_unique($items));
        sort($items);

        return $items;
    }
}
