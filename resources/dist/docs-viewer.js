(() => {
    const root = document.getElementById('oxcribe-docs-app');
    if (!root) {
        return;
    }

    const SEARCH_INPUT_ID = 'oxcribe-docs-search';

    const state = {
        docs: null,
        loading: true,
        error: '',
        selectedOperationId: null,
        mode: 'happy_path',
        scenarioKey: null,
        snippetKind: 'curl',
        search: '',
        selectedMethod: 'ALL',
        baseUrl: window.location.origin || '',
        bearerToken: '',
        requestPathParams: {},
        requestQueryParams: {},
        requestBodyModel: null,
        requestBodyText: 'null',
        requestBodyError: '',
        responseBodyText: 'null',
        tryStatus: 'Idle',
        tryResponseText: 'null',
        openGroups: {},
        hydratedKey: null,
    };

    const exampleModes = [
        { key: 'minimal_valid', label: 'Minimal' },
        { key: 'happy_path', label: 'Happy Path' },
        { key: 'realistic_full', label: 'Full' },
    ];

    function prettyJson(value) {
        return JSON.stringify(value ?? null, null, 2);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function copyText(value) {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(value);
            return;
        }

        const area = document.createElement('textarea');
        area.value = value;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }

    function normalizeBaseUrl(value) {
        return String(value || '').trim().replace(/\/+$/, '');
    }

    function isMachineLike(value) {
        const trimmed = String(value || '').trim();

        return trimmed.length > 48
            || /^[a-f0-9]{24,}$/i.test(trimmed)
            || trimmed.includes('::closure')
            || trimmed.split('.').some((segment) => segment.length > 24);
    }

    function singularize(value) {
        if (value.endsWith('ies')) {
            return `${value.slice(0, -3)}y`;
        }
        if (value.endsWith('sses')) {
            return value.slice(0, -2);
        }
        if (value.endsWith('s') && value.length > 3) {
            return value.slice(0, -1);
        }
        return value;
    }

    function titleize(value) {
        return String(value || '')
            .replace(/[_-]+/g, ' ')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
            .join(' ');
    }

    function operationTitle(operation) {
        const summary = String(operation?.summary || '').trim();
        if (summary && summary !== operation.path && !isMachineLike(summary)) {
            return summary;
        }

        const parts = String(operation?.path || '').split('/').filter(Boolean).filter((segment) => !segment.startsWith('{'));
        const resource = parts[parts.length - 1] || 'resource';
        const name = titleize(singularize(resource));
        const method = String(operation?.method || '').toUpperCase();

        switch (method) {
            case 'GET':
                return operation.path.includes('{') ? `Show ${name}` : `List ${titleize(resource) || 'Resources'}`;
            case 'POST':
                return `Create ${name}`;
            case 'PUT':
            case 'PATCH':
                return `Update ${name}`;
            case 'DELETE':
                return `Delete ${name}`;
            default:
                return `${method} ${name}`;
        }
    }

    function groupKeyForOperation(operation) {
        const tag = Array.isArray(operation.tags) && operation.tags.length > 0 ? String(operation.tags[0]).trim() : '';
        if (tag) {
            return tag;
        }

        const segment = String(operation.path || '')
            .split('/')
            .filter(Boolean)
            .find((part) => !part.startsWith('{'));

        return segment ? titleize(segment) : 'General';
    }

    function isObjectLike(value) {
        return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
    }

    function isSchema(value) {
        return isObjectLike(value);
    }

    function deepClone(value) {
        if (value === null || value === undefined) {
            return value;
        }

        return JSON.parse(JSON.stringify(value));
    }

    function refSchemaName(ref) {
        const match = String(ref || '').match(/^#\/components\/schemas\/(.+)$/);
        return match ? match[1] : null;
    }

    function resolveSchema(schema, componentsSchemas = {}) {
        if (!isSchema(schema)) {
            return null;
        }

        const normalized = schema;

        if (typeof normalized.$ref === 'string') {
            const refName = refSchemaName(normalized.$ref);
            if (refName && isSchema(componentsSchemas[refName])) {
                return resolveSchema(componentsSchemas[refName], componentsSchemas);
            }

            return normalized;
        }

        if (Array.isArray(normalized.anyOf) && normalized.anyOf.length > 0) {
            const preferred = normalized.anyOf.find((entry) => !(isSchema(entry) && entry.type === 'null')) ?? normalized.anyOf[0];
            const resolved = resolveSchema(preferred, componentsSchemas);

            if (!resolved) {
                return null;
            }

            return {
                ...resolved,
                nullable: normalized.anyOf.some((entry) => isSchema(entry) && entry.type === 'null'),
            };
        }

        if (Array.isArray(normalized.oneOf) && normalized.oneOf.length > 0) {
            return resolveSchema(normalized.oneOf[0], componentsSchemas);
        }

        if (Array.isArray(normalized.type) && normalized.type.length > 0) {
            const preferredType = normalized.type.find((entry) => entry !== 'null') ?? normalized.type[0];

            return {
                ...normalized,
                type: preferredType,
                nullable: normalized.type.includes('null'),
            };
        }

        return normalized;
    }

    function componentsSchemas() {
        return state.docs?.components?.schemas || {};
    }

    function resolveRequestContent(operation) {
        const content = operation?.requestBody?.content;
        if (!isObjectLike(content)) {
            return null;
        }

        const priorities = ['application/json', 'multipart/form-data', 'application/x-www-form-urlencoded'];
        const keys = Object.keys(content);
        const contentType = priorities.find((candidate) => candidate in content) ?? keys[0];

        if (!contentType || !isObjectLike(content[contentType])) {
            return null;
        }

        return {
            contentType,
            schema: resolveSchema(content[contentType].schema, componentsSchemas()),
        };
    }

    function buildParameterObjectSchema(operation, location) {
        const parameters = (operation?.parameters || []).filter((parameter) => parameter.in === location);
        if (parameters.length === 0) {
            return null;
        }

        const properties = Object.fromEntries(parameters.map((parameter) => [
            String(parameter.name),
            isSchema(parameter.schema) ? parameter.schema : { type: 'string' },
        ]));

        const required = parameters
            .filter((parameter) => Boolean(parameter.required))
            .map((parameter) => String(parameter.name));

        return {
            type: 'object',
            properties,
            required,
        };
    }

    function buildSchemaInitialValue(schema, arrayCount = 1, depth = 0) {
        if (depth > 8) {
            return null;
        }

        const resolved = resolveSchema(schema, componentsSchemas());
        if (!resolved) {
            return null;
        }

        if (resolved.example !== undefined) {
            return deepClone(resolved.example);
        }

        if (resolved.default !== undefined) {
            return deepClone(resolved.default);
        }

        if (Array.isArray(resolved.enum) && resolved.enum.length > 0) {
            return deepClone(resolved.enum[0]);
        }

        if ((resolved.type === 'object' || isObjectLike(resolved.properties)) && isObjectLike(resolved.properties)) {
            const required = Array.isArray(resolved.required) ? resolved.required : [];
            const value = {};

            Object.entries(resolved.properties).forEach(([key, childSchema]) => {
                if (!required.includes(key)) {
                    return;
                }

                value[key] = buildSchemaInitialValue(childSchema, arrayCount, depth + 1);
            });

            return value;
        }

        if (resolved.type === 'array') {
            const itemValue = buildSchemaInitialValue(resolved.items, arrayCount, depth + 1);
            return Array.from({ length: Math.max(arrayCount, 1) }, () => deepClone(itemValue));
        }

        if (resolved.format === 'binary') {
            return null;
        }

        switch (resolved.type) {
            case 'boolean':
                return true;
            case 'integer':
                return 1;
            case 'number':
                return 1.5;
            case 'string':
                if (resolved.format === 'date-time') {
                    return '2026-03-26T12:00:00Z';
                }
                if (resolved.format === 'date') {
                    return '2026-03-26';
                }
                if (resolved.format === 'email') {
                    return 'ana.lopez@example.test';
                }
                if (resolved.format === 'uuid') {
                    return '550e8400-e29b-41d4-a716-446655440000';
                }
                if (resolved.format === 'uri') {
                    return 'https://example.test/resource';
                }
                return 'example';
            default:
                return null;
        }
    }

    function isFileMarker(value) {
        return isObjectLike(value)
            && isObjectLike(value.__oxcribeFile)
            && typeof value.__oxcribeFile.name === 'string'
            && typeof value.__oxcribeFile.mimeType === 'string'
            && typeof value.__oxcribeFile.contentBase64 === 'string';
    }

    function containsFileMarkers(value) {
        if (isFileMarker(value)) {
            return true;
        }

        if (Array.isArray(value)) {
            return value.some((item) => containsFileMarkers(item));
        }

        if (isObjectLike(value)) {
            return Object.values(value).some((item) => containsFileMarkers(item));
        }

        return false;
    }

    function multipartEntries(value, prefix = '') {
        if (isFileMarker(value)) {
            return prefix ? [{ key: prefix, value }] : [];
        }

        if (Array.isArray(value)) {
            return value.flatMap((item, index) => multipartEntries(item, `${prefix}[${index}]`));
        }

        if (isObjectLike(value)) {
            return Object.entries(value).flatMap(([key, item]) =>
                multipartEntries(item, prefix ? `${prefix}[${key}]` : key)
            );
        }

        if (prefix === '' || value === null || value === undefined) {
            return [];
        }

        return [{ key: prefix, value: typeof value === 'string' ? value : JSON.stringify(value) }];
    }

    function shellQuote(value) {
        return `'${String(value).replace(/'/g, `'\"'\"'`)}'`;
    }

    function buildRequestUrl(baseUrl, operation, pathParams, queryParams) {
        let path = operation.path;

        Object.entries(pathParams || {}).forEach(([key, value]) => {
            path = path.replace(`{${key}}`, encodeURIComponent(String(value)));
        });

        const url = new URL(`${normalizeBaseUrl(baseUrl)}${path}`);

        Object.entries(queryParams || {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => url.searchParams.append(key, String(item)));
                return;
            }

            if (typeof value === 'object') {
                url.searchParams.set(key, JSON.stringify(value));
                return;
            }

            url.searchParams.set(key, String(value));
        });

        return url.toString();
    }

    function selectedOperation() {
        return filteredOperations().find((operation) => operation.id === state.selectedOperationId)
            || filteredOperations()[0]
            || null;
    }

    function selectedScenario(operation) {
        return availableScenarios(operation).find((scenario) => scenario.key === state.scenarioKey) || null;
    }

    function selectedExample(operation) {
        const scenario = selectedScenario(operation);
        if (scenario) {
            return {
                request: scenario.request,
                response: scenario.response,
            };
        }

        return operation?.examples?.[state.mode] || operation?.examples?.happy_path || null;
    }

    function availableScenarios(operation) {
        if (!operation) {
            return [];
        }

        return Object.values(operation.scenarios?.[state.mode] || {});
    }

    function buildLiveSnippets(operation, requestHeaders) {
        const method = String(operation.method || 'GET').toUpperCase();
        const url = buildRequestUrl(state.baseUrl, operation, state.requestPathParams, state.requestQueryParams);
        const headers = { ...requestHeaders };

        if (state.bearerToken) {
            headers.Authorization = `Bearer ${state.bearerToken}`;
        }

        const hasFiles = containsFileMarkers(state.requestBodyModel);

        if (hasFiles) {
            delete headers['Content-Type'];
            delete headers['content-type'];
        } else if (!['GET', 'HEAD'].includes(method) && state.requestBodyModel !== null && state.requestBodyModel !== undefined) {
            headers['Content-Type'] ??= 'application/json';
        }

        const headerLines = Object.entries(headers)
            .filter(([, value]) => value !== '')
            .map(([key, value]) => `-H ${shellQuote(`${key}: ${value}`)}`);

        const multipart = hasFiles ? multipartEntries(state.requestBodyModel) : [];
        const curlBody = hasFiles
            ? multipart.map(({ key, value }) => (
                isFileMarker(value)
                    ? `-F ${shellQuote(`${key}=@${value.__oxcribeFile.name}`)}`
                    : `-F ${shellQuote(`${key}=${value}`)}`
            )).join(' \\\n  ')
            : (!['GET', 'HEAD'].includes(method) && state.requestBodyModel !== null && state.requestBodyModel !== undefined
                ? `-d ${shellQuote(JSON.stringify(state.requestBodyModel, null, 2))}`
                : '');

        const fetchHeaders = Object.keys(headers).length > 0
            ? `headers: ${JSON.stringify(headers, null, 2)},\n`
            : '';

        const axiosHeaders = Object.keys(headers).length > 0
            ? `  headers: ${JSON.stringify(headers, null, 2)},\n`
            : '';

        const fetchBody = hasFiles
            ? `const formData = new FormData();\n${multipart.map(({ key, value }) => (
                isFileMarker(value)
                    ? `formData.append(${JSON.stringify(key)}, fileInput.files[0]);`
                    : `formData.append(${JSON.stringify(key)}, ${JSON.stringify(String(value))});`
            )).join('\n')}\n\n`
            : (!['GET', 'HEAD'].includes(method) && state.requestBodyModel !== null && state.requestBodyModel !== undefined
                ? `const body = ${JSON.stringify(state.requestBodyModel, null, 2)};\n\n`
                : '');

        const axiosBody = hasFiles
            ? `const formData = new FormData();\n${multipart.map(({ key, value }) => (
                isFileMarker(value)
                    ? `formData.append(${JSON.stringify(key)}, fileInput.files[0]);`
                    : `formData.append(${JSON.stringify(key)}, ${JSON.stringify(String(value))});`
            )).join('\n')}\n\n`
            : (!['GET', 'HEAD'].includes(method) && state.requestBodyModel !== null && state.requestBodyModel !== undefined
                ? `const payload = ${JSON.stringify(state.requestBodyModel, null, 2)};\n\n`
                : '');

        return {
            curl: [
                `curl -X ${method} ${shellQuote(url)}`,
                ...headerLines,
                curlBody,
            ].filter(Boolean).join(' \\\n  '),
            fetch: `${fetchBody}await fetch(${JSON.stringify(url)}, {\n  method: ${JSON.stringify(method)},\n  ${fetchHeaders}${hasFiles ? 'body: formData,\n' : (!['GET', 'HEAD'].includes(method) && state.requestBodyModel !== null && state.requestBodyModel !== undefined ? 'body: JSON.stringify(body),\n' : '')}});`,
            axios: `${axiosBody}await axios({\n  url: ${JSON.stringify(url)},\n  method: ${JSON.stringify(method.toLowerCase())},\n${axiosHeaders}${hasFiles ? '  data: formData,\n' : (!['GET', 'HEAD'].includes(method) && state.requestBodyModel !== null && state.requestBodyModel !== undefined ? '  data: payload,\n' : '')}});`,
        };
    }

    function filteredOperations() {
        const operations = Array.isArray(state.docs?.operations) ? state.docs.operations : [];
        const needle = state.search.trim().toLowerCase();

        return operations.filter((operation) => {
            const haystack = [
                operation.id,
                operation.path,
                operation.summary,
                operation.description || '',
                ...(operation.tags || []),
            ].join(' ').toLowerCase();

            const matchesSearch = needle === '' || haystack.includes(needle);
            const matchesMethod = state.selectedMethod === 'ALL' || String(operation.method).toUpperCase() === state.selectedMethod;

            return matchesSearch && matchesMethod;
        });
    }

    function groupedOperations() {
        const groups = new Map();

        filteredOperations().forEach((operation) => {
            const key = groupKeyForOperation(operation);
            if (!groups.has(key)) {
                groups.set(key, []);
            }
            groups.get(key).push(operation);
        });

        return Array.from(groups.entries())
            .sort((left, right) => left[0].localeCompare(right[0]))
            .map(([key, operations]) => ({
                key,
                label: key,
                operations,
            }));
    }

    function methods() {
        const values = new Set(['ALL']);
        (state.docs?.operations || []).forEach((operation) => values.add(String(operation.method).toUpperCase()));

        return Array.from(values);
    }

    function requestStateKey(operation) {
        return [operation?.id || '', state.mode, state.scenarioKey || 'default'].join('::');
    }

    function hydrateRequestState(operation) {
        const example = selectedExample(operation);
        const requestBodySchema = resolveRequestContent(operation)?.schema;
        const responseSchema = resolveSchema(resolveResponseContent(operation)?.schema, componentsSchemas());

        state.requestPathParams = deepClone(example?.request?.pathParams || {});
        state.requestQueryParams = deepClone(example?.request?.queryParams || {});
        state.requestBodyModel = Object.prototype.hasOwnProperty.call(example?.request || {}, 'body')
            ? deepClone(example?.request?.body)
            : buildSchemaInitialValue(requestBodySchema);
        state.requestBodyText = prettyJson(state.requestBodyModel);
        state.requestBodyError = '';
        state.responseBodyText = prettyJson(example?.response?.body ?? buildSchemaInitialValue(responseSchema));
        state.tryStatus = 'Idle';
        state.tryResponseText = 'null';
        state.hydratedKey = requestStateKey(operation);
    }

    function syncDerivedState() {
        const operation = selectedOperation();

        if (!operation) {
            state.selectedOperationId = null;
            state.requestPathParams = {};
            state.requestQueryParams = {};
            state.requestBodyModel = null;
            state.requestBodyText = 'null';
            state.responseBodyText = 'null';
            state.requestBodyError = '';
            state.hydratedKey = null;
            return null;
        }

        if (state.selectedOperationId !== operation.id) {
            state.selectedOperationId = operation.id;
        }

        const scenarios = availableScenarios(operation);
        if (scenarios.length > 0) {
            if (!scenarios.some((scenario) => scenario.key === state.scenarioKey)) {
                state.scenarioKey = scenarios[0].key;
            }
        } else {
            state.scenarioKey = null;
        }

        if (state.hydratedKey !== requestStateKey(operation)) {
            hydrateRequestState(operation);
        }

        state.openGroups[groupKeyForOperation(operation)] = true;

        return operation;
    }

    function resolveResponseContent(operation) {
        const responses = operation?.responses || {};
        const responseKey = Object.keys(responses).find((key) => key.startsWith('2')) || Object.keys(responses)[0];
        if (!responseKey || !isObjectLike(responses[responseKey])) {
            return null;
        }

        const content = responses[responseKey].content;
        if (!isObjectLike(content)) {
            return null;
        }

        const priorities = ['application/json', 'text/html', 'multipart/form-data'];
        const keys = Object.keys(content);
        const contentType = priorities.find((candidate) => candidate in content) ?? keys[0];
        if (!contentType || !isObjectLike(content[contentType])) {
            return null;
        }

        return {
            contentType,
            schema: resolveSchema(content[contentType].schema, componentsSchemas()),
        };
    }

    function classifyResponseTone(code) {
        if (String(code).startsWith('2')) {
            return 'success';
        }
        if (String(code).startsWith('3')) {
            return 'redirect';
        }
        if (String(code).startsWith('4')) {
            return 'client_error';
        }
        if (String(code).startsWith('5')) {
            return 'server_error';
        }
        return 'unknown';
    }

    function fallbackResponseDescription(code) {
        if (String(code).startsWith('2')) {
            return 'Successful response';
        }
        if (String(code).startsWith('3')) {
            return 'Redirect response';
        }
        if (String(code).startsWith('4')) {
            return 'Client error';
        }
        if (String(code).startsWith('5')) {
            return 'Server error';
        }
        return 'Response';
    }

    function compareResponseEntries(left, right) {
        const rank = (tone) => {
            switch (tone) {
                case 'success': return 0;
                case 'redirect': return 1;
                case 'client_error': return 2;
                case 'server_error': return 3;
                default: return 4;
            }
        };

        const toneDelta = rank(left.tone) - rank(right.tone);
        if (toneDelta !== 0) {
            return toneDelta;
        }

        const leftNumber = Number.parseInt(left.code, 10);
        const rightNumber = Number.parseInt(right.code, 10);
        if (Number.isFinite(leftNumber) && Number.isFinite(rightNumber)) {
            return leftNumber - rightNumber;
        }

        return String(left.code).localeCompare(String(right.code));
    }

    function summarizeResponses(operation, preferredStatus) {
        const responses = operation?.responses || {};
        const entries = Object.entries(responses)
            .filter(([, value]) => isObjectLike(value))
            .map(([code, value]) => ({
                code,
                description: String(value.description || '').trim() || fallbackResponseDescription(code),
                tone: classifyResponseTone(code),
            }))
            .sort(compareResponseEntries);

        const preferredCode = preferredStatus != null
            ? String(preferredStatus)
            : (entries.find((entry) => entry.code.startsWith('2'))?.code || entries[0]?.code || null);

        const primary = entries.find((entry) => entry.code === preferredCode) || null;
        const remaining = entries.filter((entry) => entry.code !== primary?.code);

        return {
            primary,
            secondarySuccess: remaining.filter((entry) => entry.tone === 'success' || entry.tone === 'redirect'),
            clientErrors: remaining.filter((entry) => entry.tone === 'client_error'),
            serverErrors: remaining.filter((entry) => entry.tone === 'server_error'),
            other: remaining.filter((entry) => entry.tone === 'unknown'),
        };
    }

    function responseToneStyle(tone) {
        switch (tone) {
            case 'success':
                return 'background: var(--color-green-dim); border: 1px solid rgba(61,214,140,0.25); color: var(--color-green);';
            case 'redirect':
                return 'background: var(--color-blue-dim); border: 1px solid rgba(91,156,245,0.25); color: var(--color-blue);';
            case 'client_error':
                return 'background: var(--color-amber-dim); border: 1px solid rgba(240,166,74,0.25); color: var(--color-amber);';
            case 'server_error':
                return 'background: var(--color-red-dim); border: 1px solid rgba(248,113,113,0.25); color: var(--color-red);';
            default:
                return 'background: var(--color-surface-hover); border: 1px solid var(--color-border-bright); color: var(--color-text-secondary);';
        }
    }

    function serializePath(path) {
        return encodeURIComponent(JSON.stringify(path));
    }

    function deserializePath(value) {
        try {
            return JSON.parse(decodeURIComponent(value));
        } catch {
            return [];
        }
    }

    function getTargetValue(target) {
        if (target === 'body') {
            return state.requestBodyModel;
        }
        if (target === 'path') {
            return state.requestPathParams;
        }
        if (target === 'query') {
            return state.requestQueryParams;
        }
        return null;
    }

    function setTargetValue(target, value) {
        if (target === 'body') {
            state.requestBodyModel = value;
            state.requestBodyText = prettyJson(value);
            state.requestBodyError = '';
            return;
        }
        if (target === 'path') {
            state.requestPathParams = value || {};
            return;
        }
        if (target === 'query') {
            state.requestQueryParams = value || {};
        }
    }

    function getNestedValue(rootValue, path) {
        let current = rootValue;

        for (const segment of path) {
            if (current === null || current === undefined) {
                return undefined;
            }

            current = current[segment];
        }

        return current;
    }

    function setNestedValue(rootValue, path, value) {
        if (path.length === 0) {
            return value;
        }

        const root = Array.isArray(rootValue) ? [...rootValue] : { ...(rootValue || {}) };
        let current = root;

        for (let index = 0; index < path.length - 1; index += 1) {
            const segment = path[index];
            const nextSegment = path[index + 1];
            const existing = current[segment];

            if (Array.isArray(existing)) {
                current[segment] = [...existing];
            } else if (isObjectLike(existing)) {
                current[segment] = { ...existing };
            } else {
                current[segment] = typeof nextSegment === 'number' ? [] : {};
            }

            current = current[segment];
        }

        current[path[path.length - 1]] = value;

        return root;
    }

    function removeArrayItem(rootValue, path, index) {
        const root = deepClone(rootValue);
        const arrayValue = getNestedValue(root, path);

        if (!Array.isArray(arrayValue)) {
            return root;
        }

        arrayValue.splice(index, 1);
        return root;
    }

    function topLevelSchemaForTarget(target, operation) {
        if (target === 'body') {
            return resolveRequestContent(operation)?.schema || null;
        }
        if (target === 'path') {
            return buildParameterObjectSchema(operation, 'path');
        }
        if (target === 'query') {
            return buildParameterObjectSchema(operation, 'query');
        }
        return null;
    }

    function schemaForTargetPath(target, path, operation) {
        let schema = resolveSchema(topLevelSchemaForTarget(target, operation), componentsSchemas());

        for (const segment of path) {
            if (!schema) {
                return null;
            }

            if (typeof segment === 'number') {
                schema = resolveSchema(schema.items, componentsSchemas());
                continue;
            }

            if ((schema.type === 'object' || isObjectLike(schema.properties)) && isObjectLike(schema.properties)) {
                schema = resolveSchema(schema.properties[segment], componentsSchemas());
                continue;
            }

            if (schema.type === 'array') {
                schema = resolveSchema(schema.items, componentsSchemas());
                if (schema && isObjectLike(schema.properties)) {
                    schema = resolveSchema(schema.properties[segment], componentsSchemas());
                }
            }
        }

        return schema;
    }

    function coerceFieldValue(schema, rawValue) {
        const resolved = resolveSchema(schema, componentsSchemas());
        if (!resolved) {
            return rawValue;
        }

        if (resolved.type === 'boolean') {
            return rawValue === 'true';
        }

        if (resolved.type === 'integer') {
            return rawValue === '' ? null : parseInt(rawValue, 10);
        }

        if (resolved.type === 'number') {
            return rawValue === '' ? null : parseFloat(rawValue);
        }

        return rawValue;
    }

    function typeLabel(schema) {
        const resolved = resolveSchema(schema, componentsSchemas());
        if (!resolved) {
            return 'unknown';
        }

        if (Array.isArray(resolved.enum) && resolved.enum.length > 0) {
            return 'enum';
        }

        if (resolved.format) {
            return `${resolved.type || 'string'} · ${resolved.format}`;
        }

        return resolved.type || 'string';
    }

    function renderFieldHeader(label, schema, required) {
        return `
            <div class="ox-field-head">
                <div>
                    <p class="ox-field-label">${escapeHtml(label)}</p>
                    <p class="ox-field-meta">${escapeHtml(typeLabel(schema))}</p>
                </div>
                ${required ? '<span class="ox-badge-soft">required</span>' : '<span class="ox-badge-soft" data-tone="muted">optional</span>'}
            </div>
        `;
    }

    function renderPrimitiveControl(target, schema, value, path, required, label) {
        const resolved = resolveSchema(schema, componentsSchemas()) || { type: 'string' };
        const pathKey = serializePath(path);
        const common = `data-control="schema-field" data-target-model="${escapeHtml(target)}" data-path="${escapeHtml(pathKey)}"`;

        if (resolved.format === 'binary') {
            return `
                <div class="ox-field">
                    ${renderFieldHeader(label, schema, required)}
                    <input class="ox-input" type="file" ${common} data-value-type="binary">
                    ${isFileMarker(value) ? `<p class="ox-helper">Selected file: ${escapeHtml(value.__oxcribeFile.name)}</p>` : '<p class="ox-helper">Binary uploads are converted into multipart form data for Try It and live snippets.</p>'}
                </div>
            `;
        }

        if (Array.isArray(resolved.enum) && resolved.enum.length > 0) {
            return `
                <div class="ox-field">
                    ${renderFieldHeader(label, schema, required)}
                    <select class="ox-select" ${common} data-value-type="enum">
                        ${resolved.enum.map((entry) => `<option value="${escapeHtml(entry)}"${String(entry) === String(value ?? resolved.enum[0]) ? ' selected' : ''}>${escapeHtml(entry)}</option>`).join('')}
                    </select>
                </div>
            `;
        }

        if (resolved.type === 'boolean') {
            return `
                <div class="ox-field">
                    ${renderFieldHeader(label, schema, required)}
                    <select class="ox-select" ${common} data-value-type="boolean">
                        <option value="true"${value === true ? ' selected' : ''}>true</option>
                        <option value="false"${value === false ? ' selected' : ''}>false</option>
                    </select>
                </div>
            `;
        }

        const inputType = resolved.type === 'integer' || resolved.type === 'number'
            ? 'number'
            : (resolved.format === 'email' ? 'email' : (resolved.format === 'date' ? 'date' : (resolved.format === 'uri' ? 'url' : 'text')));
        const step = resolved.type === 'integer' ? '1' : (resolved.type === 'number' ? 'any' : null);

        return `
            <div class="ox-field">
                ${renderFieldHeader(label, schema, required)}
                <input
                    class="ox-input"
                    type="${inputType}"
                    value="${escapeHtml(value ?? '')}"
                    ${common}
                    data-value-type="${escapeHtml(resolved.type || 'string')}"
                    ${step ? `step="${step}"` : ''}
                >
            </div>
        `;
    }

    function renderSchemaEditor(target, schema, value, path = [], label = 'Field', required = true, rootLevel = false) {
        const resolved = resolveSchema(schema, componentsSchemas());
        if (!resolved) {
            return '';
        }

        if ((resolved.type === 'object' || isObjectLike(resolved.properties)) && isObjectLike(resolved.properties)) {
            const requiredKeys = Array.isArray(resolved.required) ? resolved.required : [];
            const currentValue = isObjectLike(value) ? value : {};

            return `
                <section class="ox-fieldset${rootLevel ? ' ox-fieldset-root' : ''}">
                    ${rootLevel ? '' : renderFieldHeader(label, schema, required)}
                    <div class="ox-form-grid">
                        ${Object.entries(resolved.properties).map(([key, childSchema]) => (
                            renderSchemaEditor(
                                target,
                                childSchema,
                                currentValue[key],
                                [...path, key],
                                titleize(key),
                                requiredKeys.includes(key),
                                false,
                            )
                        )).join('')}
                    </div>
                </section>
            `;
        }

        if (resolved.type === 'array') {
            const items = Array.isArray(value) ? value : [];
            const pathKey = serializePath(path);

            return `
                <section class="ox-fieldset">
                    ${renderFieldHeader(label, schema, required)}
                    <div class="ox-array-stack">
                        ${items.length === 0 ? '<p class="ox-helper">No items yet.</p>' : ''}
                        ${items.map((item, index) => `
                            <div class="ox-array-card">
                                <div class="ox-array-toolbar">
                                    <span>Item ${index + 1}</span>
                                    <button class="ox-button" type="button" data-action="remove-array-item" data-target-model="${escapeHtml(target)}" data-path="${escapeHtml(pathKey)}" data-index="${index}">Remove</button>
                                </div>
                                ${renderSchemaEditor(target, resolved.items, item, [...path, index], `Item ${index + 1}`, true, false)}
                            </div>
                        `).join('')}
                    </div>
                    <button class="ox-button" type="button" data-action="add-array-item" data-target-model="${escapeHtml(target)}" data-path="${escapeHtml(pathKey)}">Add item</button>
                </section>
            `;
        }

        return renderPrimitiveControl(target, resolved, value, path, required, label);
    }

    function currentHeaders(operation) {
        return { ...(selectedExample(operation)?.request?.headers || {}) };
    }

    function renderSidebar(groups, operation) {
        const operationsCount = Array.isArray(state.docs?.operations) ? state.docs.operations.length : 0;
        const visibleCount = filteredOperations().length;

        return `
            <aside class="ox-sidebar">
                <div class="ox-panel ox-brand">
                    <p class="ox-kicker">Oxcribe Local Viewer</p>
                    <h1>${escapeHtml(state.docs?.info?.title || root.dataset.title || 'Oxcribe Docs')}</h1>
                    <p class="ox-copy">OpenAPI ${escapeHtml(state.docs?.info?.openapi || '3.1.0')} · ${escapeHtml(operationsCount)} operations</p>
                    <p class="ox-copy"><a class="ox-meta-link" href="${escapeHtml(root.dataset.openapiUrl || '#')}">OpenAPI JSON</a></p>
                </div>

                <div class="ox-toolbar">
                    <input id="${SEARCH_INPUT_ID}" class="ox-input" type="search" value="${escapeHtml(state.search)}" placeholder="Search endpoints · ⌘K" data-input="search">
                    <div class="ox-filter-row">
                        <select class="ox-select" data-input="method">
                            ${methods().map((method) => `<option value="${escapeHtml(method)}"${method === state.selectedMethod ? ' selected' : ''}>${escapeHtml(method)}</option>`).join('')}
                        </select>
                        <div class="ox-copy" style="display:flex;align-items:center;justify-content:flex-end;">${escapeHtml(visibleCount === operationsCount ? `${operationsCount} visible` : `${visibleCount}/${operationsCount}`)}</div>
                    </div>
                </div>

                <div class="ox-groups">
                    ${groups.length === 0 ? `<div class="ox-panel ox-empty">No operations match the current filters.</div>` : groups.map((group) => {
                        const isOpen = state.openGroups[group.key] !== false;
                        return `
                            <section class="ox-group">
                                <button class="ox-group-toggle" type="button" data-action="toggle-group" data-group="${escapeHtml(group.key)}">
                                    <span class="ox-group-label">
                                        <strong>${escapeHtml(group.label)}</strong>
                                        <span>${escapeHtml(group.operations.length)} operations</span>
                                    </span>
                                    <span>${isOpen ? '−' : '+'}</span>
                                </button>
                                ${isOpen ? `
                                    <div class="ox-group-list">
                                        ${group.operations.map((entry) => `
                                            <button class="ox-operation" type="button" data-action="select-operation" data-operation="${escapeHtml(entry.id)}" data-active="${String(entry.id === operation?.id)}">
                                                <div class="ox-row">
                                                    <span class="ox-method" data-method="${escapeHtml(entry.method)}">${escapeHtml(entry.method)}</span>
                                                    <span class="ox-operation-title">${escapeHtml(operationTitle(entry))}</span>
                                                </div>
                                                <div class="ox-operation-path">${escapeHtml(entry.path)}</div>
                                            </button>
                                        `).join('')}
                                    </div>
                                ` : ''}
                            </section>
                        `;
                    }).join('')}
                </div>
            </aside>
        `;
    }

    function renderMain(operation) {
        if (!operation) {
            return `<main class="ox-main"><div class="ox-panel ox-empty">No operations are available in this document.</div></main>`;
        }

        const requestContent = resolveRequestContent(operation);
        const pathSchema = buildParameterObjectSchema(operation, 'path');
        const querySchema = buildParameterObjectSchema(operation, 'query');
        const scenarios = availableScenarios(operation);
        const snippets = buildLiveSnippets(operation, currentHeaders(operation));
        const selectedScenarioData = scenarios.find((scenario) => scenario.key === state.scenarioKey) || null;
        const activeExample = selectedExample(operation);
        const responseSummary = summarizeResponses(operation, activeExample?.response?.status);

        return `
            <main class="ox-main">
                <div class="ox-main-grid">
                    <div class="ox-stack">
                        <section class="ox-panel ox-hero">
                            <div class="ox-row">
                                <span class="ox-method" data-method="${escapeHtml(operation.method)}">${escapeHtml(operation.method)}</span>
                                <span class="ox-kicker">${escapeHtml(operation.id)}</span>
                            </div>
                            <h2>${escapeHtml(operationTitle(operation))}</h2>
                            <p class="ox-summary">${escapeHtml(operation.path)}</p>
                            ${operation.description ? `<p class="ox-description">${escapeHtml(operation.description)}</p>` : ''}
                            <div class="ox-actions" style="margin-top:1rem;">
                                ${exampleModes.map((item) => `
                                    <button class="ox-pill" data-action="select-mode" data-mode="${escapeHtml(item.key)}" data-active="${String(item.key === state.mode)}">${escapeHtml(item.label)}</button>
                                `).join('')}
                            </div>
                            ${scenarios.length > 0 ? `
                                <div class="ox-actions" style="margin-top:0.75rem;">
                                    ${scenarios.map((scenario) => `
                                        <button class="ox-pill" data-tone="secondary" data-action="select-scenario" data-scenario="${escapeHtml(scenario.key)}" data-active="${String(scenario.key === state.scenarioKey)}">${escapeHtml(scenario.label)}</button>
                                    `).join('')}
                                </div>
                            ` : ''}
                            ${selectedScenarioData?.description ? `<p class="ox-helper" style="margin-top:0.75rem;">${escapeHtml(selectedScenarioData.description)}</p>` : ''}
                            <div class="ox-builder-grid" style="margin-top:1rem;">
                                <div class="ox-panel" style="padding:0.9rem 1rem; background: rgba(255,255,255,0.02);">
                                    <div class="ox-kicker">Response hierarchy</div>
                                    <div class="ox-actions" style="margin-top:0.65rem;">
                                        ${responseSummary.primary ? `
                                            <span class="ox-pill" style="${responseToneStyle(responseSummary.primary.tone)}">${escapeHtml(responseSummary.primary.code)}</span>
                                            <span class="ox-helper">Primary response</span>
                                        ` : ''}
                                        ${responseSummary.secondarySuccess.length > 0 ? `<span class="ox-pill">${responseSummary.secondarySuccess.length} other success</span>` : ''}
                                        ${responseSummary.clientErrors.length > 0 ? `<span class="ox-pill" style="${responseToneStyle('client_error')}">${responseSummary.clientErrors.length} client errors</span>` : ''}
                                        ${responseSummary.serverErrors.length > 0 ? `<span class="ox-pill" style="${responseToneStyle('server_error')}">${responseSummary.serverErrors.length} possible failures</span>` : ''}
                                    </div>
                                </div>
                                <div class="ox-panel" style="padding:0.9rem 1rem; background: rgba(255,255,255,0.02);">
                                    <div class="ox-kicker">Primary response</div>
                                    ${responseSummary.primary ? `
                                        <div class="ox-actions" style="margin-top:0.65rem;">
                                            <span class="ox-pill" style="${responseToneStyle(responseSummary.primary.tone)}">${escapeHtml(responseSummary.primary.code)}</span>
                                            <span class="ox-helper">${escapeHtml(responseSummary.primary.description)}</span>
                                        </div>
                                    ` : '<p class="ox-helper" style="margin-top:0.65rem;">No response metadata available.</p>'}
                                </div>
                            </div>
                        </section>

                        ${(responseSummary.secondarySuccess.length > 0 || responseSummary.clientErrors.length > 0 || responseSummary.serverErrors.length > 0 || responseSummary.other.length > 0) ? `
                            <section class="ox-panel ox-section">
                                <div class="ox-builder-grid">
                                    ${responseSummary.secondarySuccess.length > 0 ? `
                                        <div class="ox-panel" style="padding:0.9rem 1rem; background: rgba(255,255,255,0.02);">
                                            <div class="ox-kicker">Other success responses</div>
                                            <div class="ox-stack" style="margin-top:0.75rem;">
                                                ${responseSummary.secondarySuccess.map((entry) => `
                                                    <div class="ox-actions">
                                                        <span class="ox-pill" style="${responseToneStyle(entry.tone)}">${escapeHtml(entry.code)}</span>
                                                        <span class="ox-helper">${escapeHtml(entry.description)}</span>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${responseSummary.clientErrors.length > 0 ? `
                                        <div class="ox-panel" style="padding:0.9rem 1rem; background: rgba(240,166,74,0.08); border-color: rgba(240,166,74,0.22);">
                                            <div class="ox-kicker" style="color: var(--color-amber);">Client errors</div>
                                            <div class="ox-stack" style="margin-top:0.75rem;">
                                                ${responseSummary.clientErrors.map((entry) => `
                                                    <div class="ox-actions">
                                                        <span class="ox-pill" style="${responseToneStyle(entry.tone)}">${escapeHtml(entry.code)}</span>
                                                        <span class="ox-helper">${escapeHtml(entry.description)}</span>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${(responseSummary.serverErrors.length > 0 || responseSummary.other.length > 0) ? `
                                        <div class="ox-panel" style="padding:0.9rem 1rem; background: rgba(248,113,113,0.08); border-color: rgba(248,113,113,0.22);">
                                            <div class="ox-kicker" style="color: var(--color-red);">Possible failures</div>
                                            <div class="ox-stack" style="margin-top:0.75rem;">
                                                ${responseSummary.serverErrors.map((entry) => `
                                                    <div class="ox-actions">
                                                        <span class="ox-pill" style="${responseToneStyle(entry.tone)}">${escapeHtml(entry.code)}</span>
                                                        <span class="ox-helper">${escapeHtml(entry.description)}</span>
                                                    </div>
                                                `).join('')}
                                                ${responseSummary.other.map((entry) => `
                                                    <div class="ox-actions">
                                                        <span class="ox-pill" style="${responseToneStyle(entry.tone)}">${escapeHtml(entry.code)}</span>
                                                        <span class="ox-helper">${escapeHtml(entry.description)}</span>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </section>
                        ` : ''}

                        ${pathSchema ? `
                            <section class="ox-panel ox-section">
                                <div class="ox-section-head">
                                    <div>
                                        <h3>Path parameters</h3>
                                        <p>Resolved directly into the request URL.</p>
                                    </div>
                                </div>
                                ${renderSchemaEditor('path', pathSchema, state.requestPathParams, [], 'Path parameters', true, true)}
                            </section>
                        ` : ''}

                        ${querySchema ? `
                            <section class="ox-panel ox-section">
                                <div class="ox-section-head">
                                    <div>
                                        <h3>Query parameters</h3>
                                        <p>Live request state flows into snippets and Try It.</p>
                                    </div>
                                </div>
                                ${renderSchemaEditor('query', querySchema, state.requestQueryParams, [], 'Query parameters', true, true)}
                            </section>
                        ` : ''}

                        <section class="ox-panel ox-section">
                            <div class="ox-section-head">
                                <div>
                                    <h3>Request body</h3>
                                    <p>${escapeHtml(requestContent?.contentType || 'application/json')} · edit with controls or raw JSON.</p>
                                </div>
                                <button class="ox-button" type="button" data-action="copy-request">Copy</button>
                            </div>
                            <div class="ox-builder-grid">
                                <div class="ox-stack">
                                    ${requestContent?.schema
                                        ? renderSchemaEditor('body', requestContent.schema, state.requestBodyModel, [], 'Request body', true, true)
                                        : '<div class="ox-helper">No structured request schema is available for this operation.</div>'}
                                </div>
                                <div class="ox-stack">
                                    <textarea class="ox-textarea" data-input="request-body">${escapeHtml(state.requestBodyText)}</textarea>
                                    ${state.requestBodyError ? `<p class="ox-error">${escapeHtml(state.requestBodyError)}</p>` : '<p class="ox-helper">Raw JSON stays in sync with the form builder.</p>'}
                                </div>
                            </div>
                        </section>

                        <section class="ox-panel ox-section">
                            <div class="ox-section-head">
                                <div>
                                    <h3>Response example</h3>
                                    <p>Projected response for the current mode and scenario.</p>
                                </div>
                                <div class="ox-actions">
                                    ${responseSummary.primary ? `<span class="ox-pill" style="${responseToneStyle(responseSummary.primary.tone)}">${escapeHtml(responseSummary.primary.code)}</span>` : ''}
                                    <button class="ox-button" type="button" data-action="copy-response">Copy</button>
                                </div>
                            </div>
                            <pre class="ox-code">${escapeHtml(state.responseBodyText)}</pre>
                        </section>

                        <section class="ox-panel ox-section">
                            <div class="ox-section-head">
                                <div>
                                    <h3>Live snippets</h3>
                                    <p>Generated from the current request state, not a static sample.</p>
                                </div>
                                <button class="ox-button" type="button" data-action="copy-snippet">Copy</button>
                            </div>
                            <div class="ox-tab-row" style="margin-bottom:0.85rem;">
                                ${['curl', 'fetch', 'axios'].map((kind) => `
                                    <button class="ox-pill" data-action="select-snippet" data-snippet="${escapeHtml(kind)}" data-active="${String(kind === state.snippetKind)}">${escapeHtml(kind)}</button>
                                `).join('')}
                            </div>
                            <pre class="ox-code">${escapeHtml(snippets[state.snippetKind] || '')}</pre>
                        </section>
                    </div>

                    <div class="ox-stack">
                        <section class="ox-panel ox-section">
                            <div class="ox-section-head">
                                <div>
                                    <h3>Try It</h3>
                                    <p>Send the current request state directly from the local viewer.</p>
                                </div>
                                <span class="ox-kicker">${escapeHtml(state.tryStatus)}</span>
                            </div>

                            <div class="ox-try-grid">
                                <label class="ox-label">
                                    Base URL
                                    <input class="ox-input" data-input="base-url" value="${escapeHtml(state.baseUrl)}" placeholder="https://api.example.test">
                                </label>
                                <label class="ox-label">
                                    Bearer token
                                    <input class="ox-input" data-input="bearer-token" value="${escapeHtml(state.bearerToken)}" placeholder="Optional">
                                </label>
                                <label class="ox-label">
                                    Resolved request URL
                                    <input class="ox-input" value="${escapeHtml(buildRequestUrl(state.baseUrl, operation, state.requestPathParams, state.requestQueryParams))}" readonly>
                                </label>
                                <button class="ox-button" type="button" data-action="send-try">Send request</button>
                            </div>
                        </section>

                        <section class="ox-panel ox-section">
                            <div class="ox-section-head">
                                <div>
                                    <h3>Response</h3>
                                    <p>Raw response from the request sent above.</p>
                                </div>
                            </div>
                            <pre class="ox-code">${escapeHtml(state.tryResponseText)}</pre>
                        </section>
                    </div>
                </div>
            </main>
        `;
    }

    function captureFocusDescriptor(element) {
        if (!(element instanceof HTMLElement)) {
            return null;
        }

        const descriptor = {
            input: element.getAttribute('data-input'),
            control: element.getAttribute('data-control'),
            targetModel: element.getAttribute('data-target-model'),
            path: element.getAttribute('data-path'),
            selectionStart: typeof element.selectionStart === 'number' ? element.selectionStart : null,
            selectionEnd: typeof element.selectionEnd === 'number' ? element.selectionEnd : null,
        };

        if (!descriptor.input && !descriptor.control) {
            return null;
        }

        return descriptor;
    }

    function restoreFocus(descriptor) {
        if (!descriptor) {
            return;
        }

        let selector = null;
        if (descriptor.control) {
            selector = `[data-control="${CSS.escape(descriptor.control)}"][data-target-model="${CSS.escape(descriptor.targetModel || '')}"][data-path="${CSS.escape(descriptor.path || '')}"]`;
        } else if (descriptor.input) {
            selector = `[data-input="${CSS.escape(descriptor.input)}"]`;
        }

        if (!selector) {
            return;
        }

        const element = root.querySelector(selector);
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.focus();
        if (typeof element.setSelectionRange === 'function' && descriptor.selectionStart !== null) {
            element.setSelectionRange(descriptor.selectionStart, descriptor.selectionEnd ?? descriptor.selectionStart);
        }
    }

    function render(descriptor = null) {
        if (state.loading) {
            root.innerHTML = `<div class="ox-root"><main class="ox-main"><div class="ox-panel ox-empty">Loading docs payload…</div></main></div>`;
            return;
        }

        if (state.error) {
            root.innerHTML = `<div class="ox-root"><main class="ox-main"><div class="ox-panel ox-empty">${escapeHtml(state.error)}</div></main></div>`;
            return;
        }

        const operation = syncDerivedState();
        const groups = groupedOperations();

        root.innerHTML = `
            <div class="ox-root">
                ${renderSidebar(groups, operation)}
                ${renderMain(operation)}
            </div>
        `;

        restoreFocus(descriptor);
    }

    async function sendTryRequest() {
        const operation = selectedOperation();
        if (!operation) {
            return;
        }

        try {
            state.tryStatus = 'Sending';
            render();

            const headers = new Headers(currentHeaders(operation));
            headers.set('Accept', headers.get('Accept') || 'application/json');
            if (state.bearerToken) {
                headers.set('Authorization', `Bearer ${state.bearerToken}`);
            }

            const method = String(operation.method || 'GET').toUpperCase();
            const hasFiles = containsFileMarkers(state.requestBodyModel);
            const url = buildRequestUrl(state.baseUrl, operation, state.requestPathParams, state.requestQueryParams);
            const options = {
                method,
                headers,
                credentials: 'include',
            };

            if (!['GET', 'HEAD'].includes(method)) {
                if (hasFiles) {
                    headers.delete('Content-Type');
                    headers.delete('content-type');

                    const formData = new FormData();
                    multipartEntries(state.requestBodyModel).forEach(({ key, value }) => {
                        if (isFileMarker(value)) {
                            const byteCharacters = atob(value.__oxcribeFile.contentBase64);
                            const byteNumbers = Array.from(byteCharacters).map((char) => char.charCodeAt(0));
                            const blob = new Blob([new Uint8Array(byteNumbers)], { type: value.__oxcribeFile.mimeType || 'application/octet-stream' });
                            formData.append(key, blob, value.__oxcribeFile.name);
                        } else {
                            formData.append(key, String(value));
                        }
                    });

                    options.body = formData;
                } else if (state.requestBodyModel !== null && state.requestBodyModel !== undefined) {
                    headers.set('Content-Type', headers.get('Content-Type') || 'application/json');
                    options.body = JSON.stringify(state.requestBodyModel);
                }
            }

            const response = await fetch(url, options);
            const contentType = response.headers.get('content-type') || '';
            const payload = contentType.includes('application/json')
                ? await response.json()
                : await response.text();

            state.tryStatus = `Status ${response.status}`;
            state.tryResponseText = prettyJson({
                status: response.status,
                ok: response.ok,
                headers: Object.fromEntries(response.headers.entries()),
                body: payload,
            });
        } catch (error) {
            state.tryStatus = 'Failed';
            state.tryResponseText = prettyJson({
                message: error instanceof Error ? error.message : String(error),
            });
        }

        render();
    }

    root.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        const action = trigger.getAttribute('data-action');

        if (action === 'toggle-group') {
            const key = trigger.getAttribute('data-group');
            state.openGroups[key] = state.openGroups[key] === false;
            render();
            return;
        }

        if (action === 'select-operation') {
            state.selectedOperationId = trigger.getAttribute('data-operation');
            state.scenarioKey = null;
            state.hydratedKey = null;
            render();
            return;
        }

        if (action === 'select-mode') {
            state.mode = trigger.getAttribute('data-mode') || 'happy_path';
            state.scenarioKey = null;
            state.hydratedKey = null;
            render();
            return;
        }

        if (action === 'select-scenario') {
            state.scenarioKey = trigger.getAttribute('data-scenario');
            state.hydratedKey = null;
            render();
            return;
        }

        if (action === 'select-snippet') {
            state.snippetKind = trigger.getAttribute('data-snippet') || 'curl';
            render();
            return;
        }

        if (action === 'add-array-item') {
            const operation = selectedOperation();
            const targetModel = trigger.getAttribute('data-target-model');
            const path = deserializePath(trigger.getAttribute('data-path') || '');
            const arraySchema = schemaForTargetPath(targetModel, path, operation);
            const currentValue = getTargetValue(targetModel);
            const nextValue = deepClone(currentValue) || (targetModel === 'body' ? {} : {});
            const currentArray = getNestedValue(nextValue, path);
            const itemValue = buildSchemaInitialValue(resolveSchema(arraySchema?.items, componentsSchemas()));
            const replacement = Array.isArray(currentArray) ? [...currentArray, itemValue] : [itemValue];
            setTargetValue(targetModel, setNestedValue(nextValue, path, replacement));
            render();
            return;
        }

        if (action === 'remove-array-item') {
            const targetModel = trigger.getAttribute('data-target-model');
            const path = deserializePath(trigger.getAttribute('data-path') || '');
            const index = parseInt(trigger.getAttribute('data-index') || '0', 10);
            const nextValue = removeArrayItem(getTargetValue(targetModel), path, index);
            setTargetValue(targetModel, nextValue);
            render();
            return;
        }

        if (action === 'copy-request') {
            await copyText(state.requestBodyText);
            return;
        }

        if (action === 'copy-response') {
            await copyText(state.responseBodyText);
            return;
        }

        if (action === 'copy-snippet') {
            const operation = selectedOperation();
            const snippets = buildLiveSnippets(operation, currentHeaders(operation));
            await copyText(snippets[state.snippetKind] || '');
            return;
        }

        if (action === 'send-try') {
            await sendTryRequest();
        }
    });

    function handleStructuredInput(target, shouldRender = true) {
        const input = target.getAttribute('data-input');
        const descriptor = captureFocusDescriptor(target);

        if (input === 'search') {
            state.search = target.value;
            render(descriptor);
            return;
        }

        if (input === 'method') {
            state.selectedMethod = target.value || 'ALL';
            render(descriptor);
            return;
        }

        if (input === 'base-url') {
            state.baseUrl = target.value;
            render(descriptor);
            return;
        }

        if (input === 'bearer-token') {
            state.bearerToken = target.value;
            render(descriptor);
            return;
        }

        if (input === 'request-body') {
            state.requestBodyText = target.value;

            try {
                state.requestBodyModel = JSON.parse(target.value === '' ? 'null' : target.value);
                state.requestBodyError = '';
                if (shouldRender) {
                    render(descriptor);
                }
            } catch (error) {
                state.requestBodyError = error instanceof Error ? error.message : 'Invalid JSON';
                if (shouldRender) {
                    render(descriptor);
                }
            }
            return;
        }

        const control = target.getAttribute('data-control');
        if (control !== 'schema-field') {
            return;
        }

        const targetModel = target.getAttribute('data-target-model');
        const path = deserializePath(target.getAttribute('data-path') || '');
        const operation = selectedOperation();
        const schema = schemaForTargetPath(targetModel, path, operation);

        if (target.type === 'file') {
            const [file] = Array.from(target.files || []);
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = () => {
                const result = typeof reader.result === 'string' ? reader.result : '';
                const contentBase64 = result.includes(',') ? result.split(',')[1] : result;
                const marker = {
                    __oxcribeFile: {
                        name: file.name,
                        mimeType: file.type || 'application/octet-stream',
                        contentBase64,
                    },
                };
                setTargetValue(targetModel, setNestedValue(getTargetValue(targetModel), path, marker));
                render();
            };
            reader.readAsDataURL(file);
            return;
        }

        const nextValue = coerceFieldValue(schema, target.value);
        setTargetValue(targetModel, setNestedValue(getTargetValue(targetModel), path, nextValue));
        if (shouldRender) {
            render(descriptor);
        }
    }

    root.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) {
            return;
        }

        handleStructuredInput(target, true);
    });

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) {
            return;
        }

        handleStructuredInput(target, true);
    });

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            const input = root.querySelector(`#${CSS.escape(SEARCH_INPUT_ID)}`);
            if (input instanceof HTMLInputElement) {
                input.focus();
                input.select();
            }
        }
    });

    async function bootstrap() {
        render();

        try {
            const response = await fetch(root.dataset.payloadUrl || '', {
                headers: { Accept: 'application/json' },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Payload request failed with status ${response.status}.`);
            }

            state.docs = await response.json();
            state.baseUrl = state.docs?.meta?.defaultBaseUrl || state.baseUrl;
            state.selectedOperationId = state.docs?.operations?.[0]?.id || null;
            state.hydratedKey = null;
        } catch (error) {
            state.error = error instanceof Error ? error.message : String(error);
        } finally {
            state.loading = false;
            render();
        }
    }

    bootstrap();
})();
