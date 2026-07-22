'use strict';

const state = {
    token: sessionStorage.getItem('eva_access_token') || sessionStorage.getItem('eva_admin_token') || '',
    user: null,
    documents: [],
    jobs: [],
    users: [],
    projects: [],
    scopes: { projects: [], documents: [] },
    queryHistory: [],
    secretOwner: '',
    jobPollTimer: null,
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
    usersBody: document.querySelector('#users-body'), userCount: document.querySelector('#user-count'), projectsList: document.querySelector('#projects-list'),
    permissionForm: document.querySelector('#permission-form'), permissionTree: document.querySelector('#permission-tree'),
    projectDocuments: document.querySelector('#project-documents'), menuToggle: document.querySelector('#menu-toggle'), navigation: document.querySelector('#top-navigation'),
    uploadProgress: document.querySelector('#upload-progress'), uploadProgressBar: document.querySelector('#upload-progress-bar'), uploadProgressLabel: document.querySelector('#upload-progress-label'),
    uploadProgressValue: document.querySelector('#upload-progress-value'), uploadProgressMeta: document.querySelector('#upload-progress-meta'), toast: document.querySelector('#toast'),
    secretDialog: document.querySelector('#secret-dialog'), recoverySecret: document.querySelector('#recovery-secret'),
    confirmationDialog: document.querySelector('#confirmation-dialog'), confirmationForm: document.querySelector('#confirmation-form'),
    confirmationTitle: document.querySelector('#confirmation-title'), confirmationConsequence: document.querySelector('#confirmation-consequence'),
    confirmationTarget: document.querySelector('#confirmation-target'), confirmationInput: document.querySelector('#confirmation-input'),
    confirmationError: document.querySelector('#confirmation-error'),
    passwordResetDialog: document.querySelector('#password-reset-dialog'), passwordResetForm: document.querySelector('#admin-password-reset-form'),
    passwordResetUsername: document.querySelector('#password-reset-username'), adminResetPassword: document.querySelector('#admin-reset-password'),
};

const initialChatEmptyMarkup = elements.queryResult.innerHTML;
const maxQueryPayloadLength = 20_000;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
}

function total(group) { return Object.values(group || {}).reduce((sum, value) => sum + Number(value || 0), 0); }
function apiPath(path) { return `api/${String(path).replace(/^\/+/, '')}`; }

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    const response = await fetch(apiPath(path), { ...options, headers });
    const payload = await response.json().catch(() => ({
        error: `O servidor retornou uma resposta não reconhecida (HTTP ${response.status}).`,
    }));
    if (!response.ok) {
        if (response.status === 401 && !String(path).startsWith('auth/')) disconnect(false);
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
    elements.accessPanel.hidden = connected;
    elements.accessPanel.setAttribute('aria-hidden', String(connected));
    elements.topbar.inert = !connected;
    elements.workspace.inert = !connected;
    document.body.classList.toggle('auth-locked', !connected);
    elements.connection.dataset.state = connected ? 'online' : 'offline';
    elements.connectionLabel.textContent = connected ? 'Sessão ativa' : 'Desconectado';
    elements.sessionUser.textContent = connected && state.user ? state.user.username : '';
    if (!connected) setTimeout(() => elements.accessUsername.focus(), 0);
}

function applyRole() {
    const admin = state.user?.role === 'superadmin';
    document.querySelectorAll('[data-admin-only]').forEach(node => {
        if (node.matches('a')) node.hidden = !admin;
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

function switchView(view) {
    const admin = state.user?.role === 'superadmin';
    if (!admin && !['query', 'settings'].includes(view)) view = 'query';
    document.querySelectorAll('[data-view-panel]').forEach(panel => {
        const allowed = admin ? !panel.hasAttribute('data-user-only') : !panel.hasAttribute('data-admin-only');
        const active = allowed && panel.dataset.viewPanel === view;
        panel.hidden = !active;
        panel.classList.toggle('active', active);
    });
    document.querySelectorAll('[data-view]').forEach(link => link.classList.toggle('active', link.dataset.view === view));
    document.body.classList.remove('menu-open');
    elements.menuToggle.setAttribute('aria-expanded', 'false');
    location.hash = view;
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

    if (running.length) {
        elements.workerMonitor.dataset.state = 'running';
        elements.workerStateLabel.textContent = `Worker ativo: ${running.length} etapa(s) em processamento.`;
        elements.workerStateDetail.textContent = 'O progresso é atualizado automaticamente a cada 3 segundos.';
        return;
    }

    if (queued.length) {
        elements.workerMonitor.dataset.state = 'waiting';
        elements.workerStateLabel.textContent = `Worker inativo: ${queued.length} etapa(s) aguardando execução.`;
        elements.workerStateDetail.textContent = 'A fila não avança sozinha. Execute o comando exibido ao lado no terminal do projeto.';
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

function renderAudit(events) {
    elements.auditBody.innerHTML = events.length ? events.map(event => `<tr><td><strong>${escapeHtml(event.event_type)}</strong></td><td>${escapeHtml(event.entity_type || '—')}</td><td>${escapeHtml(event.entity_id || '—')}</td><td>${escapeHtml(event.created_at)}</td><td><small>${escapeHtml(JSON.stringify(event.metadata || {}))}</small></td></tr>`).join('') : '<tr><td colspan="5" class="empty">Nenhum evento registrado.</td></tr>';
}

function renderUsers(users) {
    state.users = users;
    elements.userCount.textContent = `${users.length} usuário${users.length === 1 ? '' : 's'}`;
    elements.usersBody.innerHTML = users.length ? users.map(user => `<tr><td><strong>${escapeHtml(user.username)}</strong></td><td><span class="status ${user.active ? 'status-ready' : 'status-failed'}">${user.active ? 'ativo' : 'inativo'}</span></td><td>${escapeHtml(user.last_login_at || 'Nunca')}</td><td>${user.project_ids.length} projeto(s) · ${user.document_ids.length} obra(s)</td><td class="table-actions"><button class="button button-primary button-small" type="button" data-permissions="${user.id}">Permissões</button><button class="button button-quiet button-small" type="button" data-reset="${user.id}">Nova senha</button><button class="button button-quiet button-small" type="button" data-toggle-user="${user.id}" data-active="${user.active ? '0' : '1'}">${user.active ? 'Desativar' : 'Ativar'}</button></td></tr>`).join('') : '<tr><td colspan="5" class="empty">Nenhum usuário cadastrado.</td></tr>';
}

function renderProjects(projects) {
    state.projects = projects;
    elements.projectsList.innerHTML = projects.length ? projects.map(project => `<article class="card project-card"><p class="eyebrow">Projeto</p><h2>${escapeHtml(project.name)}</h2><p>${project.documents.length} obra${project.documents.length === 1 ? '' : 's'}</p><ul>${project.documents.map(document => `<li>${escapeHtml(document.title)}</li>`).join('')}</ul><div class="project-actions"><button class="button button-quiet" type="button" data-edit-project="${project.id}">Editar projeto</button><button class="button button-danger" type="button" data-delete-project="${project.id}">Excluir projeto e obras</button></div></article>`).join('') : '<div class="card empty">Nenhum projeto cadastrado.</div>';
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
    const evidences = result.evidences_used || [], simetry = result.simetry_interactions || [], assimetry = result.assimetry_interactions || [], limitations = result.limitations || [];
    const technicalDetails = state.user?.role === 'superadmin'
        ? `<div class="result-section"><h2>Interações simetry</h2>${renderList(simetry, item => item.summary)}</div><div class="result-section"><h2>Interações assimetry</h2>${renderList(assimetry, item => item.summary)}</div><div class="result-section"><h2>Limitações</h2>${renderList(limitations, item => item)}</div>`
        : '';

    return `<section class="chat-turn"><article class="chat-message-user"><p class="eyebrow">Você</p><p>${escapeHtml(question)}</p></article><article class="card chat-message-assistant"><p class="eyebrow">Resposta documental</p><div class="answer">${escapeHtml(result.answer || '')}</div><div class="result-section"><h2>Evidências utilizadas</h2>${renderEvidenceList(evidences)}</div><div class="copy-result-action"><button type="button" class="button button-quiet button-copy-result" data-copy-query="${index}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg><span>Copiar pergunta e resposta</span></button></div>${technicalDetails}</article></section>`;
}

function renderConversation(pendingQuestion = '') {
    const completed = state.queryHistory.map((turn, index) => renderQuery(turn.result, turn.user, index)).join('');
    const pending = pendingQuestion
        ? `<section class="chat-turn"><article class="chat-message-user"><p class="eyebrow">Você</p><p>${escapeHtml(pendingQuestion)}</p></article><article class="card chat-message-assistant chat-message-pending"><p>Consultando evidências…</p></article></section>`
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
        const [metrics, documents, jobs, audit, users, projects, scopes] = await Promise.all([api('metrics'), api('documents'), api('jobs'), api('audit'), api('admin/users'), api('admin/projects'), api('scopes')]);
        renderMetrics(metrics.metrics); renderDocuments(documents.documents); renderJobs(jobs.jobs); renderAudit(audit.events); renderUsers(users.users); renderProjects(projects.projects); renderScopes(scopes.scopes);
        scheduleJobPolling();
    } else {
        const { scopes } = await api('scopes');
        renderScopes(scopes);
    }
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

async function refreshProcessingState() {
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
    try {
        const payload = await api('auth/login', { method: 'POST', body: JSON.stringify({ username: elements.accessUsername.value.trim(), password: elements.accessPassword.value }) });
        state.token = payload.token; await initializeSession(); elements.accessForm.reset(); notify('Sessão iniciada.');
    } catch (error) { state.token = ''; notify(error.message, true); }
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
elements.menuToggle.addEventListener('click', () => { const expanded = elements.menuToggle.getAttribute('aria-expanded') === 'true'; elements.menuToggle.setAttribute('aria-expanded', String(!expanded)); document.body.classList.toggle('menu-open', !expanded); });
document.querySelectorAll('[data-view]').forEach(link => link.addEventListener('click', event => { event.preventDefault(); switchView(link.dataset.view); }));

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
    try { const payload = await api('query', { method: 'POST', body: JSON.stringify({ scopes, input: buildConversationalInput(input) }) }); setQueryScopePanel(false); rememberConversationTurn(input, payload.query); renderConversation(); document.querySelector('#query-input').value = ''; document.querySelector('#query-input').focus(); }
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
    const permissionButton = event.target.closest('[data-permissions]'), toggleButton = event.target.closest('[data-toggle-user]'), resetButton = event.target.closest('[data-reset]');
    if (permissionButton) {
        const user = state.users.find(item => item.id === Number(permissionButton.dataset.permissions)); if (!user) return;
        document.querySelector('#permission-user-id').value = user.id; document.querySelector('#permission-title').textContent = user.username;
        renderPermissionTree(user);
        elements.permissionForm.hidden = false; elements.permissionForm.scrollIntoView({ behavior: 'smooth' });
    }
    if (toggleButton) { try { await api(`admin/users/${toggleButton.dataset.toggleUser}`, { method: 'PATCH', body: JSON.stringify({ active: toggleButton.dataset.active === '1' }) }); await refreshUsers(); notify('Estado do usuário atualizado.'); } catch (error) { notify(error.message, true); } }
    if (resetButton) {
        const user = state.users.find(item => item.id === Number(resetButton.dataset.reset));
        if (user) openPasswordReset(user);
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
    event.preventDefault(); const projectId = Number(document.querySelector('#project-id').value), documentIds = Array.from(elements.projectDocuments.querySelectorAll('input:checked')).map(input => Number(input.value)), payload = { name: document.querySelector('#project-name').value.trim(), document_ids: documentIds };
    try { await api(projectId ? `admin/projects/${projectId}` : 'admin/projects', { method: projectId ? 'PUT' : 'POST', body: JSON.stringify(payload) }); resetProjectForm(); notify('Projeto salvo.'); await refreshProjectsAndScopes(); } catch (error) { notify(error.message, true); }
});

function resetProjectForm() { document.querySelector('#project-form').reset(); document.querySelector('#project-id').value = ''; document.querySelector('#project-cancel').hidden = true; renderProjectDocumentChoices(new Set()); }
document.querySelector('#project-cancel').addEventListener('click', resetProjectForm);
elements.projectsList.addEventListener('click', async event => {
    const editButton = event.target.closest('[data-edit-project]');
    const deleteButton = event.target.closest('[data-delete-project]');

    if (editButton) {
        const project = state.projects.find(item => item.id === Number(editButton.dataset.editProject));
        if (!project) return;
        document.querySelector('#project-id').value = project.id;
        document.querySelector('#project-name').value = project.name;
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

    if (event.key === 'Escape' && !elements.passwordResetDialog.hidden) {
        event.preventDefault();
        closePasswordReset();
    }
});

loadBranding();
if (state.token) initializeSession().catch(() => disconnect(false)); else setConnected(false);
