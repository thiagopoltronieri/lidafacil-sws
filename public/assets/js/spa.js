'use strict';

// console spa do Lida Fácil: consome a api rest com jwt no header Authorization e adapta a interface ao perfil
(function () {
    const API_BASE = '/api/v1';
    const STORAGE_KEY = 'lidafacil.tokens';

    const state = {
        access: null,
        refresh: null,
        me: null,
    };

    const ROLE_LABEL = { admin: 'Administrador', operator: 'Operador', client: 'Cliente' };
    const ROLE_OPTIONS = [
        { value: 'admin', label: 'Administrador' },
        { value: 'operator', label: 'Operador' },
        { value: 'client', label: 'Cliente' },
    ];
    const SPECIES_OPTIONS = [
        { value: 'bovino', label: 'Bovino' },
        { value: 'suino', label: 'Suíno' },
        { value: 'ovino', label: 'Ovino' },
    ];
    const SEX_OPTIONS = [
        { value: 'macho', label: 'Macho' },
        { value: 'femea', label: 'Fêmea' },
    ];

    const el = (id) => document.getElementById(id);

    // armazenamento dos tokens por-viewer; embrulhado em try/catch por causa de janelas privadas
    function saveTokens() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ access: state.access, refresh: state.refresh }));
        } catch (e) {
            // sem persistência: a sessão vale só enquanto a aba estiver aberta
        }
    }

    function loadTokens() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            const parsed = JSON.parse(raw);
            state.access = parsed.access || null;
            state.refresh = parsed.refresh || null;
        } catch (e) {
            state.access = null;
            state.refresh = null;
        }
    }

    function clearSession() {
        state.access = null;
        state.refresh = null;
        state.me = null;
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignora falha de storage
        }
    }

    // camada de acesso à api
    async function parseBody(response) {
        if (response.status === 204) {
            return null;
        }
        try {
            return await response.json();
        } catch (e) {
            return null;
        }
    }

    async function tryRefresh() {
        if (!state.refresh) {
            return false;
        }
        const response = await fetch(API_BASE + '/auth/refresh', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: state.refresh }),
        });
        if (!response.ok) {
            return false;
        }
        const data = await parseBody(response);
        state.access = data.access_token;
        state.refresh = data.refresh_token;
        saveTokens();
        return true;
    }

    async function api(method, path, body) {
        const build = () => {
            const opts = { method, headers: {} };
            if (state.access) {
                opts.headers['Authorization'] = 'Bearer ' + state.access;
            }
            if (body !== undefined) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
            return opts;
        };

        let response = await fetch(API_BASE + path, build());

        // renova uma única vez quando o access token expira, de forma transparenet
        const renewable = path !== '/auth/login' && path !== '/auth/refresh';
        if (response.status === 401 && renewable && (await tryRefresh())) {
            response = await fetch(API_BASE + path, build());
        }

        const data = await parseBody(response);
        recordResponse(method, path, response.status, data);
        return { status: response.status, ok: response.ok, data };
    }

    // painel da última resposta
    function recordResponse(method, path, status, data) {
        el('response-meta').textContent = method + ' ' + API_BASE + path + ' -> ' + status;
        el('response-body').textContent = data === null ? '(sem corpo)' : JSON.stringify(data, null, 2);
    }

    // toast simples de feedback
    let toastTimer = null;
    function toast(message) {
        const node = el('toast');
        node.textContent = message;
        node.hidden = false;
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = setTimeout(() => { node.hidden = true; }, 3200);
    }

    // troca de tela
    function showLogin() {
        el('view-login').hidden = false;
        el('view-app').hidden = true;
    }

    function showApp() {
        el('view-login').hidden = true;
        el('view-app').hidden = false;
    }

    // helpers de dom textContent evita injeção de html a partir de dados da api
    function node(tag, className, text) {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function tableFrom(headers) {
        const table = node('table', 'data-table');
        const thead = node('thead');
        const tr = node('tr');
        headers.forEach((h) => tr.appendChild(node('th', null, h)));
        thead.appendChild(tr);
        table.appendChild(thead);
        table.appendChild(node('tbody'));
        return table;
    }

    // perfil atual
    function role() {
        return state.me ? state.me.role : 'client';
    }

    function applyRoleToChrome() {
        el('current-name').textContent = state.me ? state.me.name : '';
        const badge = el('current-role');
        badge.textContent = ROLE_LABEL[role()] || role();
        badge.className = 'badge role-' + role();

        // cliente não gere rebanho: some com a aba de animais
        el('tab-animals-btn').hidden = role() === 'client';
        // só o admin cria e exclui usuários
        el('new-user-btn').hidden = role() !== 'admin';
    }

    // users
    async function renderUsers() {
        const list = el('users-list');
        list.textContent = '';

        if (role() === 'client') {
            // cliente enxerga apenas o próprio cadastro
            el('users-title').textContent = 'Meu perfil';
            const card = node('div', 'card glass');
            card.appendChild(renderUserLine(state.me));
            list.appendChild(card);
            return;
        }

        el('users-title').textContent = 'Usuários';
        const result = await api('GET', '/users');
        if (!result.ok) {
            list.appendChild(node('p', 'empty', 'Não foi possível carregar os usuários.'));
            return;
        }

        const table = tableFrom(['Nome', 'E-mail', 'Perfil', 'Ações']);
        const tbody = table.querySelector('tbody');
        result.data.data.forEach((user) => {
            const tr = node('tr');
            tr.appendChild(node('td', null, user.name));
            tr.appendChild(node('td', null, user.email));
            const perfil = node('td');
            perfil.appendChild(node('span', 'badge role-' + user.role, ROLE_LABEL[user.role] || user.role));
            tr.appendChild(perfil);
            tr.appendChild(userActions(user));
            tbody.appendChild(tr);
        });
        const card = node('div', 'card glass');
        card.appendChild(table);
        list.appendChild(card);
    }

    function renderUserLine(user) {
        const wrap = node('div', 'profile-line');
        wrap.appendChild(node('p', null, user.name));
        wrap.appendChild(node('p', 'muted', user.email + ' - ' + (ROLE_LABEL[user.role] || user.role)));
        const edit = node('button', 'btn btn-ghost btn-sm', 'Editar meus dados');
        edit.type = 'button';
        edit.addEventListener('click', () => openUserModal(user));
        wrap.appendChild(edit);
        return wrap;
    }

    function userActions(user) {
        const td = node('td');
        const actions = node('div', 'row-actions');

        const edit = node('button', 'btn btn-ghost btn-sm', 'Editar');
        edit.type = 'button';
        edit.addEventListener('click', () => openUserModal(user));
        actions.appendChild(edit);

        if (role() === 'admin') {
            const del = node('button', 'btn btn-danger btn-sm', 'Excluir');
            del.type = 'button';
            del.addEventListener('click', () => removeUser(user));
            actions.appendChild(del);
        }

        td.appendChild(actions);
        return td;
    }

    async function removeUser(user) {
        if (!window.confirm('Excluir o usuário ' + user.email + '?')) {
            return;
        }
        const result = await api('DELETE', '/users/' + user.id);
        if (result.status === 204) {
            toast('Usuário excluído.');
            await renderUsers();
            focusSection('users');
        } else {
            toast(messageOf(result, 'Não foi possível excluir.'));
        }
    }

    function openUserModal(user) {
        const editing = Boolean(user);
        const canAssignRole = role() === 'admin';
        const fields = [
            { name: 'name', label: 'Nome', type: 'text', value: user ? user.name : '', required: true },
            { name: 'email', label: 'E-mail', type: 'email', value: user ? user.email : '', required: true },
            {
                name: 'password',
                label: editing ? 'Nova senha (deixe em branco para manter)' : 'Senha',
                type: 'password',
                value: '',
                required: !editing,
            },
        ];
        if (canAssignRole) {
            fields.push({ name: 'role', label: 'Perfil', type: 'select', options: ROLE_OPTIONS, value: user ? user.role : 'client' });
        }

        openModal(editing ? 'Editar usuário' : 'Novo usuário', fields, async (values) => {
            const payload = { name: values.name, email: values.email };
            if (values.password) {
                payload.password = values.password;
            }
            if (canAssignRole) {
                payload.role = values.role;
            }
            const result = editing
                ? await api('PUT', '/users/' + user.id, payload)
                : await api('POST', '/users', payload);

            if (result.ok) {
                toast(editing ? 'Usuário atualizado.' : 'Usuário criado.');
                closeModal();
                if (editing && state.me && user.id === state.me.id) {
                    await loadMe();
                }
                await renderUsers();
                focusSection('users');
            } else {
                showModalError(result);
            }
        });
    }

    // animais
    async function renderAnimals() {
        const list = el('animals-list');
        list.textContent = '';
        const result = await api('GET', '/animals');
        if (!result.ok) {
            list.appendChild(node('p', 'empty', 'Não foi possível carregar o rebanho.'));
            return;
        }
        if (result.data.data.length === 0) {
            list.appendChild(node('p', 'empty', 'Nenhum animal cadastrado ainda.'));
            return;
        }

        const table = tableFrom(['Brinco', 'Espécie', 'Sexo', 'Peso (kg)', 'Ações']);
        const tbody = table.querySelector('tbody');
        result.data.data.forEach((animal) => {
            const tr = node('tr');
            tr.appendChild(node('td', null, animal.tag));
            tr.appendChild(node('td', null, labelOf(SPECIES_OPTIONS, animal.species)));
            tr.appendChild(node('td', null, labelOf(SEX_OPTIONS, animal.sex)));
            tr.appendChild(node('td', null, String(animal.weight_kg)));

            const td = node('td');
            const actions = node('div', 'row-actions');
            const edit = node('button', 'btn btn-ghost btn-sm', 'Editar');
            edit.type = 'button';
            edit.addEventListener('click', () => openAnimalModal(animal));
            const del = node('button', 'btn btn-danger btn-sm', 'Excluir');
            del.type = 'button';
            del.addEventListener('click', () => removeAnimal(animal));
            actions.appendChild(edit);
            actions.appendChild(del);
            td.appendChild(actions);
            tr.appendChild(td);
            tbody.appendChild(tr);
        });
        const card = node('div', 'card glass');
        card.appendChild(table);
        list.appendChild(card);
    }

    async function removeAnimal(animal) {
        if (!window.confirm('Excluir o animal ' + animal.tag + '?')) {
            return;
        }
        const result = await api('DELETE', '/animals/' + animal.id);
        if (result.status === 204) {
            toast('Animal excluído.');
            await renderAnimals();
            focusSection('animals');
        } else {
            toast(messageOf(result, 'Não foi possível excluir.'));
        }
    }

    function openAnimalModal(animal) {
        const editing = Boolean(animal);
        const fields = [
            { name: 'tag', label: 'Brinco', type: 'text', value: animal ? animal.tag : '', required: true },
            { name: 'species', label: 'Espécie', type: 'select', options: SPECIES_OPTIONS, value: animal ? animal.species : 'bovino' },
            { name: 'sex', label: 'Sexo', type: 'select', options: SEX_OPTIONS, value: animal ? animal.sex : 'femea' },
            { name: 'weight_kg', label: 'Peso (kg)', type: 'number', value: animal ? String(animal.weight_kg) : '', required: true },
            { name: 'birth_date', label: 'Nascimento', type: 'date', value: animal && animal.birth_date ? animal.birth_date : '' },
            { name: 'notes', label: 'Observações', type: 'textarea', value: animal && animal.notes ? animal.notes : '' },
        ];

        openModal(editing ? 'Editar animal' : 'Novo animal', fields, async (values) => {
            const result = editing
                ? await api('PUT', '/animals/' + animal.id, values)
                : await api('POST', '/animals', values);
            if (result.ok) {
                toast(editing ? 'Animal atualizado.' : 'Animal cadastrado.');
                closeModal();
                await renderAnimals();
                focusSection('animals');
            } else {
                showModalError(result);
            }
        });
    }

    function labelOf(options, value) {
        const found = options.find((o) => o.value === value);
        return found ? found.label : value;
    }

    function messageOf(result, fallback) {
        return result.data && result.data.error && result.data.error.message ? result.data.error.message : fallback;
    }

    // modal com foco preso e retorno de foco visando a acessibilidade
    let modalSubmit = null;
    let lastFocused = null;

    function openModal(title, fields, onSubmit) {
        lastFocused = document.activeElement;
        el('modal-title').textContent = title;
        el('modal-error').hidden = true;

        const container = el('modal-fields');
        container.textContent = '';
        fields.forEach((field) => container.appendChild(buildField(field)));

        modalSubmit = onSubmit;
        el('modal').hidden = false;

        const first = container.querySelector('input, select, textarea');
        if (first) {
            first.focus();
        }
    }

    function closeModal() {
        el('modal').hidden = true;
        modalSubmit = null;
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function buildField(field) {
        const wrap = node('div', 'field' + (field.type === 'textarea' ? ' full' : ''));
        const id = 'f-' + field.name;
        const label = node('label', null, field.label);
        label.setAttribute('for', id);
        wrap.appendChild(label);

        let input;
        if (field.type === 'select') {
            input = node('select');
            field.options.forEach((opt) => {
                const option = node('option', null, opt.label);
                option.value = opt.value;
                if (opt.value === field.value) {
                    option.selected = true;
                }
                input.appendChild(option);
            });
        } else if (field.type === 'textarea') {
            input = node('textarea');
            input.value = field.value || '';
        } else {
            input = node('input');
            input.type = field.type;
            input.value = field.value || '';
        }
        input.id = id;
        input.name = field.name;
        if (field.required) {
            input.required = true;
        }
        wrap.appendChild(input);
        return wrap;
    }

    function collectModal() {
        const values = {};
        el('modal-fields').querySelectorAll('input, select, textarea').forEach((input) => {
            values[input.name] = input.value;
        });
        return values;
    }

    function clearFieldErrors() {
        el('modal-fields').querySelectorAll('[aria-invalid="true"]').forEach((input) => {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('aria-describedby');
        });
        el('modal-fields').querySelectorAll('.field-error').forEach((elem) => elem.remove());
    }

    function showModalError(result) {
        clearFieldErrors();
        const box = el('modal-error');
        const fields = result.data && result.data.error ? result.data.error.fields : null;

        if (fields) {
            // liga cada mensagem ao seu campo por aria-invalid e aria-describedby, para o leitor de tela apontar o erro certo
            Object.keys(fields).forEach((key) => {
                const input = el('f-' + key);
                if (!input) {
                    return;
                }
                input.setAttribute('aria-invalid', 'true');
                const hint = node('p', 'error-text field-error', fields[key]);
                hint.id = 'err-' + key;
                input.setAttribute('aria-describedby', hint.id);
                if (input.parentElement) {
                    input.parentElement.appendChild(hint);
                }
            });
            box.textContent = 'Confira os campos destacados.';
        } else {
            box.textContent = messageOf(result, 'Não foi possível salvar.');
        }
        box.hidden = false;
    }

    function trapFocus(event) {
        if (el('modal').hidden) {
            return;
        }
        if (event.key === 'Escape') {
            closeModal();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = el('modal').querySelectorAll('input, select, textarea, button');
        if (focusable.length === 0) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    // autenticação
    async function doLogin(email, password) {
        const result = await api('POST', '/auth/login', { email, password });
        if (!result.ok) {
            return false;
        }
        state.access = result.data.access_token;
        state.refresh = result.data.refresh_token;
        state.me = result.data.user;
        saveTokens();
        return true;
    }

    async function loadMe() {
        const result = await api('GET', '/users/me');
        if (result.ok) {
            state.me = result.data.data;
            return true;
        }
        return false;
    }

    async function enterApp() {
        applyRoleToChrome();
        showApp();
        switchTab('users');
    }

    function logout() {
        const token = state.refresh;
        if (token) {
            // revoga o refresh token no servidor; não precisa aguardar a resposta
            api('POST', '/auth/logout', { refresh_token: token });
        }
        clearSession();
        showLogin();
    }

    // abas
    function switchTab(tab) {
        const isUsers = tab === 'users';
        el('tab-panel-users').hidden = !isUsers;
        el('tab-panel-animals').hidden = isUsers;
        document.querySelectorAll('.tab-btn').forEach((btn) => {
            btn.setAttribute('aria-current', btn.dataset.tab === tab ? 'page' : 'false');
        });
        if (isUsers) {
            renderUsers();
        } else {
            renderAnimals();
        }
    }

    // devolve o foco a um alvo estável após um re-render; o botão de linha some, então focamos o título da seção
    function focusSection(tab) {
        const heading = el(tab === 'users' ? 'users-title' : 'animals-title');
        if (heading) {
            heading.focus();
        }
    }

    // ligação dos eventos
    function wireEvents() {
        el('login-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const email = el('login-email').value.trim();
            const password = el('login-password').value;
            const errorBox = el('login-error');
            errorBox.hidden = true;
            const ok = await doLogin(email, password);
            if (ok) {
                el('login-form').reset();
                enterApp();
            } else {
                errorBox.textContent = 'Credenciais inválidas. Confira o e-mail e a senha.';
                errorBox.hidden = false;
            }
        });

        el('logout-btn').addEventListener('click', logout);
        el('new-user-btn').addEventListener('click', () => openUserModal(null));
        el('new-animal-btn').addEventListener('click', () => openAnimalModal(null));
        el('modal-cancel').addEventListener('click', closeModal);
        el('modal').addEventListener('click', (event) => {
            if (event.target === el('modal')) {
                closeModal();
            }
        });
        document.addEventListener('keydown', trapFocus);

        el('modal-form').addEventListener('submit', (event) => {
            event.preventDefault();
            if (modalSubmit) {
                modalSubmit(collectModal());
            }
        });

        document.querySelectorAll('.tab-btn').forEach((btn) => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });
    }

    // inicialização: reidrata a sessão se houver token válido
    async function init() {
        wireEvents();
        loadTokens();
        if (state.access && (await loadMe())) {
            enterApp();
        } else {
            clearSession();
            showLogin();
        }
    }

    init();
})();
