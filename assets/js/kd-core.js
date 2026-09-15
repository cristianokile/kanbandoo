/**
 * KanbanDoo - Núcleo compartilhado
 * Utilitários, chamadas de API, avisos (com Desfazer), modais acessíveis e tema.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    // ---------------------------------------------------------------
    // Utilitários
    // ---------------------------------------------------------------

    /** Prefixo em que o app está publicado (vazio na raiz do domínio). */
    KD.base = typeof window.KD_BASE === 'string' ? window.KD_BASE : '';

    /** Monta uma URL da aplicação: KD.url('/api/tarefas') */
    KD.url = function (path) {
        return KD.base + '/' + String(path || '').replace(/^\/+/, '');
    };

    KD.escapeHtml = function (value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /** Só deixa passar links que o navegador realmente abre com segurança. */
    KD.safeUrl = function (value, fallback) {
        const raw = String(value || '').trim();
        if (!raw) return fallback || '#';
        if (/^https?:\/\//i.test(raw) || /^mailto:/i.test(raw)) return raw;
        return fallback || '#';
    };

    KD.formatSeconds = function (totalSeconds) {
        const sec = Math.max(0, parseInt(totalSeconds || 0, 10));
        const h = Math.floor(sec / 3600).toString().padStart(2, '0');
        const m = Math.floor((sec % 3600) / 60).toString().padStart(2, '0');
        const s = Math.floor(sec % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    };

    KD.formatDate = function (dateStr) {
        if (!dateStr) return '';
        const parts = String(dateStr).slice(0, 10).split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateStr;
    };

    /** "há 5 min", "ontem 14:03" — mais legível que um timestamp cru. */
    KD.formatRelative = function (dateStr) {
        if (!dateStr) return '';
        const parsed = new Date(String(dateStr).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return dateStr;

        const diff = Math.floor((Date.now() - parsed.getTime()) / 1000);
        const time = parsed.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        // Registros gravados antes do fuso ser fixado podem vir "no futuro":
        // nesse caso mostra a data em vez de um "há -3 h".
        if (diff < 0) return `${parsed.toLocaleDateString('pt-BR')} ${time}`;
        if (diff < 60) return 'agora há pouco';
        if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
        if (diff < 86400) return `há ${Math.floor(diff / 3600)} h`;
        if (diff < 172800) return `ontem, ${time}`;
        return `${parsed.toLocaleDateString('pt-BR')} ${time}`;
    };

    KD.debounce = function (fn, wait) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), wait);
        };
    };

    KD.icons = function () {
        if (window.lucide) window.lucide.createIcons();
    };

    // ---------------------------------------------------------------
    // API
    // ---------------------------------------------------------------

    KD.api = {
        async get(url) {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.error) {
                throw new Error(data.error || 'Falha ao carregar os dados.');
            }
            return data;
        },

        async post(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.error) {
                const err = new Error(data.error || 'Não foi possível concluir a ação.');
                err.field = data.field;
                throw err;
            }
            return data;
        },

        tasks(payload) {
            return KD.api.post(KD.url('/api/tarefas'), payload);
        },
    };

    // ---------------------------------------------------------------
    // Avisos (toasts) com ação de Desfazer
    // ---------------------------------------------------------------

    function toastStack() {
        let stack = document.getElementById('kdToastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'kdToastStack';
            stack.className = 'kd-toast-stack';
            stack.setAttribute('role', 'status');
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        return stack;
    }

    /**
     * KD.toast('Tarefa excluída', { tone:'success', undo: () => ... })
     * O botão Desfazer substitui os confirm() em ações destrutivas.
     */
    KD.toast = function (message, options) {
        const opts = options || {};
        const stack = toastStack();

        const el = document.createElement('div');
        el.className = 'kd-toast kd-glass kd-glass--raised';
        el.dataset.tone = opts.tone || 'success';

        const text = document.createElement('span');
        text.className = 'kd-toast__text';
        text.textContent = message;
        el.appendChild(text);

        let timeout = opts.duration || (opts.undo ? 7000 : 3200);

        if (typeof opts.undo === 'function') {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'kd-toast__action';
            btn.textContent = opts.undoLabel || 'Desfazer';
            btn.addEventListener('click', () => {
                dismiss();
                opts.undo();
            });
            el.appendChild(btn);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'kd-icon-btn';
        close.style.width = '22px';
        close.style.height = '22px';
        close.setAttribute('aria-label', 'Fechar aviso');
        close.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5"></i>';
        close.addEventListener('click', () => dismiss());
        el.appendChild(close);

        stack.appendChild(el);
        KD.icons();

        const timer = setTimeout(dismiss, timeout);

        function dismiss() {
            clearTimeout(timer);
            if (!el.isConnected) return;
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 180);
        }

        return dismiss;
    };

    KD.toastError = function (message) {
        KD.toast(message || 'Algo deu errado. Tente novamente.', { tone: 'error', duration: 5000 });
    };

    // ---------------------------------------------------------------
    // Modais acessíveis: Esc fecha, foco preso, foco devolvido ao sair
    // ---------------------------------------------------------------

    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const openModals = [];

    KD.openModal = function (id, options) {
        const modal = document.getElementById(id);
        if (!modal || openModals.includes(modal)) return;

        const opts = options || {};
        modal.__returnFocus = document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        openModals.push(modal);
        document.body.style.overflow = 'hidden';

        const target = opts.focus ? modal.querySelector(opts.focus) : modal.querySelector(FOCUSABLE);
        if (target) {
            window.requestAnimationFrame(() => target.focus());
        }
        KD.icons();
    };

    KD.closeModal = function (id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        const index = openModals.indexOf(modal);
        if (index >= 0) openModals.splice(index, 1);
        if (openModals.length === 0) document.body.style.overflow = '';

        if (modal.__returnFocus && typeof modal.__returnFocus.focus === 'function') {
            modal.__returnFocus.focus();
        }
        modal.__returnFocus = null;
    };

    KD.isModalOpen = function () {
        return openModals.length > 0;
    };

    KD.topModal = function () {
        return openModals[openModals.length - 1] || null;
    };

    document.addEventListener('keydown', (event) => {
        const modal = KD.topModal();
        if (!modal) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            KD.closeModal(modal.id);
            return;
        }

        if (event.key !== 'Tab') return;

        // Mantém o Tab circulando dentro do modal aberto.
        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null);
        if (items.length === 0) return;

        const first = items[0];
        const last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    // Clicar no fundo fecha o modal
    document.addEventListener('mousedown', (event) => {
        const modal = KD.topModal();
        if (modal && event.target === modal) {
            KD.closeModal(modal.id);
        }
    });

    // ---------------------------------------------------------------
    // Menus suspensos (ações do card e da coluna)
    // ---------------------------------------------------------------

    let openMenu = null;
    let openMenuTrigger = null;

    KD.closeMenus = function (except) {
        document.querySelectorAll('.kd-menu:not([hidden])').forEach((menu) => {
            if (menu === except) return;
            menu.hidden = true;
            const trigger = menu.parentElement && menu.parentElement.querySelector('[aria-haspopup="menu"]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
            restoreMenu(menu);
            if (menu === openMenu) {
                openMenu = null;
                openMenuTrigger = null;
            }
        });
    };

    /**
     * O menu do card vive dentro de uma coluna com rolagem própria e, com o
     * efeito de vidro, dentro de um ancestral com backdrop-filter — que faz
     * position:fixed se comportar como absolute e recortaria o menu. Por isso
     * ao abrir ele é movido para o <body>, ancorado no botão, e volta ao lugar
     * ao fechar. Também vira para cima quando não há espaço abaixo.
     */
    function positionMenu(menu, trigger) {
        if (!trigger) return;

        menu.style.position = 'fixed';
        menu.style.top = '0px';
        menu.style.left = '0px';
        menu.style.right = 'auto';

        const anchor = trigger.getBoundingClientRect();
        const size = menu.getBoundingClientRect();
        const margin = 8;

        let top = anchor.bottom + 4;
        if (top + size.height > window.innerHeight - margin) {
            top = Math.max(margin, anchor.top - size.height - 4);
        }

        let left = anchor.right - size.width;
        if (left < margin) left = margin;
        if (left + size.width > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - size.width - margin);
        }

        menu.style.top = `${top}px`;
        menu.style.left = `${left}px`;
    }

    KD.toggleMenu = function (menu, trigger) {
        const willOpen = menu.hidden;
        KD.closeMenus(willOpen ? menu : null);
        menu.hidden = !willOpen;
        if (trigger) trigger.setAttribute('aria-expanded', String(willOpen));

        if (willOpen) {
            openMenu = menu;
            openMenuTrigger = trigger;
            portalMenu(menu);
            KD.icons();
            positionMenu(menu, trigger);
            const firstItem = menu.querySelector('.kd-menu__item');
            if (firstItem) firstItem.focus();
            return;
        }

        restoreMenu(menu);
        openMenu = null;
        openMenuTrigger = null;
        KD.icons();
    };

    /** Tira o menu de dentro da coluna e o coloca no body, guardando a origem. */
    function portalMenu(menu) {
        if (menu.dataset.portaled === '1' || !menu.parentElement) return;

        const anchor = document.createComment('kd-menu');
        menu.parentElement.insertBefore(anchor, menu);
        menu.__anchor = anchor;
        menu.dataset.portaled = '1';
        document.body.appendChild(menu);
    }

    /** Devolve o menu para onde estava, para o card seguir sendo redesenhado. */
    function restoreMenu(menu) {
        menu.style.position = '';
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';

        if (menu.dataset.portaled !== '1') return;
        delete menu.dataset.portaled;

        const anchor = menu.__anchor;
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(menu, anchor);
            anchor.remove();
        } else {
            // O card foi redesenhado enquanto o menu estava aberto.
            menu.remove();
        }
        menu.__anchor = null;
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.kd-menu-wrap')) KD.closeMenus();
    });

    // Rolar ou redimensionar move o botão: o menu acompanha, e some junto
    // com o botão quando ele sai da área visível.
    function repositionOpenMenu() {
        if (!openMenu || openMenu.hidden || !openMenuTrigger) return;

        const anchor = openMenuTrigger.getBoundingClientRect();
        const visible = anchor.bottom > 0 && anchor.top < window.innerHeight
            && anchor.right > 0 && anchor.left < window.innerWidth;

        if (!visible) {
            KD.closeMenus();
            return;
        }
        positionMenu(openMenu, openMenuTrigger);
    }

    window.addEventListener('resize', repositionOpenMenu);
    document.addEventListener('scroll', repositionOpenMenu, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !KD.isModalOpen()) KD.closeMenus();
    });

    // ---------------------------------------------------------------
    // Tema: aplicado localmente e guardado no perfil do usuário
    // ---------------------------------------------------------------

    KD.THEME_KEY = 'kanbandoo_theme';

    KD.applyTheme = function (theme) {
        const isLight = theme === 'light';
        document.documentElement.classList.toggle('light', isLight);
        document.documentElement.classList.toggle('dark', !isLight);

        const icon = document.getElementById('themeToggleIcon');
        if (icon) {
            icon.setAttribute('data-lucide', isLight ? 'moon' : 'sun');
            KD.icons();
        }
        const btn = document.getElementById('themeToggleBtn');
        if (btn) {
            btn.setAttribute('aria-label', isLight ? 'Mudar para tema escuro' : 'Mudar para tema claro');
        }
    };

    KD.toggleTheme = function () {
        const next = document.documentElement.classList.contains('light') ? 'dark' : 'light';
        try { localStorage.setItem(KD.THEME_KEY, next); } catch (e) { /* modo privado */ }
        KD.applyTheme(next);

        // Guarda no perfil para a preferência acompanhar o usuário em outra máquina.
        KD.api.post(KD.url('/api/preferencias'), { theme: next }).catch(() => {});
    };

    document.addEventListener('DOMContentLoaded', () => {
        let stored = null;
        try { stored = localStorage.getItem(KD.THEME_KEY); } catch (e) { /* modo privado */ }
        KD.applyTheme(stored || document.documentElement.dataset.theme || 'dark');
    });

    // ---------------------------------------------------------------
    // Transparência (liquid glass)
    // ---------------------------------------------------------------

    KD.GLASS_KEY = 'kanbandoo_glass';

    /** enabled: true | false | null (null = seguir a preferência do sistema) */
    KD.applyGlass = function (enabled) {
        const root = document.documentElement;

        if (enabled === null) {
            delete root.dataset.glass;
        } else {
            root.dataset.glass = enabled ? 'on' : 'off';
        }

        // Estado real: com a escolha do usuário vencendo o sistema.
        const systemReduces = window.matchMedia('(prefers-reduced-transparency: reduce)').matches;
        const active = enabled === null ? !systemReduces : enabled;

        const btn = document.getElementById('glassToggleBtn');
        if (btn) {
            btn.setAttribute('aria-pressed', String(active));
            btn.setAttribute('aria-label', active ? 'Reduzir transparência' : 'Ativar efeito de vidro');
            btn.classList.toggle('kd-btn--active', active);
            const icon = btn.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', active ? 'sparkles' : 'droplet-off');
                KD.icons();
            }
        }
    };

    KD.toggleGlass = function () {
        const systemReduces = window.matchMedia('(prefers-reduced-transparency: reduce)').matches;
        const current = document.documentElement.dataset.glass;
        const active = current === undefined || current === '' ? !systemReduces : current === 'on';
        const next = !active;

        try { localStorage.setItem(KD.GLASS_KEY, next ? 'on' : 'off'); } catch (e) { /* modo privado */ }
        KD.applyGlass(next);
    };

    document.addEventListener('DOMContentLoaded', () => {
        let stored = null;
        try { stored = localStorage.getItem(KD.GLASS_KEY); } catch (e) { /* modo privado */ }
        KD.applyGlass(stored === null ? null : stored === 'on');
    });

    // ---------------------------------------------------------------
    // Menu do usuário na barra superior
    // ---------------------------------------------------------------

    KD.toggleUserMenu = function (event) {
        if (event) {
            event.stopPropagation();
        }
        const popup = document.getElementById('userMenuPopup');
        const trigger = document.getElementById('userMenuTrigger');
        if (!popup) return;
        const willOpen = popup.hidden;
        KD.closeMenus(willOpen ? popup : null);
        popup.hidden = !willOpen;
        if (trigger) trigger.setAttribute('aria-expanded', String(willOpen));
    };

    document.addEventListener('click', (event) => {
        const wrap = event.target.closest('.kd-menu-wrap');
        if (!wrap) {
            KD.closeMenus();
            const popup = document.getElementById('userMenuPopup');
            if (popup && !popup.hidden) {
                popup.hidden = true;
                const trigger = document.getElementById('userMenuTrigger');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
            const colMenu = document.getElementById('columnsMenuDropdown');
            if (colMenu && !colMenu.hidden) {
                colMenu.hidden = true;
                const colBtn = document.getElementById('btnToggleColumnsMenu');
                if (colBtn) colBtn.setAttribute('aria-expanded', 'false');
            }
        }
    });
})(window.KD);
