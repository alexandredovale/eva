'use strict';

const state = {
    token: sessionStorage.getItem('eva_access_token') || sessionStorage.getItem('eva_admin_token') || '',
    user: null,
    documents: [],
    jobs: [],
    users: [],
    projects: [],
    modules: [],
    moduleInterfaces: [],
    activeModuleId: '',
    moduleLocalFilter: '',
    scopes: { projects: [], documents: [] },
    queryHistory: [],
    secretOwner: '',
    jobPollTimer: null,
    workerDrainActive: false,
};

sessionStorage.removeItem('eva_admin_token');

const elements = {
    accessPanel: document.querySelector('#access-panel'), accessForm: document.querySelector('#access-form'),
    accessUsername: document.querySelector('#access-username'), accessPassword: document.querySelector('#access-password'),
    accessToken: document.querySelector('#access-token'), adminAccessForm: document.querySelector('#admin-access-form'),
    recoverForm: document.querySelector('#recover-form'), topbar: document.querySelector('.topbar'), workspace: document.querySelector('#workspace'),
    connection: document.querySelector('#connection-state'), connectionLabel: document.querySelector('#connection-label'), sessionUser: document.querySelector('#session-user'),
    metricGrid: document.querySelector('#metric-grid'), documentsBody: document.querySelector('#documents-body'), documentCount: document.querySelector('#document-count'),
    queryScopes: document.querySelector('#query-scopes'), queryScopeToggle: document.querySelector('#query-scope-toggle'), queryScopePanel: document.querySelector('#query-scope-panel'), queryScopeSummary: document.querySelector('#query-scope-summary'),
    queryResult: document.querySelector('#query-result'), restartChat: document.querySelector('#restart-chat'), jobsBody: document.querySelector('#jobs-body'), auditBody: document.querySelector('#audit-body'),
    workerMonitor: document.querySelector('#worker-monitor'), workerStateLabel: document.querySelector('#worker-state-label'), workerStateDetail: document.querySelector('#worker-state-detail'),
    workerRunButtons: document.querySelectorAll('[data-run-worker]'),
    usersBody: document.querySelector('#users-body'), userCount: document.querySelector('#user-count'), projectsList: document.querySelector('#projects-list'),
    permissionForm: document.querySelector('#permission-form'), permissionTree: document.querySelector('#permission-tree'),
    projectDocuments: document.querySelector('#project-documents'), projectResponseProfile: document.querySelector('#project-response-profile'),
    menuToggle: document.querySelector('#menu-toggle'), navigation: document.querySelector('#top-navigation'),
    uploadProgress: document.querySelector('#upload-progress'), uploadProgressBar: document.querySelector('#upload-progress-bar'), uploadProgressLabel: document.querySelector('#upload-progress-label'),
    uploadProgressValue: document.querySelector('#upload-progress-value'), uploadProgressMeta: document.querySelector('#upload-progress-meta'), toast: document.querySelector('#toast'),
    secretDialog: document.querySelector('#secret-dialog'), recoverySecret: document.querySelector('#recovery-secret'),
    confirmationDialog: document.querySelector('#confirmation-dialog'), confirmationForm: document.querySelector('#confirmation-form'),
    confirmationTitle: document.querySelector('#confirmation-title'), confirmationConsequence: document.querySelector('#confirmation-consequence'),
    confirmationTarget: document.querySelector('#confirmation-target'), confirmationInput: document.querySelector('#confirmation-input'),
    confirmationError: document.querySelector('#confirmation-error'),
    processingConfirmationDialog: document.querySelector('#processing-confirmation-dialog'), processingConfirmationForm: document.querySelector('#processing-confirmation-form'),
    processingConfirmationCancel: document.querySelector('#processing-confirmation-cancel'),
    passwordResetDialog: document.querySelector('#password-reset-dialog'), passwordResetForm: document.querySelector('#admin-password-reset-form'),
    passwordResetUsername: document.querySelector('#password-reset-username'), adminResetPassword: document.querySelector('#admin-reset-password'),
    usernameRenameDialog: document.querySelector('#username-rename-dialog'), usernameRenameForm: document.querySelector('#username-rename-form'),
    usernameRenameCurrent: document.querySelector('#username-rename-current'), renamedUsername: document.querySelector('#renamed-username'),
    modulesList: document.querySelector('#modules-list'), moduleNavigation: document.querySelector('#module-navigation'),
    moduleView: document.querySelector('#view-module'), moduleDashboard: document.querySelector('#module-dashboard'),
};

const initialChatEmptyMarkup = elements.queryResult.innerHTML;
const maxQueryPayloadLength = 20_000;
const cspStyleNonce = document.querySelector('meta[name="csp-style-nonce"]')?.content || '';

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
}

function normalizeSearchText(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
}

function total(group) { return Object.values(group || {}).reduce((sum, value) => sum + Number(value || 0), 0); }
function apiPath(path) { return `api/${String(path).replace(/^\/+/, '')}`; }

async function api(path, options = {}) {
    const normalizedPath = String(path).replace(/^\/+/, '');
    const requestToken = state.token;
    const headers = new Headers(options.headers || {});
    const publicRoute = normalizedPath === 'branding' || normalizedPath.startsWith('auth/');
    if (!publicRoute && requestToken) headers.set('Authorization', `Bearer ${requestToken}`);
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    const response = await fetch(apiPath(normalizedPath), { ...options, headers, cache: 'no-store' });
    const payload = await response.json().catch(() => ({
        error: `O servidor retornou uma resposta não reconhecida (HTTP ${response.status}).`,
    }));
    if (!response.ok) {
        if (response.status === 401 && !publicRoute && state.token === requestToken) disconnect(false);
        throw new Error(payload.error || `Falha HTTP ${response.status}.`);
    }
    return payload;
}

function uploadDocument(formData, onProgress) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', apiPath('documents'));
        request.responseType = 'json';
        request.setRequestHeader('Authorization', `Bearer ${state.token}`);
        request.upload.addEventListener('progress', event => { if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100)); });
        request.addEventListener('load', () => {
            const payload = request.response || {};
            if (request.status >= 200 && request.status < 300) return resolve(payload);
            if (request.status === 401) disconnect(false);
            reject(new Error(payload.error || `Falha HTTP ${request.status}.`));
        });
        request.addEventListener('error', () => reject(new Error('Não foi possível enviar o documento.')));
        request.send(formData);
    });
}

function formatBytes(bytes) {
    const value = Number(bytes || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 ** 2) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 ** 2)).toFixed(1)} MB`;
}

function notify(message, error = false) {
    elements.toast.textContent = message;
    elements.toast.classList.toggle('error', error);
    elements.toast.hidden = false;
    clearTimeout(notify.timer);
    notify.timer = setTimeout(() => { elements.toast.hidden = true; }, 4500);
}

function setConnected(connected) {
    if (connected) {
        elements.topbar.inert = false;
        elements.workspace.inert = false;
        if (elements.accessPanel.contains(document.activeElement)) {
            elements.workspace.focus({ preventScroll: true });
        }
        elements.accessPanel.inert = true;
        elements.accessPanel.hidden = true;
    } else {
        elements.accessPanel.hidden = false;
        elements.accessPanel.inert = false;
        elements.accessUsername.focus({ preventScroll: true });
        elements.topbar.inert = true;
        elements.workspace.inert = true;
    }
    document.body.classList.toggle('auth-locked', !connected);
    elements.connection.dataset.state = connected ? 'online' : 'offline';
    elements.connectionLabel.textContent = connected ? 'Sessão ativa' : 'Desconectado';
    elements.sessionUser.textContent = connected && state.user ? state.user.username : '';
}

function applyRole() {
    const admin = state.user?.role === 'superadmin';
    document.querySelectorAll('[data-admin-only]').forEach(node => {
        if (!node.matches('[data-view-panel]')) node.hidden = !admin;
    });
    document.querySelectorAll('[data-user-only]').forEach(node => {
        if (node.matches('a')) node.hidden = admin;
    });
    const brand = document.querySelector('.brand');
    brand.dataset.view = admin ? 'overview' : 'query';
    brand.href = admin ? '#overview' : '#query';
}

async function initializeSession() {
    const { user } = await api('me');
    state.user = user;
    sessionStorage.setItem('eva_access_token', state.token);
    resetChat();
    applyRole();
    setConnected(true);
    await refreshAll();
    switchView(user.role === 'superadmin' ? 'overview' : 'query');
}

function disconnect(showMessage = true) {
    stopJobPolling();
    state.token = '';
    state.user = null;
    state.documents = [];
    state.jobs = [];
    state.users = [];
    state.projects = [];
    state.modules = [];
    state.moduleInterfaces = [];
    state.activeModuleId = '';
    state.moduleLocalFilter = '';
    elements.moduleNavigation.innerHTML = '';
    elements.moduleDashboard.innerHTML = '<div class="card empty">Selecione um módulo ativo.</div>';
    resetChat();
    sessionStorage.removeItem('eva_access_token');
    elements.accessPassword.value = '';
    elements.accessToken.value = '';
    setConnected(false);
    if (showMessage) notify('Sessão encerrada.');
}

function resetChat() {
    state.scopes = { projects: [], documents: [] };
    state.queryHistory = [];
    elements.queryResult.innerHTML = initialChatEmptyMarkup;
    elements.queryScopes.innerHTML = '<p class="tree-empty">Nenhuma obra disponível nesta sessão.</p>';
    elements.queryScopeSummary.textContent = 'Selecione o escopo da consulta';
    elements.queryScopeToggle.disabled = true;
    document.querySelector('#query-input').value = '';
    setQueryScopePanel(false);
}

function restartChat() {
    state.queryHistory = [];
    elements.queryResult.innerHTML = initialChatEmptyMarkup;
    document.querySelector('#query-input').value = '';
    document.querySelector('#query-input').focus();
}

function switchView(view, requestedModuleId = '') {
    const admin = state.user?.role === 'superadmin';
    const moduleInterface = view === 'module'
        ? state.moduleInterfaces.find(module => module.id === requestedModuleId)
        : null;
    if (view === 'module' && !moduleInterface) view = admin ? 'overview' : 'query';
    if (!admin && !['query', 'module', 'settings'].includes(view)) view = 'query';
    state.activeModuleId = view === 'module' && moduleInterface ? moduleInterface.id : '';
    document.querySelectorAll('[data-view-panel]').forEach(panel => {
        const allowed = admin ? !panel.hasAttribute('data-user-only') : !panel.hasAttribute('data-admin-only');
        const active = allowed && panel.dataset.viewPanel === view;
        panel.hidden = !active;
        panel.classList.toggle('active', active);
    });
    document.querySelectorAll('[data-view]').forEach(link => {
        const active = link.dataset.view === view
            && (view !== 'module' || link.dataset.moduleId === state.activeModuleId);
        link.classList.toggle('active', active);
    });
    document.body.classList.remove('menu-open');
    elements.menuToggle.setAttribute('aria-expanded', 'false');
    location.hash = view === 'module' ? `module/${state.activeModuleId}` : view;
    if (view === 'module') {
        loadModuleDashboard(state.activeModuleId).catch(error => {
            elements.moduleDashboard.innerHTML = `<div class="card empty">${escapeHtml(error.message)}</div>`;
            notify(error.message, true);
        });
    }
}

function renderMetrics(metrics) {
    const cards = [['Documentos', total(metrics.documents)], ['Evidências', total(metrics.evidences)], ['Evidências primárias', Number(metrics.evidences?.primary || 0)], ['Sínteses derivadas', Number(metrics.evidences?.derived || 0)], ['Embeddings', Number(metrics.embeddings || 0)], ['Derivações', Number(metrics.derivations || 0)], ['Trabalhos na fila', Number(metrics.jobs?.queued || 0)], ['Trabalhos concluídos', Number(metrics.jobs?.completed || 0)], ['Falhas registradas', Number(metrics.jobs?.failed || 0)]];
    elements.metricGrid.innerHTML = cards.map(([label, value]) => `<article class="metric-card"><strong>${escapeHtml(value)}</strong><span>${escapeHtml(label)}</span></article>`).join('');
}

function renderDocuments(documents) {
    state.documents = documents;
    elements.documentCount.textContent = `${documents.length} documento${documents.length === 1 ? '' : 's'}`;
    elements.documentsBody.innerHTML = documents.length ? documents.map(document => `<tr><td><strong>${escapeHtml(document.title)}</strong><br><small>${escapeHtml(document.public_id)}</small></td><td>${escapeHtml(document.format)}</td><td>${Number(document.node_count || 0)}</td><td>${Number(document.primary_evidence_count || 0)}</td><td>${Number(document.derived_evidence_count || 0)}</td><td>${Number(document.embedding_count || 0)}</td><td>${renderDocumentAction(document)}</td></tr>`).join('') : '<tr><td colspan="7" class="empty">Nenhum documento persistido.</td></tr>';
    renderProjectDocumentChoices();
}

function renderDocumentAction(document) {
    const status = document.processing_status || 'pending';
    let primaryAction = '';

    if (status === 'completed') primaryAction = '<span class="status status-completed">Processado</span>';
    else if (status === 'running') primaryAction = '<span class="status status-running">Processando</span>';
    else if (status === 'queued') primaryAction = '<span class="status status-queued">Na fila</span>';
    else if (status === 'failed') primaryAction = '<span class="status status-failed">Falha — veja Processamento</span>';
    else primaryAction = `<button type="button" class="button button-primary button-small" data-process="${Number(document.id)}">Processar</button>`;

    return `<div class="document-actions">${primaryAction}<button type="button" class="button button-danger button-small" data-delete-document="${Number(document.id)}">Excluir</button></div>`;
}

function renderScopes(scopes) {
    state.scopes = scopes;
    const branches = scopes.projects.map(project => {
        const projectControl = project.full_access
            ? `<label class="tree-node tree-project"><input type="checkbox" data-query-project value="${Number(project.id)}"><span class="tree-node-copy"><strong>${escapeHtml(project.name)}</strong><small>Projeto completo · ${project.documents.length} obra${project.documents.length === 1 ? '' : 's'}</small></span></label>`
            : `<div class="tree-node tree-project tree-project-context"><span class="tree-node-copy"><strong>${escapeHtml(project.name)}</strong><small>Acesso somente às obras indicadas</small></span></div>`;
        const documents = project.documents.map(document => queryDocumentNode(document)).join('');

        return `<section class="tree-branch" data-query-branch>${projectControl}<div class="tree-children">${documents}</div></section>`;
    });

    if (scopes.documents.length) {
        branches.push(`<section class="tree-branch tree-standalone"><div class="tree-node tree-project tree-project-context"><span class="tree-node-copy"><strong>Obras sem projeto</strong><small>Permissões individuais</small></span></div><div class="tree-children">${scopes.documents.map(document => queryDocumentNode(document)).join('')}</div></section>`);
    }

    elements.queryScopes.innerHTML = branches.length ? branches.join('') : '<p class="tree-empty">Nenhuma obra permitida.</p>';
    elements.queryScopeToggle.disabled = branches.length === 0;
    setQueryScopePanel(false);
    updateQueryScopeSummary();
}

function queryDocumentNode(document) {
    return `<label class="tree-node tree-document"><input type="checkbox" data-query-document data-explicit="0" value="${Number(document.id)}"><span class="tree-node-copy"><span>${escapeHtml(document.title)}</span><small>${escapeHtml(document.public_id)}</small></span></label>`;
}

function setQueryScopePanel(open) {
    elements.queryScopePanel.hidden = !open;
    elements.queryScopeToggle.setAttribute('aria-expanded', String(open));
}

function selectedQueryScopes() {
    const selectedProjects = Array.from(elements.queryScopes.querySelectorAll('[data-query-project]:checked'));
    const scopes = selectedProjects.map(input => ({ type: 'project', id: Number(input.value) }));
    const coveredDocumentIds = new Set();

    selectedProjects.forEach(input => {
        const project = state.scopes.projects.find(item => Number(item.id) === Number(input.value));
        project?.documents.forEach(document => coveredDocumentIds.add(Number(document.id)));
    });

    const selectedDocumentIds = new Set(
        Array.from(elements.queryScopes.querySelectorAll('[data-query-document][data-explicit="1"]:checked'))
            .map(input => Number(input.value))
            .filter(documentId => !coveredDocumentIds.has(documentId))
    );
    selectedDocumentIds.forEach(documentId => scopes.push({ type: 'document', id: documentId }));

    return scopes;
}

function updateQueryScopeSummary() {
    const scopes = selectedQueryScopes();
    const documentIds = new Set();

    scopes.forEach(scope => {
        if (scope.type === 'project') {
            const project = state.scopes.projects.find(item => Number(item.id) === scope.id);
            project?.documents.forEach(document => documentIds.add(Number(document.id)));
        } else {
            documentIds.add(scope.id);
        }
    });

    if (!scopes.length) elements.queryScopeSummary.textContent = 'Selecione o escopo da consulta';
    else if (scopes.length === 1 && scopes[0].type === 'project') {
        const project = state.scopes.projects.find(item => Number(item.id) === scopes[0].id);
        elements.queryScopeSummary.textContent = project ? `Projeto: ${project.name}` : '1 projeto selecionado';
    } else elements.queryScopeSummary.textContent = `${documentIds.size} obra${documentIds.size === 1 ? '' : 's'} selecionada${documentIds.size === 1 ? '' : 's'}`;
}

function renderJobs(jobs) {
    state.jobs = jobs;
    const stageLabels = { summaries: 'Sínteses', embeddings: 'Embeddings' };
    const statusLabels = { queued: 'Na fila', running: 'Processando', completed: 'Concluído', failed: 'Falha' };
    elements.jobsBody.innerHTML = jobs.length ? jobs.map(job => {
        const progress = Number(job.progress_percent || 0);
        const current = Number(job.progress_current || 0);
        const total = Number(job.progress_total || 0);
        const progressDetail = job.status === 'queued'
            ? (Number(job.run_count || 0) > 0 ? 'Parcial; aguarda nova execução do worker.' : 'Aguardando o worker iniciar.')
            : job.status === 'running'
                ? 'Unidades persistidas em tempo real.'
                : job.status === 'completed'
                    ? 'Etapa finalizada.'
                    : (job.last_error || 'A etapa encontrou uma falha.');
        const progressCell = `<div class="job-progress" data-status="${escapeHtml(job.status)}"><div class="job-progress-copy"><span>${progress}%</span><strong>${current} / ${total}</strong></div><progress max="100" value="${progress}">${progress}%</progress><small>${escapeHtml(progressDetail)}</small></div>`;

        return `<tr><td><strong>${escapeHtml(job.public_id)}</strong></td><td>${escapeHtml(job.document_title)}<br><small>${escapeHtml(job.document_public_id)}</small></td><td>${escapeHtml(stageLabels[job.stage] || job.stage)}</td><td><span class="status status-${escapeHtml(job.status)}">${escapeHtml(statusLabels[job.status] || job.status)}</span></td><td>${progressCell}</td><td>${Number(job.run_count || 0)}</td><td>${Number(job.failure_count || 0)} / ${Number(job.max_failures || 0)}</td><td>${job.status === 'failed' && Number(job.failure_count) < Number(job.max_failures) ? `<button type="button" class="button button-quiet button-small" data-retry="${escapeHtml(job.public_id)}">Retomar</button>` : '—'}</td></tr>`;
    }).join('') : '<tr><td colspan="8" class="empty">Nenhum trabalho agendado.</td></tr>';
    renderWorkerMonitor(jobs);
}

function renderWorkerMonitor(jobs) {
    if (!elements.workerMonitor) return;
    const running = jobs.filter(job => job.status === 'running');
    const queued = jobs.filter(job => job.status === 'queued');
    const failed = jobs.filter(job => job.status === 'failed');
    updateWorkerRunButtons();

    if (state.workerDrainActive) {
        elements.workerMonitor.dataset.state = 'running';
        elements.workerStateLabel.textContent = 'Worker ativo pelo navegador.';
        elements.workerStateDetail.textContent = 'A fila está sendo consumida; mantenha esta aba aberta.';
        return;
    }

    if (running.length) {
        elements.workerMonitor.dataset.state = 'running';
        elements.workerStateLabel.textContent = `Worker ativo: ${running.length} etapa(s) em processamento.`;
        elements.workerStateDetail.textContent = 'O progresso é atualizado automaticamente a cada 3 segundos.';
        return;
    }

    if (queued.length) {
        elements.workerMonitor.dataset.state = 'waiting';
        elements.workerStateLabel.textContent = `Worker inativo: ${queued.length} etapa(s) aguardando execução.`;
        elements.workerStateDetail.textContent = 'Use “Processar fila no navegador” para iniciar as etapas pendentes.';
        return;
    }

    if (failed.length) {
        elements.workerMonitor.dataset.state = 'failed';
        elements.workerStateLabel.textContent = `${failed.length} etapa(s) com falha.`;
        elements.workerStateDetail.textContent = 'Consulte o erro na barra e use Retomar quando ainda houver tentativas disponíveis.';
        return;
    }

    elements.workerMonitor.dataset.state = 'idle';
    elements.workerStateLabel.textContent = 'Sem trabalhos ativos.';
    elements.workerStateDetail.textContent = 'A tela é atualizada automaticamente durante o processamento.';
}

function updateWorkerRunButtons() {
    const queued = state.jobs.some(job => job.status === 'queued');

    elements.workerRunButtons.forEach(button => {
        button.disabled = state.workerDrainActive || !queued || state.user?.role !== 'superadmin';
        button.textContent = state.workerDrainActive ? 'Processando fila…' : 'Processar fila no navegador';
    });
}

function renderAudit(events) {
    elements.auditBody.innerHTML = events.length ? events.map(event => `<tr><td><strong>${escapeHtml(event.event_type)}</strong></td><td>${escapeHtml(event.entity_type || '—')}</td><td>${escapeHtml(event.entity_id || '—')}</td><td>${escapeHtml(event.created_at)}</td><td><small>${escapeHtml(JSON.stringify(event.metadata || {}))}</small></td></tr>`).join('') : '<tr><td colspan="5" class="empty">Nenhum evento registrado.</td></tr>';
}

function renderUsers(users) {
    state.users = users;
    elements.userCount.textContent = `${users.length} usuário${users.length === 1 ? '' : 's'}`;
    elements.usersBody.innerHTML = users.length ? users.map(user => `<tr><td><strong>${escapeHtml(user.username)}</strong></td><td><span class="status ${user.active ? 'status-ready' : 'status-failed'}">${user.active ? 'ativo' : 'inativo'}</span></td><td>${escapeHtml(user.last_login_at || 'Nunca')}</td><td>${user.project_ids.length} projeto(s) · ${user.document_ids.length} obra(s)</td><td class="table-actions"><button class="button button-primary button-small" type="button" data-permissions="${user.id}">Permissões</button><button class="button button-quiet button-small" type="button" data-rename-user="${user.id}">Renomear</button><button class="button button-quiet button-small" type="button" data-reset="${user.id}">Nova senha</button><button class="button button-quiet button-small" type="button" data-toggle-user="${user.id}" data-active="${user.active ? '0' : '1'}">${user.active ? 'Desativar' : 'Ativar'}</button><button class="button button-danger button-small" type="button" data-delete-user="${user.id}">Excluir</button></td></tr>`).join('') : '<tr><td colspan="5" class="empty">Nenhum usuário cadastrado.</td></tr>';
}

function renderModules(modules) {
    state.modules = modules;
    elements.modulesList.innerHTML = modules.length ? modules.map(module => {
        if (!module.valid) return `<article class="card module-card"><div class="module-card-header"><div><p class="eyebrow">Pacote inválido</p><h2>${escapeHtml(module.directory)}</h2></div><span class="status status-failed">inválido</span></div><p>${escapeHtml(module.error || 'Manifesto incompatível.')}</p></article>`;
        return `<article class="card module-card" data-module-card="${escapeHtml(module.id)}"><div class="module-card-header"><div><p class="eyebrow">${escapeHtml(module.vendor)}</p><h2>${escapeHtml(module.name)}</h2><p>${escapeHtml(module.id)}</p></div><span class="status ${module.active ? 'status-ready' : 'status-queued'}">${module.active ? 'ativo' : 'inativo'}</span></div><div class="module-meta"><span>v${escapeHtml(module.version)}</span><span>contract ${escapeHtml(module.eva_contract)}</span><span>${escapeHtml(module.storage?.driver || '')}</span></div><div class="module-actions"><button type="button" class="button ${module.active ? 'button-quiet' : 'button-primary'}" data-module-toggle="${escapeHtml(module.id)}" data-active="${module.active ? '0' : '1'}">${module.active ? 'Desativar' : 'Ativar'}</button><button type="button" class="button button-danger" data-module-delete="${escapeHtml(module.id)}" data-module-name="${escapeHtml(module.name)}">Excluir</button></div></article>`;
    }).join('') : '<div class="card empty">Nenhum pacote de módulo instalado.</div>';
}

function renderModuleInterfaces(modules) {
    state.moduleInterfaces = Array.isArray(modules) ? modules : [];
    elements.moduleNavigation.innerHTML = state.moduleInterfaces.map(module =>
        `<a href="#module/${encodeURIComponent(module.id)}" data-view="module" data-module-id="${escapeHtml(module.id)}"><span>${escapeHtml(module.name)}</span></a>`
    ).join('');

    if (state.activeModuleId && !state.moduleInterfaces.some(module => module.id === state.activeModuleId)) {
        switchView(state.user?.role === 'superadmin' ? 'overview' : 'query');
    }
}

function filterModuleEntries() {
    const filter = elements.moduleDashboard.querySelector('[data-module-content-filter]');
    const entries = Array.from(elements.moduleDashboard.querySelectorAll('[data-module-entry]'));
    if (!filter) return;
    const query = normalizeSearchText(filter.value);
    state.moduleLocalFilter = filter.value;
    let visible = 0;

    entries.forEach(entry => {
        const matches = !query || normalizeSearchText(entry.textContent).includes(query);
        entry.hidden = !matches;
        if (matches) {
            visible += 1;
            return;
        }

        const toggle = entry.querySelector('[data-module-accordion-toggle]');
        const body = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (body) body.hidden = true;
    });

    const total = entries.length;
    const count = elements.moduleDashboard.querySelector('[data-module-filter-count]');
    const singular = count?.dataset.moduleItemSingular || 'item';
    const plural = count?.dataset.moduleItemPlural || 'itens';
    const totalLabel = `${total} ${total === 1 ? singular : plural}`;
    const empty = elements.moduleDashboard.querySelector('[data-module-filter-empty]');
    if (count) count.textContent = query ? `${visible} de ${totalLabel}` : totalLabel;
    if (empty) empty.hidden = !query || visible > 0 || total === 0;
}

function renderModuleDashboard(dashboard) {
    if (dashboard?.contract !== 'eva.module.dashboard/1' || typeof dashboard.html !== 'string' || typeof dashboard.css !== 'string') {
        throw new Error('O módulo retornou uma interface incompatível.');
    }

    elements.moduleDashboard.innerHTML = dashboard.html;
    const style = document.createElement('style');
    style.dataset.moduleStyle = state.activeModuleId;
    if (cspStyleNonce) style.setAttribute('nonce', cspStyleNonce);
    style.textContent = dashboard.css;
    elements.moduleDashboard.prepend(style);

    const filter = elements.moduleDashboard.querySelector('[data-module-content-filter]');
    if (filter && state.moduleLocalFilter) {
        filter.value = state.moduleLocalFilter;
    }
    filterModuleEntries();
}

async function loadModuleDashboard(moduleId) {
    if (!moduleId || moduleId !== state.activeModuleId) return;
    const parameters = new URLSearchParams();
    elements.moduleDashboard.querySelectorAll('[data-module-filter]').forEach(control => {
        if (control.name && control.value !== '') parameters.set(control.name, control.value);
        else if (control.dataset.moduleFilter && control.value !== '') parameters.set(control.dataset.moduleFilter, control.value);
    });
    elements.moduleDashboard.setAttribute('aria-busy', 'true');

    try {
        const suffix = parameters.size ? `?${parameters.toString()}` : '';
        const { dashboard } = await api(`modules/${encodeURIComponent(moduleId)}/dashboard${suffix}`);
        if (moduleId !== state.activeModuleId) return;
        renderModuleDashboard(dashboard);
    } finally {
        elements.moduleDashboard.removeAttribute('aria-busy');
    }
}

function moduleActionRequestId() {
    if (typeof crypto?.randomUUID === 'function') return crypto.randomUUID();
    const random = crypto.getRandomValues(new Uint32Array(4));
    return `module-${Array.from(random, value => value.toString(16).padStart(8, '0')).join('')}`;
}

function serializeModuleActionInput(form, submitter = null) {
    const input = {};
    const data = form instanceof HTMLFormElement ? new FormData(form) : new FormData();

    if (submitter?.name && !data.has(submitter.name)) {
        data.append(submitter.name, submitter.value || '1');
    }

    data.forEach((value, rawName) => {
        if (value instanceof File) throw new Error('Ações modulares não aceitam arquivos neste contrato.');
        const forceArray = rawName.endsWith('[]');
        const name = forceArray ? rawName.slice(0, -2) : rawName;
        if (!name) return;

        if (Object.prototype.hasOwnProperty.call(input, name)) {
            input[name] = Array.isArray(input[name])
                ? [...input[name], value]
                : [input[name], value];
        } else {
            input[name] = forceArray ? [value] : value;
        }
    });

    return input;
}

async function executeModuleAction(actionId, form = null, submitter = null) {
    const moduleId = state.activeModuleId;
    if (!moduleId || !/^[a-z][a-z0-9_.-]{2,79}$/.test(actionId || '')) {
        throw new Error('A ação modular solicitada é inválida.');
    }

    const controls = form instanceof HTMLFormElement
        ? Array.from(form.querySelectorAll('button, input, select, textarea'))
        : submitter ? [submitter] : [];
    const previousDisabled = controls.map(control => control.disabled);
    controls.forEach(control => { control.disabled = true; });
    elements.moduleDashboard.setAttribute('aria-busy', 'true');

    try {
        const { action } = await api(
            `modules/${encodeURIComponent(moduleId)}/actions/${encodeURIComponent(actionId)}`,
            {
                method: 'POST',
                body: JSON.stringify({
                    request_id: moduleActionRequestId(),
                    input: serializeModuleActionInput(form, submitter),
                }),
            }
        );

        if (moduleId !== state.activeModuleId) return;

        if (action?.contract !== 'eva.module.action/1'
            || !action.dashboard
            || (action.notice !== undefined && (
                !['success', 'info', 'warning'].includes(action.notice?.type)
                || typeof action.notice?.message !== 'string'
            ))) {
            throw new Error('O módulo retornou uma ação incompatível.');
        }

        renderModuleDashboard(action.dashboard);
        if (action.notice?.message) notify(action.notice.message);
    } finally {
        if (moduleId === state.activeModuleId) {
            elements.moduleDashboard.removeAttribute('aria-busy');
        }
        controls.forEach((control, index) => {
            if (control.isConnected) control.disabled = previousDisabled[index];
        });
    }
}

function renderProjects(projects) {
    state.projects = projects;
    elements.projectsList.innerHTML = projects.length ? projects.map(project => {
        const hasResponseProfile = Boolean(String(project.response_profile || '').trim());

        return `<article class="card project-card"><p class="eyebrow">Projeto</p><h2>${escapeHtml(project.name)}</h2><p>${project.documents.length} obra${project.documents.length === 1 ? '' : 's'}</p><p class="project-profile-status ${hasResponseProfile ? 'is-configured' : ''}">${hasResponseProfile ? 'Perfil de respostas configurado' : 'Sem perfil de respostas'}</p><ul>${project.documents.map(document => `<li>${escapeHtml(document.title)}</li>`).join('')}</ul><div class="project-actions"><button class="button button-quiet" type="button" data-edit-project="${project.id}">Editar projeto</button><button class="button button-danger" type="button" data-delete-project="${project.id}">Excluir projeto e obras</button></div></article>`;
    }).join('') : '<div class="card empty">Nenhum projeto cadastrado.</div>';
    renderProjectDocumentChoices();
}

function confirmTypedDeletion(label, expectedValue, consequence) {
    if (typeof confirmTypedDeletion.resolve === 'function') {
        confirmTypedDeletion.resolve(false);
    }

    elements.confirmationTitle.textContent = `Excluir ${label.toLowerCase()}?`;
    elements.confirmationConsequence.textContent = consequence;
    elements.confirmationTarget.textContent = expectedValue;
    elements.confirmationInput.value = '';
    elements.confirmationError.hidden = true;
    elements.confirmationDialog.hidden = false;
    confirmTypedDeletion.trigger = document.activeElement;
    elements.topbar.inert = true;
    elements.workspace.inert = true;
    document.body.classList.add('auth-locked');
    setTimeout(() => elements.confirmationInput.focus(), 0);

    return new Promise(resolve => {
        confirmTypedDeletion.resolve = resolve;
        confirmTypedDeletion.expectedValue = expectedValue;
    });
}

function sharedDocumentsForProject(project) {
    const otherDocumentIds = new Set(
        state.projects
            .filter(item => item.id !== project.id)
            .flatMap(item => item.document_ids.map(Number))
    );

    return project.documents.filter(document => otherDocumentIds.has(Number(document.id)));
}

function projectDeletionConsequence(project) {
    const sharedDocuments = sharedDocumentsForProject(project);
    const base = `Esta ação excluirá permanentemente o projeto e suas ${project.documents.length} obra(s).`;

    if (!sharedDocuments.length) {
        return `${base} Todos os dados documentais relacionados também serão removidos.`;
    }

    const titles = sharedDocuments.map(document => document.title).join('; ');

    return `${base} ${sharedDocuments.length} obra(s) também pertencem a outros projetos: ${titles}. `
        + 'Para mantê-las ativas, cancele agora, edite este projeto, desmarque essas obras, salve o projeto sem elas e somente depois retorne para excluí-lo. '
        + 'Se continuar, as obras compartilhadas serão excluídas globalmente e desaparecerão dos demais projetos.';
}

function closeTypedDeletion(result) {
    if (typeof confirmTypedDeletion.resolve !== 'function') return;
    const resolve = confirmTypedDeletion.resolve;
    confirmTypedDeletion.resolve = null;
    confirmTypedDeletion.expectedValue = '';
    elements.confirmationDialog.hidden = true;
    elements.confirmationForm.reset();
    elements.confirmationError.hidden = true;
    elements.topbar.inert = !state.user;
    elements.workspace.inert = !state.user;
    document.body.classList.toggle('auth-locked', !state.user || !elements.secretDialog.hidden);
    if (confirmTypedDeletion.trigger instanceof HTMLElement) confirmTypedDeletion.trigger.focus();
    confirmTypedDeletion.trigger = null;
    resolve(result);
}

function confirmQueueProcessing() {
    if (typeof confirmQueueProcessing.resolve === 'function') {
        confirmQueueProcessing.resolve(false);
    }

    confirmQueueProcessing.trigger = document.activeElement;
    elements.processingConfirmationDialog.hidden = false;
    elements.topbar.inert = true;
    elements.workspace.inert = true;
    document.body.classList.add('auth-locked');
    setTimeout(() => elements.processingConfirmationCancel.focus(), 0);

    return new Promise(resolve => {
        confirmQueueProcessing.resolve = resolve;
    });
}

function closeQueueProcessingConfirmation(result) {
    if (typeof confirmQueueProcessing.resolve !== 'function') return;
    const resolve = confirmQueueProcessing.resolve;
    confirmQueueProcessing.resolve = null;
    elements.processingConfirmationDialog.hidden = true;
    elements.processingConfirmationForm.reset();
    elements.topbar.inert = !state.user;
    elements.workspace.inert = !state.user;
    document.body.classList.toggle('auth-locked', !state.user || !elements.secretDialog.hidden);
    if (confirmQueueProcessing.trigger instanceof HTMLElement) confirmQueueProcessing.trigger.focus();
    confirmQueueProcessing.trigger = null;
    resolve(result);
}

function openPasswordReset(user) {
    openPasswordReset.user = user;
    openPasswordReset.trigger = document.activeElement;
    elements.passwordResetUsername.textContent = user.username;
    elements.passwordResetForm.reset();
    elements.passwordResetDialog.hidden = false;
    elements.topbar.inert = true;
    elements.workspace.inert = true;
    document.body.classList.add('auth-locked');
    setTimeout(() => elements.adminResetPassword.focus(), 0);
}

function closePasswordReset() {
    elements.passwordResetDialog.hidden = true;
    elements.passwordResetForm.reset();
    elements.topbar.inert = !state.user;
    elements.workspace.inert = !state.user;
    document.body.classList.toggle('auth-locked', !state.user || !elements.secretDialog.hidden);
    if (openPasswordReset.trigger instanceof HTMLElement) openPasswordReset.trigger.focus();
    openPasswordReset.trigger = null;
    openPasswordReset.user = null;
}

function openUsernameRename(user) {
    openUsernameRename.user = user;
    openUsernameRename.trigger = document.activeElement;
    elements.usernameRenameCurrent.textContent = user.username;
    elements.usernameRenameForm.reset();
    elements.renamedUsername.value = user.username;
    elements.usernameRenameDialog.hidden = false;
    elements.topbar.inert = true;
    elements.workspace.inert = true;
    document.body.classList.add('auth-locked');
    setTimeout(() => { elements.renamedUsername.focus(); elements.renamedUsername.select(); }, 0);
}

function closeUsernameRename() {
    elements.usernameRenameDialog.hidden = true;
    elements.usernameRenameForm.reset();
    elements.topbar.inert = !state.user;
    elements.workspace.inert = !state.user;
    document.body.classList.toggle('auth-locked', !state.user || !elements.secretDialog.hidden);
    if (openUsernameRename.trigger instanceof HTMLElement) openUsernameRename.trigger.focus();
    openUsernameRename.trigger = null;
    openUsernameRename.user = null;
}

function renderProjectDocumentChoices(selected = null) {
    if (!elements.projectDocuments) return;
    const checked = selected || new Set(Array.from(elements.projectDocuments.querySelectorAll('input:checked')).map(input => Number(input.value)));
    elements.projectDocuments.innerHTML = state.documents.length ? state.documents.map(document => `<label><input type="checkbox" value="${Number(document.id)}" ${checked.has(Number(document.id)) ? 'checked' : ''}><span>${escapeHtml(document.title)}</span><small>${escapeHtml(document.public_id)}</small></label>`).join('') : '<p>Nenhum documento disponível.</p>';
}

function renderPermissionTree(user) {
    const projectIds = new Set(user.project_ids.map(Number));
    const documentIds = new Set(user.document_ids.map(Number));
    const projectDocumentIds = new Set(state.projects.flatMap(project => project.document_ids.map(Number)));
    const branches = state.projects.map(project => {
        const inherited = projectIds.has(Number(project.id));
        const children = project.documents.map(document => {
            const explicit = documentIds.has(Number(document.id));
            return permissionDocumentNode(document, explicit, inherited);
        }).join('');

        return `<section class="tree-branch" data-permission-branch><label class="tree-node tree-project"><input type="checkbox" data-permission-project value="${Number(project.id)}" ${inherited ? 'checked' : ''}><span class="tree-node-copy"><strong>${escapeHtml(project.name)}</strong><small>Acesso completo · ${project.documents.length} obra${project.documents.length === 1 ? '' : 's'}</small></span></label><div class="tree-children">${children || '<p class="tree-empty">Projeto sem obras.</p>'}</div></section>`;
    });
    const standalone = state.documents.filter(document => !projectDocumentIds.has(Number(document.id)));

    if (standalone.length) {
        branches.push(`<section class="tree-branch tree-standalone"><div class="tree-node tree-project tree-project-context"><span class="tree-node-copy"><strong>Obras sem projeto</strong><small>Permissões individuais</small></span></div><div class="tree-children">${standalone.map(document => permissionDocumentNode(document, documentIds.has(Number(document.id)), false)).join('')}</div></section>`);
    }

    elements.permissionTree.innerHTML = branches.length ? branches.join('') : '<p class="tree-empty">Nenhum projeto ou obra cadastrado.</p>';
}

function permissionDocumentNode(document, explicit, inherited) {
    return `<label class="tree-node tree-document ${inherited ? 'is-inherited' : ''}"><input type="checkbox" data-permission-document data-explicit="${explicit ? '1' : '0'}" data-public-id="${escapeHtml(document.public_id)}" value="${Number(document.id)}" ${(explicit || inherited) ? 'checked' : ''} ${inherited ? 'disabled' : ''}><span class="tree-node-copy"><span>${escapeHtml(document.title)}</span><small>${inherited ? 'Incluída pelo projeto' : escapeHtml(document.public_id)}</small></span></label>`;
}

function renderQuery(result, question, index) {
    const evidences = result.evidences_used || [], simetry = result.simetry_interactions || [], assimetry = result.assimetry_interactions || [], limitations = result.limitations || [], contextIntelligence = result.context_intelligence || [];
    const technicalDetails = state.user?.role === 'superadmin'
        ? `${renderContextIntelligence(contextIntelligence)}<div class="result-section"><h2>Interações simetry</h2>${renderList(simetry, item => item.summary)}</div><div class="result-section"><h2>Interações assimetry</h2>${renderList(assimetry, item => item.summary)}</div><div class="result-section"><h2>Limitações</h2>${renderList(limitations, item => item)}</div>`
        : '';

    return `<section class="chat-turn"><article class="chat-message-user"><p class="eyebrow">Você</p><p>${escapeHtml(question)}</p></article><article class="card chat-message-assistant"><p class="eyebrow">Resposta documental</p><div class="answer">${escapeHtml(result.answer || '')}</div><div class="result-section"><h2>Evidências utilizadas</h2>${renderEvidenceList(evidences)}</div><div class="copy-result-action"><button type="button" class="button button-quiet button-copy-result" data-copy-query="${index}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg><span>Copiar pergunta e resposta</span></button></div>${technicalDetails}</article></section>`;
}

function renderConversation(pendingQuestion = '') {
    const completed = state.queryHistory.map((turn, index) => renderQuery(turn.result, turn.user, index)).join('');
    const pending = pendingQuestion
        ? `<section class="chat-turn"><article class="chat-message-user"><p class="eyebrow">Você</p><p>${escapeHtml(pendingQuestion)}</p></article><article class="card chat-message-assistant chat-message-pending"><p class="query-loading"><span>Consultando evidências</span><span class="query-loading-dots" aria-hidden="true"><span class="query-loading-dot"></span><span class="query-loading-dot"></span><span class="query-loading-dot"></span></span><span class="sr-only">…</span></p></article></section>`
        : '';

    elements.queryResult.innerHTML = `<div class="chat-transcript">${completed}${pending}</div>`;
    elements.queryResult.scrollTop = elements.queryResult.scrollHeight;
}

function buildConversationalInput(input) {
    const historyBlocks = state.queryHistory.slice(-3).map(turn => `# Interação Anterior:\n## Usuário\n${turn.user}\n## Resposta\n${turn.response}`);

    while (historyBlocks.length && new TextEncoder().encode(`${input}\n\n${historyBlocks.join('\n\n')}`).length > maxQueryPayloadLength) {
        historyBlocks.shift();
    }

    return historyBlocks.length ? `${input}\n\n${historyBlocks.join('\n\n')}` : input;
}

function rememberConversationTurn(user, result) {
    state.queryHistory.push({ user, response: result.answer || '', result });
}

function renderList(items, label) { return items.length ? `<ul>${items.map(item => `<li>${escapeHtml(label(item))}</li>`).join('')}</ul>` : '<p>Nenhum registro.</p>'; }

function renderContextIntelligence(analyses) {
    if (!analyses.length) return '';

    const formatMetric = value => value === null || !Number.isFinite(Number(value))
        ? 'indefinido'
        : Number(value).toFixed(6);
    const regionLabels = { core: 'núcleo', convergence: 'convergência', empty: 'vazio' };

    return `<div class="result-section"><h2>Context Intelligence Engine</h2><ul>${analyses.map((analysis, index) => {
        const coreCount = Array.isArray(analysis.core) ? analysis.core.length : 0;
        const convergenceCount = Array.isArray(analysis.convergence) ? analysis.convergence.length : 0;
        const discardedCount = Array.isArray(analysis.discarded) ? analysis.discarded.length : 0;
        const region = regionLabels[analysis.selected_region] || analysis.selected_region || 'vazio';
        const source = analysis.document ? `${analysis.document}${analysis.document_id ? ` (${analysis.document_id})` : ''}` : `Distribuição ${index + 1}`;
        return `<li><strong>${escapeHtml(source)}:</strong> ${Number(analysis.candidate_count || 0)} candidatos · μ ${formatMetric(analysis.mean)} · σ ${formatMetric(analysis.standard_deviation)} · CV ${formatMetric(analysis.coefficient_of_variation)} · contexto: ${escapeHtml(region)} (${Number(analysis.selected_count || 0)}) · regiões ${coreCount}/${convergenceCount}/${discardedCount}</li>`;
    }).join('')}</ul></div>`;
}

function renderEvidenceList(evidences) {
    if (!evidences.length) return '<p>Nenhum registro.</p>';

    return `<ul class="evidence-list">${evidences.map(evidence => `<li><code>${escapeHtml(evidence.id)}</code><span>${escapeHtml(formatEvidenceBreadcrumb(evidence))}</span></li>`).join('')}</ul>`;
}

function formatEvidenceBreadcrumb(evidence) {
    const breadcrumb = [];
    if (evidence.document) breadcrumb.push(String(evidence.document).trim());
    const structuralParts = String(evidence.structural_path || '')
        .split('/')
        .filter(Boolean)
        .map(formatStructuralSegment);

    if (structuralParts.length) {
        breadcrumb.push(...structuralParts);
    } else if (evidence.node) {
        breadcrumb.push(String(evidence.node).trim());
    }

    return breadcrumb.filter(Boolean).join(' › ') || 'Evidência documental';
}

function formatStructuralSegment(segment) {
    const normalized = String(segment).replace(/-/g, ' ').trim();
    if (!normalized) return '';

    const withRomanNumerals = normalized.replace(
        /(capítulo\s+)([ivxlcdm]+)\b/giu,
        (_, prefix, numeral) => prefix + numeral.toUpperCase()
    );

    return withRomanNumerals.charAt(0).toLocaleUpperCase('pt-BR') + withRomanNumerals.slice(1);
}

function buildQueryCopyText(question, answer, evidences) {
    const references = evidences.length
        ? evidences.map(evidence => `${evidence.id} — ${formatEvidenceBreadcrumb(evidence)}`).join('\n')
        : 'Nenhuma evidência utilizada.';

    return `Pergunta\n${String(question || '').trim()}\n\nResposta\n${String(answer || '').trim()}\n\nEvidências utilizadas\n${references}`;
}

async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();

    if (!copied) throw new Error('Cópia não suportada pelo navegador.');
}

async function refreshAll() {
    if (state.user?.role === 'superadmin') {
        const [metrics, documents, jobs, audit, users, projects, scopes, interfaces] = await Promise.all([api('metrics'), api('documents'), api('jobs'), api('audit'), api('admin/users'), api('admin/projects'), api('scopes'), api('modules')]);
        renderMetrics(metrics.metrics); renderDocuments(documents.documents); renderJobs(jobs.jobs); renderAudit(audit.events); renderUsers(users.users); renderProjects(projects.projects); renderScopes(scopes.scopes);
        renderModuleInterfaces(interfaces.modules);
        await refreshModules().catch(() => {});
        scheduleJobPolling();
    } else {
        const [{ scopes }, { modules }] = await Promise.all([api('scopes'), api('modules')]);
        renderScopes(scopes);
        renderModuleInterfaces(modules);
    }

    if (state.activeModuleId) await loadModuleDashboard(state.activeModuleId);
}

async function refreshModules() {
    if (state.user?.role !== 'superadmin') return;
    const { modules } = await api('admin/modules');
    renderModules(modules);
}

async function refreshModuleInterfaces() {
    const { modules } = await api('modules');
    renderModuleInterfaces(modules);
}

function stopJobPolling() {
    clearTimeout(state.jobPollTimer);
    state.jobPollTimer = null;
}

function scheduleJobPolling() {
    stopJobPolling();
    const active = state.jobs.some(job => ['queued', 'running'].includes(job.status));

    if (!active || state.user?.role !== 'superadmin') return;
    state.jobPollTimer = setTimeout(refreshProcessingState, 3000);
}

async function refreshProcessingState(reschedule = true) {
    try {
        const [metrics, documents, jobs] = await Promise.all([api('metrics'), api('documents'), api('jobs')]);
        renderMetrics(metrics.metrics);
        renderDocuments(documents.documents);
        renderJobs(jobs.jobs);
    } catch (_) {
        if (elements.workerMonitor) {
            elements.workerStateDetail.textContent = 'Não foi possível atualizar o progresso agora; uma nova tentativa será feita automaticamente.';
        }
    } finally {
        if (reschedule) scheduleJobPolling();
    }
}

async function drainQueueFromBrowser() {
    if (state.workerDrainActive) return;

    if (!state.jobs.some(job => job.status === 'queued')) {
        notify('Não há etapas pendentes na fila.');
        return;
    }

    if (!await confirmQueueProcessing()) {
        return;
    }

    state.workerDrainActive = true;
    stopJobPolling();
    renderWorkerMonitor(state.jobs);
    let processedRuns = 0;
    let failedRuns = 0;

    try {
        while (state.workerDrainActive && state.user?.role === 'superadmin') {
            const { worker } = await api('admin/queue/run', {
                method: 'POST',
                body: JSON.stringify({ confirm_live: true }),
            });
            const status = worker?.status || 'idle';

            if (status === 'idle') break;
            processedRuns++;
            if (status === 'failed') failedRuns++;
            await refreshProcessingState(false);
        }

        await refreshAll();
        notify(
            failedRuns > 0
                ? `Fila consumida com ${failedRuns} execução(ões) com falha.`
                : `Fila consumida em ${processedRuns} execução(ões).`,
            failedRuns > 0
        );
    } catch (error) {
        try { await refreshProcessingState(false); } catch (_) {}
        notify(error.message, true);
    } finally {
        state.workerDrainActive = false;
        renderWorkerMonitor(state.jobs);
        scheduleJobPolling();
    }
}

async function refreshUsers() {
    const { users } = await api('admin/users');
    renderUsers(users);
}

async function refreshProjectsAndScopes() {
    const [projects, scopes] = await Promise.all([api('admin/projects'), api('scopes')]);
    renderProjects(projects.projects);
    renderScopes(scopes.scopes);
}

async function loadBranding() {
    try {
        const { branding } = await api('branding');
        document.title = branding.name; document.querySelector('#brand-name').textContent = branding.name; document.querySelector('#brand-tagline').textContent = branding.tagline; document.querySelector('#brand-mark').textContent = branding.name.slice(0, 1).toUpperCase();
        document.documentElement.style.setProperty('--primary', branding.primary_color); document.documentElement.style.setProperty('--secondary', branding.secondary_color); document.documentElement.style.setProperty('--accent', branding.accent_color);
        const logo = document.querySelector('#brand-logo'), mark = document.querySelector('#brand-mark'), logoUrl = String(branding.logo_url || '').trim();
        logo.addEventListener('load', () => { logo.hidden = false; mark.hidden = true; }); logo.addEventListener('error', () => { logo.hidden = true; mark.hidden = false; }); logo.alt = branding.name;
        if (logoUrl) logo.src = logoUrl;
        else { logo.removeAttribute('src'); logo.hidden = true; mark.hidden = false; }
    } catch (error) { notify(error.message, true); }
}

function showSecret(code, owner = '') {
    state.secretOwner = owner;
    elements.recoverySecret.textContent = code;
    elements.secretDialog.hidden = false;
    document.body.classList.add('auth-locked');
    document.querySelector('#copy-secret').focus();
}

function closeSecret() { elements.recoverySecret.textContent = ''; elements.secretDialog.hidden = true; document.body.classList.toggle('auth-locked', !state.user); }

elements.accessForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    state.token = '';
    sessionStorage.removeItem('eva_access_token');

    try {
        const payload = await api('auth/login', { method: 'POST', body: JSON.stringify({ username: elements.accessUsername.value.trim(), password: elements.accessPassword.value }) });
        state.token = payload.token;
        sessionStorage.setItem('eva_access_token', state.token);
        await initializeSession();
        elements.accessForm.reset();
        notify('Sessão iniciada.');
    } catch (error) {
        disconnect(false);
        notify(error.message, true);
    } finally {
        button.disabled = false;
    }
});

elements.adminAccessForm.addEventListener('submit', async event => {
    event.preventDefault(); state.token = elements.accessToken.value.trim();
    if (state.token.length < 24) return notify('Informe um token administrativo válido.', true);
    try { await initializeSession(); elements.adminAccessForm.reset(); notify('Sessão de superadmin conectada.'); } catch (error) { state.token = ''; notify(error.message, true); }
});

document.querySelector('#show-recovery').addEventListener('click', () => { elements.accessForm.hidden = true; elements.recoverForm.hidden = false; document.querySelector('.admin-access').hidden = true; document.querySelector('#show-recovery').hidden = true; });
document.querySelector('#cancel-recovery').addEventListener('click', () => { elements.recoverForm.hidden = true; elements.accessForm.hidden = false; document.querySelector('.admin-access').hidden = false; document.querySelector('#show-recovery').hidden = false; });
elements.recoverForm.addEventListener('submit', async event => {
    event.preventDefault();
    try {
        const username = document.querySelector('#recover-username').value.trim();
        const result = await api('auth/recover', { method: 'POST', body: JSON.stringify({ username, recovery_code: document.querySelector('#recover-code').value, new_password: document.querySelector('#recover-password').value }) });
        elements.recoverForm.reset(); document.querySelector('#cancel-recovery').click(); showSecret(result.recovery_code, username); notify('Senha redefinida. Guarde o novo código.');
    } catch (error) { notify(error.message, true); }
});

document.querySelector('#disconnect-button').addEventListener('click', async () => { try { await api('logout', { method: 'POST' }); } catch (_) {} disconnect(); });
document.querySelector('#refresh-button').addEventListener('click', () => refreshAll().then(() => notify('Dados atualizados.')).catch(error => notify(error.message, true)));
elements.workerRunButtons.forEach(button => button.addEventListener('click', drainQueueFromBrowser));
elements.menuToggle.addEventListener('click', () => { const expanded = elements.menuToggle.getAttribute('aria-expanded') === 'true'; elements.menuToggle.setAttribute('aria-expanded', String(!expanded)); document.body.classList.toggle('menu-open', !expanded); });
document.addEventListener('click', event => {
    const link = event.target.closest('[data-view]');
    if (!link) return;
    event.preventDefault();
    switchView(link.dataset.view, link.dataset.moduleId || '');
});

elements.moduleDashboard.addEventListener('input', event => {
    if (event.target.matches('[data-module-content-filter]')) filterModuleEntries();
});
elements.moduleDashboard.addEventListener('submit', event => {
    const form = event.target.closest('[data-module-action-form]');
    if (!form) return;
    event.preventDefault();
    const actionId = event.submitter?.dataset.moduleAction || form.dataset.moduleActionForm || '';
    executeModuleAction(actionId, form, event.submitter).catch(error => notify(error.message, true));
});
elements.moduleDashboard.addEventListener('change', event => {
    if (!event.target.matches('[data-module-filter]')) return;
    state.moduleLocalFilter = '';
    loadModuleDashboard(state.activeModuleId).catch(error => notify(error.message, true));
});
elements.moduleDashboard.addEventListener('click', event => {
    const actionControl = event.target.closest('[data-module-action]');
    if (actionControl) {
        const form = actionControl.closest('[data-module-action-form]');

        if (!(form && actionControl.matches('button[type="submit"], input[type="submit"]'))) {
            event.preventDefault();
            executeModuleAction(actionControl.dataset.moduleAction || '', form, actionControl)
                .catch(error => notify(error.message, true));
        }
        return;
    }

    const refresh = event.target.closest('[data-module-refresh]');
    if (refresh) {
        loadModuleDashboard(state.activeModuleId).catch(error => notify(error.message, true));
        return;
    }

    const toggle = event.target.closest('[data-module-accordion-toggle]');
    if (!toggle) return;

    const body = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!body) return;
    const expand = toggle.getAttribute('aria-expanded') !== 'true';

    elements.moduleDashboard.querySelectorAll('[data-module-accordion-toggle][aria-expanded="true"]').forEach(openToggle => {
        openToggle.setAttribute('aria-expanded', 'false');
        const openBody = document.getElementById(openToggle.getAttribute('aria-controls'));
        if (openBody) openBody.hidden = true;
    });

    toggle.setAttribute('aria-expanded', String(expand));
    body.hidden = !expand;
});
elements.modulesList.addEventListener('click', async event => {
    const toggle = event.target.closest('[data-module-toggle]');
    const deleteButton = event.target.closest('[data-module-delete]');
    const button = toggle || deleteButton;
    if (!button) return;

    if (deleteButton && !await confirmTypedDeletion(
        'Módulo',
        deleteButton.dataset.moduleDelete,
        `Esta ação excluirá definitivamente ${deleteButton.dataset.moduleName}, todo o pacote em modules/${deleteButton.dataset.moduleDelete}/ e todo o histórico em modules/.runtime/data/${deleteButton.dataset.moduleDelete}/.`
    )) return;

    button.disabled = true;
    try {
        if (toggle) {
            await api(`admin/modules/${toggle.dataset.moduleToggle}`, {
                method: 'PATCH',
                body: JSON.stringify({ active: toggle.dataset.active === '1' }),
            });
            notify(toggle.dataset.active === '1' ? 'Módulo ativado.' : 'Módulo desativado.');
        } else if (deleteButton) {
            await api(`admin/modules/${deleteButton.dataset.moduleDelete}`, {
                method: 'DELETE',
                body: JSON.stringify({
                    confirm_module_id: deleteButton.dataset.moduleDelete,
                    delete_data: true,
                }),
            });
            notify('Módulo e todo o seu histórico foram excluídos definitivamente.');
        }

        await refreshModules();
        await refreshModuleInterfaces();
    } catch (error) {
        notify(error.message, true);
    } finally {
        button.disabled = false;
    }
});

document.querySelector('#upload-form').addEventListener('submit', async event => {
    event.preventDefault(); const form = event.currentTarget, button = form.querySelector('button[type="submit"]'), file = document.querySelector('#document-file').files[0];
    if (!file) return notify('Selecione um documento para anexar.', true);
    button.disabled = true; elements.uploadProgress.hidden = false; elements.uploadProgressBar.value = 0; elements.uploadProgressValue.textContent = '0%'; elements.uploadProgressLabel.textContent = `Enviando ${file.name}`; elements.uploadProgressMeta.textContent = formatBytes(file.size);
    try { await uploadDocument(new FormData(form), percentage => { elements.uploadProgressBar.value = percentage; elements.uploadProgressValue.textContent = `${percentage}%`; }); form.reset(); await refreshAll(); notify('Documento persistido com sucesso.'); }
    catch (error) { elements.uploadProgressLabel.textContent = 'Falha no envio.'; elements.uploadProgressMeta.textContent = error.message; notify(error.message, true); }
    finally { button.disabled = false; setTimeout(() => { elements.uploadProgress.hidden = true; }, 1600); }
});

elements.documentsBody.addEventListener('click', async event => {
    const processButton = event.target.closest('[data-process]');
    const deleteButton = event.target.closest('[data-delete-document]');

    if (processButton) {
        processButton.disabled = true;
        try { await api(`documents/${processButton.dataset.process}/process`, { method: 'POST' }); await refreshAll(); notify('Etapas cognitivas adicionadas à fila.'); }
        catch (error) { notify(error.message, true); }
        finally { processButton.disabled = false; }
        return;
    }

    if (!deleteButton) return;
    const documentId = Number(deleteButton.dataset.deleteDocument);
    const document = state.documents.find(item => Number(item.id) === documentId);
    if (!document || !await confirmTypedDeletion(
        'Documento',
        document.title,
        'Esta ação excluirá permanentemente a obra, seus nós, evidências, derivações, embeddings, filas e todas as permissões relacionadas.'
    )) return;

    deleteButton.disabled = true;

    try {
        const { deletion } = await api(`documents/${documentId}`, { method: 'DELETE' });
        state.documents = state.documents.filter(item => Number(item.id) !== documentId);
        renderDocuments(state.documents);
        notify(
            Number(deletion.storage_cleanup_failures || 0) > 0
                ? 'O documento foi excluído do banco, mas a fonte física exige limpeza manual.'
                : `Documento excluído com ${Number(deletion.evidences_deleted || 0)} evidência(s) relacionada(s).`,
            Number(deletion.storage_cleanup_failures || 0) > 0
        );

        try { await refreshAll(); }
        catch (refreshError) { notify('O documento foi excluído, mas o painel não pôde ser sincronizado.', true); }
    } catch (error) {
        notify(error.message, true);
    } finally {
        deleteButton.disabled = false;
    }
});
elements.jobsBody.addEventListener('click', async event => { const button = event.target.closest('[data-retry]'); if (!button) return; try { await api(`jobs/${button.dataset.retry}/retry`, { method: 'POST' }); await refreshAll(); notify('Trabalho devolvido à fila.'); } catch (error) { notify(error.message, true); } });

elements.queryScopeToggle.addEventListener('click', () => {
    setQueryScopePanel(elements.queryScopeToggle.getAttribute('aria-expanded') !== 'true');
});
elements.queryScopes.addEventListener('change', event => {
    const projectInput = event.target.closest('[data-query-project]');
    const documentInput = event.target.closest('[data-query-document]');

    if (projectInput) {
        projectInput.closest('[data-query-branch]').querySelectorAll('[data-query-document]').forEach(input => {
            input.checked = projectInput.checked || input.dataset.explicit === '1';
            input.disabled = projectInput.checked;
            input.closest('.tree-node').classList.toggle('is-inherited', projectInput.checked);
        });
    }

    if (documentInput) {
        const checked = documentInput.checked;
        elements.queryScopes.querySelectorAll(`[data-query-document][value="${documentInput.value}"]:not(:disabled)`).forEach(input => {
            input.checked = checked;
            input.dataset.explicit = checked ? '1' : '0';
        });
    }

    updateQueryScopeSummary();
});
document.addEventListener('click', event => {
    if (!elements.queryScopePanel.hidden && !event.target.closest('.composer-document')) setQueryScopePanel(false);
});
elements.restartChat.addEventListener('click', () => {
    restartChat();
    notify('Chat reiniciado.');
});

document.querySelector('#query-form').addEventListener('submit', async event => {
    event.preventDefault(); const scopes = selectedQueryScopes(), input = document.querySelector('#query-input').value.trim(), button = event.currentTarget.querySelector('button[type="submit"]');
    if (!scopes.length || !input) return notify('Selecione ao menos um projeto ou obra e informe a consulta.', true);
    button.disabled = true; elements.restartChat.disabled = true; renderConversation(input);
    try { const payload = await api('query', { method: 'POST', body: JSON.stringify({ scopes, current_input: input, input: buildConversationalInput(input) }) }); setQueryScopePanel(false); rememberConversationTurn(input, payload.query); renderConversation(); document.querySelector('#query-input').value = ''; document.querySelector('#query-input').focus(); }
    catch (error) { if (state.queryHistory.length) renderConversation(); else elements.queryResult.innerHTML = initialChatEmptyMarkup; notify(error.message, true); }
    finally { button.disabled = false; elements.restartChat.disabled = false; }
});

document.querySelector('#user-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    const username = document.querySelector('#new-username').value.trim();
    button.disabled = true;

    try {
        const result = await api('admin/users', {
            method: 'POST',
            body: JSON.stringify({
                username,
                password: document.querySelector('#new-user-password').value,
            }),
        });
        form.reset();
        showSecret(result.recovery_code, username);
        notify('Usuário cadastrado. Guarde o código de recuperação.');

        try {
            await refreshUsers();
        } catch (refreshError) {
            notify('O usuário foi cadastrado, mas a listagem não pôde ser atualizada. Recarregue a página depois de guardar o código.', true);
        }
    } catch (error) {
        const message = error.message.includes('já está cadastrado')
            ? 'Este usuário já está cadastrado. Use “Nova senha” na lista para gerar novas credenciais.'
            : error.message;
        notify(message, true);
    } finally {
        button.disabled = false;
    }
});

elements.usersBody.addEventListener('click', async event => {
    const permissionButton = event.target.closest('[data-permissions]');
    const toggleButton = event.target.closest('[data-toggle-user]');
    const resetButton = event.target.closest('[data-reset]');
    const renameButton = event.target.closest('[data-rename-user]');
    const deleteButton = event.target.closest('[data-delete-user]');
    if (permissionButton) {
        const user = state.users.find(item => item.id === Number(permissionButton.dataset.permissions)); if (!user) return;
        document.querySelector('#permission-user-id').value = user.id; document.querySelector('#permission-title').textContent = user.username;
        renderPermissionTree(user);
        elements.permissionForm.hidden = false; elements.permissionForm.scrollIntoView({ behavior: 'smooth' });
        return;
    }
    if (toggleButton) { try { await api(`admin/users/${toggleButton.dataset.toggleUser}`, { method: 'PATCH', body: JSON.stringify({ active: toggleButton.dataset.active === '1' }) }); await refreshUsers(); notify('Estado do usuário atualizado.'); } catch (error) { notify(error.message, true); } return; }
    if (resetButton) {
        const user = state.users.find(item => item.id === Number(resetButton.dataset.reset));
        if (user) openPasswordReset(user);
        return;
    }
    if (renameButton) {
        const user = state.users.find(item => item.id === Number(renameButton.dataset.renameUser));
        if (user) openUsernameRename(user);
        return;
    }
    if (deleteButton) {
        const userId = Number(deleteButton.dataset.deleteUser);
        const user = state.users.find(item => item.id === userId);
        if (!user || !await confirmTypedDeletion(
            'Usuário',
            user.username,
            'Esta ação excluirá permanentemente o cadastro, as sessões e as permissões do usuário. Projetos e obras não serão excluídos.'
        )) return;

        deleteButton.disabled = true;

        try {
            await api(`admin/users/${userId}`, {
                method: 'DELETE',
                body: JSON.stringify({ confirm_username: user.username }),
            });
            if (Number(document.querySelector('#permission-user-id').value) === userId) elements.permissionForm.hidden = true;
            state.users = state.users.filter(item => item.id !== userId);
            renderUsers(state.users);
            notify('Usuário excluído permanentemente.');

            try { await refreshUsers(); }
            catch (_) { notify('O usuário foi excluído, mas a listagem não pôde ser sincronizada.', true); }
        } catch (error) {
            notify(error.message, true);
        } finally {
            deleteButton.disabled = false;
        }
    }
});

document.querySelector('#permission-cancel').addEventListener('click', () => { elements.permissionForm.hidden = true; });
elements.permissionTree.addEventListener('change', event => {
    const projectInput = event.target.closest('[data-permission-project]');
    const documentInput = event.target.closest('[data-permission-document]');

    if (projectInput) {
        projectInput.closest('[data-permission-branch]').querySelectorAll('[data-permission-document]').forEach(input => {
            input.checked = projectInput.checked || input.dataset.explicit === '1';
            input.disabled = projectInput.checked;
            input.closest('.tree-node').classList.toggle('is-inherited', projectInput.checked);
            const detail = input.closest('.tree-node').querySelector('small');
            if (detail) detail.textContent = projectInput.checked ? 'Incluída pelo projeto' : input.dataset.publicId || detail.textContent;
        });
    }

    if (documentInput) {
        const checked = documentInput.checked;
        elements.permissionTree.querySelectorAll(`[data-permission-document][value="${documentInput.value}"]:not(:disabled)`).forEach(input => {
            input.checked = checked;
            input.dataset.explicit = checked ? '1' : '0';
        });
    }
});
elements.permissionForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    const userId = Number(document.querySelector('#permission-user-id').value);
    const projectIds = Array.from(elements.permissionTree.querySelectorAll('[data-permission-project]:checked')).map(input => Number(input.value));
    const coveredDocumentIds = new Set(
        state.projects
            .filter(project => projectIds.includes(Number(project.id)))
            .flatMap(project => project.document_ids.map(Number))
    );
    const documentIds = Array.from(new Set(
        Array.from(elements.permissionTree.querySelectorAll('[data-permission-document][data-explicit="1"]'))
            .map(input => Number(input.value))
            .filter(documentId => !coveredDocumentIds.has(documentId))
    ));
    button.disabled = true;

    try {
        await api(`admin/users/${userId}/permissions`, {
            method: 'PUT',
            body: JSON.stringify({ project_ids: projectIds, document_ids: documentIds }),
        });
        const user = state.users.find(item => item.id === userId);

        if (user) {
            user.project_ids = projectIds;
            user.document_ids = documentIds;
            renderUsers(state.users);
        }

        elements.permissionForm.hidden = true;
        notify('Permissões salvas.');

        try {
            await refreshUsers();
        } catch (refreshError) {
            notify('As permissões foram salvas, mas a listagem não pôde ser sincronizada.', true);
        }
    } catch (error) {
        notify(error.message, true);
    } finally {
        button.disabled = false;
    }
});

document.querySelector('#project-form').addEventListener('submit', async event => {
    event.preventDefault();
    const projectId = Number(document.querySelector('#project-id').value);
    const documentIds = Array.from(elements.projectDocuments.querySelectorAll('input:checked')).map(input => Number(input.value));
    const payload = {
        name: document.querySelector('#project-name').value.trim(),
        response_profile: elements.projectResponseProfile.value.trim(),
        document_ids: documentIds,
    };
    try { await api(projectId ? `admin/projects/${projectId}` : 'admin/projects', { method: projectId ? 'PUT' : 'POST', body: JSON.stringify(payload) }); resetProjectForm(); notify('Projeto salvo.'); await refreshProjectsAndScopes(); } catch (error) { notify(error.message, true); }
});

function resetProjectForm() {
    document.querySelector('#project-form').reset();
    document.querySelector('#project-id').value = '';
    document.querySelector('#project-cancel').hidden = true;
    renderProjectDocumentChoices(new Set());
}
document.querySelector('#project-cancel').addEventListener('click', resetProjectForm);
elements.projectsList.addEventListener('click', async event => {
    const editButton = event.target.closest('[data-edit-project]');
    const deleteButton = event.target.closest('[data-delete-project]');

    if (editButton) {
        const project = state.projects.find(item => item.id === Number(editButton.dataset.editProject));
        if (!project) return;
        document.querySelector('#project-id').value = project.id;
        document.querySelector('#project-name').value = project.name;
        elements.projectResponseProfile.value = project.response_profile || '';
        document.querySelector('#project-cancel').hidden = false;
        renderProjectDocumentChoices(new Set(project.document_ids.map(Number)));
        document.querySelector('#project-form').scrollIntoView({ behavior: 'smooth' });
        return;
    }

    if (!deleteButton) return;
    const projectId = Number(deleteButton.dataset.deleteProject);
    const project = state.projects.find(item => item.id === projectId);
    if (!project || !await confirmTypedDeletion(
        'Projeto',
        project.name,
        projectDeletionConsequence(project)
    )) return;

    deleteButton.disabled = true;

    try {
        const { deletion } = await api(`admin/projects/${projectId}`, { method: 'DELETE' });
        const deletedDocumentIds = new Set(project.document_ids.map(Number));
        state.projects = state.projects.filter(item => item.id !== projectId);
        state.documents = state.documents.filter(item => !deletedDocumentIds.has(Number(item.id)));
        renderProjects(state.projects);
        renderDocuments(state.documents);
        notify(
            Number(deletion.storage_cleanup_failures || 0) > 0
                ? `Projeto e ${Number(deletion.documents_deleted || 0)} obra(s) excluídos do banco; há fontes físicas para limpeza manual.`
                : `Projeto e ${Number(deletion.documents_deleted || 0)} obra(s) excluídos.`,
            Number(deletion.storage_cleanup_failures || 0) > 0
        );

        try { await refreshAll(); }
        catch (refreshError) { notify('O projeto foi excluído, mas o painel não pôde ser sincronizado.', true); }
    } catch (error) {
        notify(error.message, true);
    } finally {
        deleteButton.disabled = false;
    }
});

document.querySelector('#password-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;

    try {
        await api('me/password', {
            method: 'POST',
            body: JSON.stringify({
                current_password: document.querySelector('#current-password').value,
                new_password: document.querySelector('#changed-password').value,
            }),
        });
        form.reset();
        notify('Senha alterada.');
    } catch (error) {
        notify(error.message, true);
    }
});
document.querySelector('#recovery-code-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;

    try {
        const result = await api('me/recovery-code', {
            method: 'POST',
            body: JSON.stringify({
                current_password: document.querySelector('#recovery-current-password').value,
            }),
        });
        form.reset();
        showSecret(result.recovery_code, state.user.username);
    } catch (error) {
        notify(error.message, true);
    }
});

document.querySelector('#copy-secret').addEventListener('click', async () => { try { await navigator.clipboard.writeText(elements.recoverySecret.textContent); notify('Código copiado.'); } catch (_) { notify('Não foi possível copiar automaticamente.', true); } });
elements.queryResult.addEventListener('click', async event => {
    const button = event.target.closest('[data-copy-query]');
    const turn = button ? state.queryHistory[Number(button.dataset.copyQuery)] : null;
    if (!turn) return;
    button.disabled = true;

    try {
        await copyText(buildQueryCopyText(turn.user, turn.response, turn.result.evidences_used || []));
        const label = button.querySelector('span');
        if (label) label.textContent = 'Copiado';
        notify('Pergunta, resposta e evidências copiadas.');
        setTimeout(() => { if (label) label.textContent = 'Copiar pergunta e resposta'; }, 1800);
    } catch (error) {
        notify(error.message || 'Não foi possível copiar o conteúdo.', true);
    } finally {
        button.disabled = false;
    }
});
document.querySelector('#download-secret').addEventListener('click', () => {
    const content = `EVA — código de recuperação\nUsuário: ${state.secretOwner}\nCódigo: ${elements.recoverySecret.textContent}\n\nGuarde este arquivo em local seguro.`;
    const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `eva-recuperacao-${state.secretOwner || 'usuario'}.txt`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
});
document.querySelector('#close-secret').addEventListener('click', closeSecret);
elements.confirmationForm.addEventListener('submit', event => {
    event.preventDefault();

    if (elements.confirmationInput.value !== confirmTypedDeletion.expectedValue) {
        elements.confirmationError.textContent = 'O texto digitado não corresponde à confirmação solicitada.';
        elements.confirmationError.hidden = false;
        elements.confirmationInput.focus();
        return;
    }

    closeTypedDeletion(true);
});
document.querySelector('#confirmation-cancel').addEventListener('click', () => closeTypedDeletion(false));
elements.processingConfirmationForm.addEventListener('submit', event => {
    event.preventDefault();
    closeQueueProcessingConfirmation(true);
});
elements.processingConfirmationCancel.addEventListener('click', () => closeQueueProcessingConfirmation(false));
elements.passwordResetForm.addEventListener('submit', async event => {
    event.preventDefault();
    const user = openPasswordReset.user;
    const button = event.currentTarget.querySelector('button[type="submit"]');
    if (!user) return;
    button.disabled = true;

    try {
        const result = await api(`admin/users/${user.id}/reset-password`, {
            method: 'POST',
            body: JSON.stringify({ password: elements.adminResetPassword.value }),
        });
        closePasswordReset();
        showSecret(result.recovery_code, user.username);
        notify('Senha redefinida e sessões anteriores encerradas.');
    } catch (error) {
        notify(error.message, true);
    } finally {
        button.disabled = false;
    }
});
document.querySelector('#password-reset-cancel').addEventListener('click', closePasswordReset);
elements.usernameRenameForm.addEventListener('submit', async event => {
    event.preventDefault();
    const user = openUsernameRename.user;
    const button = event.currentTarget.querySelector('button[type="submit"]');
    if (!user) return;
    button.disabled = true;

    try {
        const result = await api(`admin/users/${user.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ username: elements.renamedUsername.value.trim() }),
        });
        user.username = result.user.username;
        if (Number(document.querySelector('#permission-user-id').value) === user.id) {
            document.querySelector('#permission-title').textContent = user.username;
        }
        renderUsers(state.users);
        closeUsernameRename();
        notify('Username alterado; ID e permissões foram preservados.');

        try { await refreshUsers(); }
        catch (_) { notify('O username foi alterado, mas a listagem não pôde ser sincronizada.', true); }
    } catch (error) {
        notify(error.message, true);
    } finally {
        button.disabled = false;
    }
});
document.querySelector('#username-rename-cancel').addEventListener('click', closeUsernameRename);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !elements.queryScopePanel.hidden) {
        setQueryScopePanel(false);
        elements.queryScopeToggle.focus();
        return;
    }

    if (event.key === 'Escape' && !elements.confirmationDialog.hidden) {
        event.preventDefault();
        closeTypedDeletion(false);
        return;
    }

    if (event.key === 'Escape' && !elements.processingConfirmationDialog.hidden) {
        event.preventDefault();
        closeQueueProcessingConfirmation(false);
        return;
    }

    if (event.key === 'Escape' && !elements.passwordResetDialog.hidden) {
        event.preventDefault();
        closePasswordReset();
        return;
    }

    if (event.key === 'Escape' && !elements.usernameRenameDialog.hidden) {
        event.preventDefault();
        closeUsernameRename();
    }
});

loadBranding();
if (state.token) initializeSession().catch(() => disconnect(false)); else setConnected(false);
