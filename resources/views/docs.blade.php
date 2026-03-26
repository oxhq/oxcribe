<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <style>
        :root {
            color-scheme: light;
            --ox-bg: #f4eee1;
            --ox-panel: rgba(255, 255, 255, 0.9);
            --ox-panel-border: rgba(39, 33, 27, 0.1);
            --ox-text: #211b16;
            --ox-muted: #6f645c;
            --ox-accent: #0f766e;
            --ox-accent-soft: rgba(15, 118, 110, 0.12);
            --ox-get: #14532d;
            --ox-post: #1d4ed8;
            --ox-put: #92400e;
            --ox-patch: #9a3412;
            --ox-delete: #991b1b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 26rem),
                linear-gradient(180deg, #fcf7ef 0%, var(--ox-bg) 100%);
            color: var(--ox-text);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .ox-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 22rem minmax(0, 1fr);
        }

        .ox-sidebar {
            border-right: 1px solid var(--ox-panel-border);
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(12px);
            padding: 1.25rem;
        }

        .ox-main {
            padding: 1.5rem;
        }

        .ox-panel,
        .ox-empty {
            background: var(--ox-panel);
            border: 1px solid var(--ox-panel-border);
            border-radius: 1rem;
            box-shadow: 0 20px 45px rgba(33, 27, 22, 0.06);
        }

        .ox-brand {
            margin-bottom: 1rem;
        }

        .ox-kicker,
        .ox-copy,
        .ox-summary,
        .ox-description,
        .ox-muted,
        .ox-operation-path {
            color: var(--ox-muted);
        }

        .ox-kicker {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .ox-brand h1,
        .ox-hero h2,
        .ox-card h3 {
            margin: 0;
        }

        .ox-brand h1 {
            margin-top: 0.35rem;
            font-size: 1.6rem;
        }

        .ox-hero {
            margin-bottom: 1rem;
            padding: 1.2rem;
        }

        .ox-hero h2 {
            margin-top: 0.75rem;
            font-size: 1.7rem;
            word-break: break-word;
        }

        .ox-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ox-card {
            padding: 1rem;
        }

        .ox-card-wide {
            grid-column: 1 / -1;
        }

        .ox-list {
            display: grid;
            gap: 0.6rem;
            max-height: calc(100vh - 11rem);
            overflow: auto;
            padding-right: 0.15rem;
        }

        .ox-operation {
            width: 100%;
            text-align: left;
            border: 1px solid var(--ox-panel-border);
            background: white;
            border-radius: 0.85rem;
            padding: 0.75rem 0.85rem;
            cursor: pointer;
        }

        .ox-operation[data-active="true"] {
            border-color: rgba(15, 118, 110, 0.35);
            box-shadow: 0 0 0 3px var(--ox-accent-soft);
        }

        .ox-method {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            font-size: 0.75rem;
            font-weight: 800;
            color: white;
        }

        .ox-method[data-method="GET"] { background: var(--ox-get); }
        .ox-method[data-method="POST"] { background: var(--ox-post); }
        .ox-method[data-method="PUT"] { background: var(--ox-put); }
        .ox-method[data-method="PATCH"] { background: var(--ox-patch); }
        .ox-method[data-method="DELETE"] { background: var(--ox-delete); }

        .ox-row {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .ox-operation-id {
            font-size: 0.78rem;
            font-weight: 700;
        }

        .ox-mode-tabs,
        .ox-snippet-tabs {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .ox-tab,
        .ox-button {
            border: 1px solid var(--ox-panel-border);
            background: white;
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            font: inherit;
            cursor: pointer;
        }

        .ox-tab[data-active="true"] {
            background: var(--ox-accent);
            border-color: var(--ox-accent);
            color: white;
        }

        .ox-stack {
            display: grid;
            gap: 0.85rem;
        }

        .ox-label {
            display: grid;
            gap: 0.35rem;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .ox-input,
        .ox-textarea,
        .ox-code {
            width: 100%;
            border: 1px solid var(--ox-panel-border);
            border-radius: 0.9rem;
            padding: 0.8rem;
            font: inherit;
            background: #fff;
            color: var(--ox-text);
        }

        .ox-textarea,
        .ox-code {
            min-height: 11rem;
            resize: vertical;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .ox-code {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            background: #fffdfa;
        }

        .ox-section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .ox-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: var(--ox-accent-soft);
            color: var(--ox-accent);
            font-size: 0.72rem;
            font-weight: 800;
        }

        .ox-empty {
            padding: 2rem;
            text-align: center;
        }

        @media (max-width: 980px) {
            .ox-shell {
                grid-template-columns: 1fr;
            }

            .ox-sidebar {
                border-right: 0;
                border-bottom: 1px solid var(--ox-panel-border);
            }

            .ox-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div
    id="oxcribe-docs-app"
    data-payload-url="{{ $payloadUrl }}"
    data-openapi-url="{{ $openApiUrl }}"
></div>

@verbatim
<script>
    (() => {
        const { createApp, ref, computed, watch, onMounted } = window.Vue;

        const exampleModes = [
            { key: 'minimal_valid', label: 'Minimal' },
            { key: 'happy_path', label: 'Happy Path' },
            { key: 'realistic_full', label: 'Full' },
        ];

        function prettyJson(value) {
            return JSON.stringify(value ?? null, null, 2);
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

        function resolveExample(operation, mode) {
            return operation.examples?.[mode] ?? operation.examples?.happy_path ?? null;
        }

        function resolveSnippets(operation, mode) {
            return operation.snippets?.[mode] ?? operation.snippets?.happy_path ?? {};
        }

        function normalizeBaseUrl(value) {
            return (value || '').trim().replace(/\/+$/, '');
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

        async function sendTryRequest({ operation, baseUrl, bearerToken, pathParams, queryParams, headers, body }) {
            const requestHeaders = new Headers(headers || {});
            requestHeaders.set('Accept', requestHeaders.get('Accept') || 'application/json');

            if (bearerToken) {
                requestHeaders.set('Authorization', `Bearer ${bearerToken}`);
            }

            const requestUrl = buildRequestUrl(baseUrl, operation, pathParams, queryParams);
            const method = operation.method.toUpperCase();
            const options = { method, headers: requestHeaders, credentials: 'include' };

            if (!['GET', 'HEAD'].includes(method) && body !== null && body !== undefined) {
                requestHeaders.set('Content-Type', requestHeaders.get('Content-Type') || 'application/json');
                options.body = JSON.stringify(body);
            }

            const response = await fetch(requestUrl, options);
            const contentType = response.headers.get('content-type') || '';
            const payload = contentType.includes('application/json')
                ? await response.json()
                : await response.text();

            return {
                status: response.status,
                ok: response.ok,
                headers: Object.fromEntries(response.headers.entries()),
                body: payload,
            };
        }

        const OperationList = {
            props: {
                operations: { type: Array, required: true },
                selectedId: { type: String, default: null },
            },
            emits: ['select'],
            template: `
                <div class="ox-list">
                    <button
                        v-for="operation in operations"
                        :key="operation.id"
                        class="ox-operation"
                        :data-active="String(operation.id === selectedId)"
                        @click="$emit('select', operation.id)"
                    >
                        <div class="ox-row">
                            <span class="ox-method" :data-method="operation.method">{{ operation.method }}</span>
                            <span class="ox-operation-id">{{ operation.id }}</span>
                        </div>
                        <p class="ox-operation-path">{{ operation.path }}</p>
                    </button>
                </div>
            `,
        };

        const ExampleTabs = {
            props: {
                modes: { type: Array, required: true },
                activeMode: { type: String, required: true },
            },
            emits: ['select'],
            template: `
                <div class="ox-mode-tabs">
                    <button
                        v-for="mode in modes"
                        :key="mode.key"
                        class="ox-tab"
                        :data-active="String(mode.key === activeMode)"
                        @click="$emit('select', mode.key)"
                    >
                        {{ mode.label }}
                    </button>
                </div>
            `,
        };

        const CodePanel = {
            props: {
                title: { type: String, required: true },
                code: { type: String, required: true },
            },
            emits: ['copy'],
            template: `
                <section class="ox-stack">
                    <div class="ox-section-title">
                        <h3>{{ title }}</h3>
                        <button class="ox-button" type="button" @click="$emit('copy')">Copy</button>
                    </div>
                    <pre class="ox-code">{{ code }}</pre>
                </section>
            `,
        };

        const SnippetPanel = {
            props: {
                snippets: { type: Object, required: true },
                activeKind: { type: String, required: true },
            },
            emits: ['select'],
            computed: {
                snippetKinds() {
                    return Object.keys(this.snippets || {});
                },
                activeCode() {
                    return this.snippets?.[this.activeKind] || '';
                },
            },
            methods: {
                async copy() {
                    await copyText(this.activeCode);
                },
            },
            template: `
                <div class="ox-stack">
                    <div class="ox-snippet-tabs">
                        <button
                            v-for="kind in snippetKinds"
                            :key="kind"
                            class="ox-tab"
                            :data-active="String(kind === activeKind)"
                            @click="$emit('select', kind)"
                        >
                            {{ kind }}
                        </button>
                    </div>
                    <section class="ox-stack">
                        <div class="ox-section-title">
                            <h3>Snippet</h3>
                            <button class="ox-button" type="button" @click="copy">Copy</button>
                        </div>
                        <pre class="ox-code">{{ activeCode }}</pre>
                    </section>
                </div>
            `,
        };

        const TryItPanel = {
            props: {
                baseUrl: { type: String, required: true },
                bearerToken: { type: String, required: true },
                statusLabel: { type: String, required: true },
                responseText: { type: String, required: true },
            },
            emits: ['update:baseUrl', 'update:bearerToken', 'send'],
            template: `
                <div class="ox-stack">
                    <label class="ox-label">
                        Base URL
                        <input
                            class="ox-input"
                            :value="baseUrl"
                            @input="$emit('update:baseUrl', $event.target.value)"
                        >
                    </label>

                    <label class="ox-label">
                        Bearer Token
                        <input
                            class="ox-input"
                            :value="bearerToken"
                            @input="$emit('update:bearerToken', $event.target.value)"
                            placeholder="Optional"
                        >
                    </label>

                    <div class="ox-row">
                        <button class="ox-button" type="button" @click="$emit('send')">Send request</button>
                        <span class="ox-badge">{{ statusLabel }}</span>
                    </div>

                    <section class="ox-stack">
                        <h3>Response</h3>
                        <pre class="ox-code">{{ responseText }}</pre>
                    </section>
                </div>
            `,
        };

        createApp({
            components: { OperationList, ExampleTabs, CodePanel, SnippetPanel, TryItPanel },
            setup() {
                const root = document.getElementById('oxcribe-docs-app');
                const payloadUrl = root?.dataset.payloadUrl || '';
                const openApiUrl = root?.dataset.openapiUrl || '';
                const docs = ref(null);
                const selectedOperationId = ref(null);
                const mode = ref('happy_path');
                const snippetKind = ref('curl');
                const baseUrl = ref(window.location.origin || '');
                const bearerToken = ref('');
                const tryStatus = ref('Idle');
                const tryResponseText = ref('null');
                const requestBodyText = ref('null');
                const responseBodyText = ref('null');
                const loading = ref(true);
                const errorMessage = ref('');

                const selectedOperation = computed(() => {
                    return docs.value?.operations?.find((operation) => operation.id === selectedOperationId.value) || null;
                });

                const selectedExample = computed(() => {
                    return selectedOperation.value ? resolveExample(selectedOperation.value, mode.value) : null;
                });

                const selectedSnippets = computed(() => {
                    return selectedOperation.value ? resolveSnippets(selectedOperation.value, mode.value) : {};
                });

                watch(selectedSnippets, (snippets) => {
                    if (!snippets?.[snippetKind.value]) {
                        snippetKind.value = Object.keys(snippets || {})[0] || 'curl';
                    }
                }, { immediate: true });

                watch(selectedExample, (example) => {
                    requestBodyText.value = prettyJson(example?.request?.body ?? null);
                    responseBodyText.value = prettyJson(example?.response?.body ?? null);
                }, { immediate: true });

                onMounted(async () => {
                    try {
                        const response = await fetch(payloadUrl, {
                            headers: { Accept: 'application/json' },
                            credentials: 'include',
                        });

                        if (!response.ok) {
                            throw new Error(`Payload request failed with status ${response.status}.`);
                        }

                        docs.value = await response.json();
                        selectedOperationId.value = docs.value.operations?.[0]?.id || null;
                        baseUrl.value = docs.value.meta?.defaultBaseUrl || baseUrl.value;
                    } catch (error) {
                        errorMessage.value = error instanceof Error ? error.message : String(error);
                    } finally {
                        loading.value = false;
                    }
                });

                async function handleTryRequest() {
                    if (!selectedOperation.value) {
                        return;
                    }

                    try {
                        tryStatus.value = 'Sending';

                        const result = await sendTryRequest({
                            operation: selectedOperation.value,
                            baseUrl: baseUrl.value,
                            bearerToken: bearerToken.value,
                            pathParams: selectedExample.value?.request?.pathParams || {},
                            queryParams: selectedExample.value?.request?.queryParams || {},
                            headers: selectedExample.value?.request?.headers || { Accept: 'application/json' },
                            body: JSON.parse(requestBodyText.value || 'null'),
                        });

                        tryStatus.value = `Status ${result.status}`;
                        tryResponseText.value = prettyJson(result);
                    } catch (error) {
                        tryStatus.value = 'Failed';
                        tryResponseText.value = prettyJson({
                            message: error instanceof Error ? error.message : String(error),
                        });
                    }
                }

                async function copyRequest() {
                    await copyText(requestBodyText.value);
                }

                async function copyResponse() {
                    await copyText(responseBodyText.value);
                }

                return {
                    baseUrl,
                    bearerToken,
                    copyRequest,
                    copyResponse,
                    docs,
                    errorMessage,
                    exampleModes,
                    handleTryRequest,
                    loading,
                    mode,
                    openApiUrl,
                    requestBodyText,
                    responseBodyText,
                    selectedExample,
                    selectedOperation,
                    selectedOperationId,
                    selectedSnippets,
                    snippetKind,
                    tryResponseText,
                    tryStatus,
                };
            },
            template: `
                <div class="ox-shell">
                    <aside class="ox-sidebar">
                        <div class="ox-brand">
                            <p class="ox-kicker">Oxcribe Docs</p>
                            <h1>{{ docs?.info?.title || 'Oxcribe Docs' }}</h1>
                            <p class="ox-copy">OpenAPI {{ docs?.info?.openapi || '3.1.0' }} · {{ docs?.meta?.operationCount || 0 }} operations</p>
                            <p class="ox-copy"><a :href="openApiUrl">OpenAPI JSON</a></p>
                        </div>

                        <div class="ox-empty" v-if="loading">Loading docs payload…</div>
                        <div class="ox-empty" v-else-if="errorMessage">{{ errorMessage }}</div>
                        <OperationList
                            v-else
                            :operations="docs.operations"
                            :selected-id="selectedOperationId"
                            @select="selectedOperationId = $event"
                        />
                    </aside>

                    <main class="ox-main">
                        <div class="ox-empty" v-if="loading">Loading local viewer…</div>
                        <div class="ox-empty" v-else-if="errorMessage">{{ errorMessage }}</div>
                        <template v-else-if="selectedOperation">
                            <section class="ox-panel ox-hero">
                                <div class="ox-row">
                                    <span class="ox-method" :data-method="selectedOperation.method">{{ selectedOperation.method }}</span>
                                    <span class="ox-operation-id">{{ selectedOperation.id }}</span>
                                </div>
                                <h2>{{ selectedOperation.path }}</h2>
                                <p class="ox-summary">{{ selectedOperation.summary }}</p>
                                <p class="ox-description" v-if="selectedOperation.description">{{ selectedOperation.description }}</p>
                            </section>

                            <div class="ox-grid">
                                <section class="ox-card">
                                    <div class="ox-stack">
                                        <div class="ox-section-title">
                                            <h3>Examples</h3>
                                            <ExampleTabs :modes="exampleModes" :active-mode="mode" @select="mode = $event" />
                                        </div>
                                        <CodePanel title="Request Body" :code="requestBodyText" @copy="copyRequest" />
                                        <CodePanel title="Response Body" :code="responseBodyText" @copy="copyResponse" />
                                    </div>
                                </section>

                                <section class="ox-card">
                                    <SnippetPanel
                                        :snippets="selectedSnippets"
                                        :active-kind="snippetKind"
                                        @select="snippetKind = $event"
                                    />
                                </section>

                                <section class="ox-card ox-card-wide">
                                    <TryItPanel
                                        v-model:base-url="baseUrl"
                                        v-model:bearer-token="bearerToken"
                                        :status-label="tryStatus"
                                        :response-text="tryResponseText"
                                        @send="handleTryRequest"
                                    />
                                </section>
                            </div>
                        </template>
                        <div class="ox-empty" v-else>No operations are available in this document.</div>
                    </main>
                </div>
            `,
        }).mount('#oxcribe-docs-app');
    })();
</script>
@endverbatim
</body>
</html>
