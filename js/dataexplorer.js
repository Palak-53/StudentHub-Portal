/**
 * =========================================================
 * STUDENT HUB — DATA EXPLORER (Modular JS)
 * dataexplorer.js
 *
 * Modules:
 *  1. State          — Central app state
 *  2. DataService    — Fetch API + error handling
 *  3. Renderer       — Dynamic card/list rendering
 *  4. Search         — Search functionality
 *  5. Filter         — Category filter
 *  6. Sorter         — Sorting (asc/desc)
 *  7. Paginator      — Pagination logic
 *  8. UI             — Loading/error/empty states + stats
 *  9. App            — Initialization & event wiring
 * =========================================================
 */

'use strict';

/* ═══════════════════════════════════════════════════════════
   1. STATE — Single source of truth
   ═══════════════════════════════════════════════════════════ */
const State = {
    currentDataset: 'students',   // 'students' | 'events' | 'faqs'
    rawData:        [],           // original fetched records
    filteredData:   [],           // after search + filter + sort
    searchQuery:    '',
    filterValue:    '',
    sortKey:        '',
    sortDir:        'asc',        // 'asc' | 'desc'
    currentPage:    1,
    pageSize:       6,
};

/* ═══════════════════════════════════════════════════════════
   2. DATA SERVICE — Fetch API with loading/error handling
   ═══════════════════════════════════════════════════════════ */
const DataService = {
    requestId: 0,

    /**
     * Fetch a JSON file and return parsed data.
     * @param {string} dataset - 'students' | 'events' | 'faqs'
     * @returns {Promise<Array>}
     */
    async fetch(dataset) {
        const url = `data/${dataset}.json`;
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Could not load ${url}`);
        }
        const data = await response.json();
        if (!Array.isArray(data)) {
            throw new Error(`Invalid data format in ${url}. Expected an array.`);
        }
        return data;
    },

    /**
     * Load a dataset: shows loader, fetches, hides loader, renders.
     * @param {string} dataset
     */
    async load(dataset) {
        const requestId = ++this.requestId;
        UI.showLoading();
        UI.hideError();
        UI.hideEmpty();
        Renderer.clearAll();
        Paginator.hide();
        State.rawData = [];
        State.filteredData = [];

        try {
            const data = await DataService.fetch(dataset);
            if (requestId !== this.requestId) return;

            State.rawData      = data;
            State.filteredData = [...data];
            State.currentPage  = 1;

            Filter.buildOptions(dataset, data);
            Sorter.buildOptions(dataset);

            // Reset controls
            DOM.searchInput.value  = '';
            DOM.filterSelect.value = '';
            DOM.sortSelect.value   = '';
            State.searchQuery = '';
            State.filterValue = '';
            State.sortKey     = '';
            State.sortDir     = 'asc';
            Sorter.updateDirButton();
            Search.updateClearBtn();

            Pipeline.run();
        } catch (err) {
            if (requestId !== this.requestId) return;

            UI.hideLoading();
            UI.showError(err.message || 'Failed to fetch data. Please try again.');
            console.error('[DataExplorer] Fetch error:', err);
        }
    },
};

/* ═══════════════════════════════════════════════════════════
   3. RENDERER — Build HTML cards from data
   ═══════════════════════════════════════════════════════════ */
const Renderer = {

    clearAll() {
        DOM.studentsGrid.innerHTML = '';
        DOM.eventsGrid.innerHTML   = '';
        DOM.faqsList.innerHTML     = '';
    },

    render(dataset, records) {
        this.clearAll();
        if (dataset === 'students') this.renderStudents(records);
        if (dataset === 'events')   this.renderEvents(records);
        if (dataset === 'faqs')     this.renderFaqs(records);
    },

    /* ── Students ── */
    renderStudents(records) {
        if (!records.length) return;
        const frag = document.createDocumentFragment();
        records.forEach(s => {
            const card = document.createElement('article');
            card.className = 'de-student-card';
            card.setAttribute('aria-label', `Student: ${s.name}`);

            const initial  = s.name.charAt(0).toUpperCase();
            const gClass   = s.gender.toLowerCase();
            const statusCls = s.status.toLowerCase() === 'active' ? 'active' : 'warning';

            card.innerHTML = `
                <div class="de-student-top">
                    <div class="de-avatar ${gClass}" aria-hidden="true">${initial}</div>
                    <div class="de-student-info">
                        <h3>${escHtml(s.name)}</h3>
                        <p>${escHtml(s.enrollmentNo)} &nbsp;·&nbsp; ${escHtml(s.city)}</p>
                    </div>
                </div>
                <div class="de-badges">
                    <span class="de-badge course">${escHtml(s.course)}</span>
                    <span class="de-badge ${statusCls}">${escHtml(s.status)}</span>
                    <span class="de-badge" style="color:#64748b;background:var(--bg-page);">Year ${s.year}</span>
                </div>
                <div class="de-student-stats">
                    <div class="de-stat-cell">
                        <span class="val">${s.cgpa}</span>
                        <span class="lbl">CGPA</span>
                    </div>
                    <div class="de-stat-cell">
                        <span class="val" style="color:${s.attendance < 75 ? '#ef4444' : '#16a34a'}">${s.attendance}%</span>
                        <span class="lbl">Attendance</span>
                    </div>
                </div>
            `;
            frag.appendChild(card);
        });
        DOM.studentsGrid.appendChild(frag);
    },

    /* ── Events ── */
    renderEvents(records) {
        if (!records.length) return;
        const frag = document.createDocumentFragment();
        records.forEach(ev => {
            const card = document.createElement('article');
            card.className = 'de-event-card';
            card.setAttribute('aria-label', `Event: ${ev.title}`);

            const catClass    = 'cat-' + ev.category.toLowerCase();
            const statusClass = 'status-' + ev.status.toLowerCase();
            const pct         = Math.round((ev.registered / ev.seats) * 100);
            const barClass    = pct >= 90 ? 'low' : pct >= 60 ? 'mid' : 'high';
            const formattedDate = new Date(ev.date).toLocaleDateString('en-IN', { day:'numeric', month:'short', year:'numeric' });

            card.innerHTML = `
                <div class="de-event-top">
                    <h3 class="de-event-title">${escHtml(ev.title)}</h3>
                    <span class="de-event-cat ${catClass}">${escHtml(ev.category)}</span>
                </div>
                <div class="de-event-meta">
                    <span>📅 ${formattedDate} &nbsp;·&nbsp; ⏰ ${escHtml(ev.time)}</span>
                    <span>📍 ${escHtml(ev.venue)} &nbsp;·&nbsp; 🏢 ${escHtml(ev.organizer)}</span>
                </div>
                <p class="de-event-desc">${escHtml(ev.description)}</p>
                <div class="de-event-footer">
                    <div class="de-seats-bar-wrap">
                        <div class="de-seats-label">${ev.registered}/${ev.seats} seats filled</div>
                        <div class="de-seats-bar">
                            <div class="de-seats-fill ${barClass}" style="width:${pct}%"></div>
                        </div>
                    </div>
                    <span class="de-status-badge ${statusClass}">${escHtml(ev.status)}</span>
                </div>
            `;
            frag.appendChild(card);
        });
        DOM.eventsGrid.appendChild(frag);
    },

    /* ── FAQs ── */
    renderFaqs(records) {
        if (!records.length) return;
        const frag = document.createDocumentFragment();
        records.forEach(faq => {
            const card = document.createElement('article');
            card.className = 'de-faq-card';
            card.setAttribute('aria-label', `FAQ: ${faq.question}`);

            const tags = (faq.tags || []).map(t => `<span class="de-tag">${escHtml(t)}</span>`).join('');

            card.innerHTML = `
                <div class="de-faq-top">
                    <h3 class="de-faq-q">Q. ${escHtml(faq.question)}</h3>
                    <span class="de-faq-cat">${escHtml(faq.category)}</span>
                </div>
                <p class="de-faq-a">${escHtml(faq.answer)}</p>
                <div class="de-faq-footer">
                    <span>👍 ${faq.helpful} helpful</span>
                    <span>👁️ ${faq.views} views</span>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-left:auto">${tags}</div>
                </div>
            `;
            frag.appendChild(card);
        });
        DOM.faqsList.appendChild(frag);
    },
};

/* ═══════════════════════════════════════════════════════════
   4. SEARCH — Filter records by text query
   ═══════════════════════════════════════════════════════════ */
const Search = {

    /**
     * Search records by looking in all string/number values.
     * @param {Array}  records
     * @param {string} query
     * @returns {Array}
     */
    apply(records, query) {
        if (!query.trim()) return records;
        const q = query.toLowerCase();
        return records.filter(record =>
            Object.values(record).some(val =>
                String(val).toLowerCase().includes(q)
            )
        );
    },

    updateClearBtn() {
        const hasVal = DOM.searchInput.value.trim().length > 0;
        DOM.clearSearch.classList.toggle('visible', hasVal);
    },
};

/* ═══════════════════════════════════════════════════════════
   5. FILTER — Category-based filtering
   ═══════════════════════════════════════════════════════════ */
const Filter = {

    /**
     * Key to filter on per dataset.
     */
    FILTER_KEYS: {
        students: 'course',
        events:   'category',
        faqs:     'category',
    },

    /**
     * Build unique filter options from data.
     */
    buildOptions(dataset, data) {
        const key    = this.FILTER_KEYS[dataset];
        const values = [...new Set(data.map(r => r[key]).filter(Boolean))].sort();

        DOM.filterSelect.innerHTML = '<option value="">All Categories</option>';
        values.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            DOM.filterSelect.appendChild(opt);
        });
    },

    /**
     * Apply category filter.
     */
    apply(records, dataset, value) {
        if (!value) return records;
        const key = this.FILTER_KEYS[dataset];
        return records.filter(r => r[key] === value);
    },
};

/* ═══════════════════════════════════════════════════════════
   6. SORTER — Sort records by chosen key and direction
   ═══════════════════════════════════════════════════════════ */
const Sorter = {

    SORT_OPTIONS: {
        students: [
            { value: 'name',       label: 'Name (A–Z)' },
            { value: 'cgpa',       label: 'CGPA' },
            { value: 'attendance', label: 'Attendance %' },
            { value: 'year',       label: 'Year' },
        ],
        events: [
            { value: 'title',      label: 'Title (A–Z)' },
            { value: 'date',       label: 'Date' },
            { value: 'registered', label: 'Registrations' },
            { value: 'seats',      label: 'Total Seats' },
        ],
        faqs: [
            { value: 'question',   label: 'Question (A–Z)' },
            { value: 'helpful',    label: 'Helpful votes' },
            { value: 'views',      label: 'Views' },
        ],
    },

    buildOptions(dataset) {
        const opts = this.SORT_OPTIONS[dataset] || [];
        DOM.sortSelect.innerHTML = '<option value="">Default Order</option>';
        opts.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.label;
            DOM.sortSelect.appendChild(opt);
        });
    },

    /**
     * Sort an array of records.
     * @param {Array}  records
     * @param {string} key
     * @param {string} dir  'asc' | 'desc'
     */
    apply(records, key, dir) {
        if (!key) return records;
        return [...records].sort((a, b) => {
            let av = a[key];
            let bv = b[key];

            // Numeric sort
            if (typeof av === 'number' && typeof bv === 'number') {
                return dir === 'asc' ? av - bv : bv - av;
            }

            // Date sort
            if (typeof av === 'string' && av.match(/^\d{4}-\d{2}-\d{2}/)) {
                av = new Date(av).getTime();
                bv = new Date(bv).getTime();
                return dir === 'asc' ? av - bv : bv - av;
            }

            // String sort
            av = String(av).toLowerCase();
            bv = String(bv).toLowerCase();
            if (av < bv) return dir === 'asc' ? -1 : 1;
            if (av > bv) return dir === 'asc' ?  1 : -1;
            return 0;
        });
    },

    updateDirButton() {
        const btn = DOM.sortDirBtn;
        if (State.sortDir === 'asc') {
            btn.textContent = '↑';
            btn.setAttribute('aria-label', 'Sort direction: ascending. Click for descending');
            btn.classList.remove('desc');
        } else {
            btn.textContent = '↓';
            btn.setAttribute('aria-label', 'Sort direction: descending. Click for ascending');
            btn.classList.add('desc');
        }
    },
};

/* ═══════════════════════════════════════════════════════════
   7. PAGINATOR — Page-by-page display
   ═══════════════════════════════════════════════════════════ */
const Paginator = {

    /**
     * Slice the records array for the current page.
     */
    slice(records) {
        const { currentPage, pageSize } = State;
        const start = (currentPage - 1) * pageSize;
        return records.slice(start, start + pageSize);
    },

    totalPages() {
        return Math.ceil(State.filteredData.length / State.pageSize);
    },

    render() {
        const total   = this.totalPages();
        const current = State.currentPage;

        if (total <= 1) {
            this.hide();
            return;
        }

        DOM.paginationBar.hidden = false;
        DOM.prevPage.disabled    = current === 1;
        DOM.nextPage.disabled    = current === total;

        // Build page number buttons
        DOM.pageNumbers.innerHTML = '';

        const pages = this.getPageRange(current, total);
        pages.forEach(p => {
            if (p === '…') {
                const el = document.createElement('span');
                el.className = 'de-page-ellipsis';
                el.textContent = '…';
                DOM.pageNumbers.appendChild(el);
            } else {
                const btn = document.createElement('button');
                btn.className = 'de-page-num' + (p === current ? ' active' : '');
                btn.textContent = p;
                btn.setAttribute('aria-label', `Page ${p}`);
                btn.setAttribute('aria-current', p === current ? 'page' : 'false');
                btn.addEventListener('click', () => {
                    State.currentPage = p;
                    Pipeline.run();
                    DOM.paginationBar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
                DOM.pageNumbers.appendChild(btn);
            }
        });

        const start = (current - 1) * State.pageSize + 1;
        const end   = Math.min(current * State.pageSize, State.filteredData.length);
        DOM.pageInfo.textContent = `Showing ${start}–${end} of ${State.filteredData.length}`;
    },

    /**
     * Produce a smart page range like [1, 2, '…', 8, 9, 10]
     */
    getPageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = [];
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) pages.push(i);
            pages.push('…', total);
        } else if (current >= total - 3) {
            pages.push(1, '…');
            for (let i = total - 4; i <= total; i++) pages.push(i);
        } else {
            pages.push(1, '…', current - 1, current, current + 1, '…', total);
        }
        return pages;
    },

    hide() {
        DOM.paginationBar.hidden = true;
    },
};

/* ═══════════════════════════════════════════════════════════
   8. UI — Loading, Error, Empty states + Stats bar
   ═══════════════════════════════════════════════════════════ */
const UI = {

    showLoading()  {
        DOM.loadingState.hidden = false;
        DOM.errorState.hidden   = true;
        DOM.emptyState.hidden   = true;
    },
    hideLoading()  { DOM.loadingState.hidden = true; },

    showError(msg) {
        DOM.errorState.hidden   = false;
        DOM.loadingState.hidden = true;
        DOM.emptyState.hidden   = true;
        DOM.errorMsg.textContent = msg;
    },
    hideError()    { DOM.errorState.hidden = true; },

    showEmpty()    {
        DOM.emptyState.hidden   = false;
        DOM.loadingState.hidden = true;
    },
    hideEmpty()    { DOM.emptyState.hidden = true; },

    updateStats(total, filtered) {
        const txt = filtered === total
            ? `${total} records`
            : `${filtered} of ${total} records`;
        DOM.statsText.textContent = txt;
    },

    /** Switch the visible tab panel */
    switchPanel(dataset) {
        DOM.panels.forEach(p => p.hidden = true);
        const panel = document.getElementById(`panel-${dataset}`);
        if (panel) panel.hidden = false;
    },
};

/* ═══════════════════════════════════════════════════════════
   PIPELINE — Run search → filter → sort → paginate → render
   ═══════════════════════════════════════════════════════════ */
const Pipeline = {
    run() {
        let records = [...State.rawData];

        // 1. Search
        records = Search.apply(records, State.searchQuery);

        // 2. Filter
        records = Filter.apply(records, State.currentDataset, State.filterValue);

        // 3. Sort
        records = Sorter.apply(records, State.sortKey, State.sortDir);

        // Save processed data for pagination
        State.filteredData = records;

        // 4. Paginate — get page slice
        const pageSlice = Paginator.slice(records);

        // 5. Render
        UI.hideLoading();
        UI.hideError();

        if (!records.length) {
            UI.showEmpty();
            Renderer.clearAll();
            Paginator.hide();
        } else {
            UI.hideEmpty();
            Renderer.render(State.currentDataset, pageSlice);
            Paginator.render();
        }

        // 6. Stats
        UI.updateStats(State.rawData.length, records.length);
    },
};

/* ═══════════════════════════════════════════════════════════
   DOM — Cached element references
   ═══════════════════════════════════════════════════════════ */
const DOM = {
    tabs:          null,
    searchInput:   null,
    clearSearch:   null,
    filterSelect:  null,
    sortSelect:    null,
    sortDirBtn:    null,
    statsText:     null,
    loadingState:  null,
    errorState:    null,
    errorMsg:      null,
    retryBtn:      null,
    emptyState:    null,
    clearAllFilters:null,
    studentsGrid:  null,
    eventsGrid:    null,
    faqsList:      null,
    paginationBar: null,
    prevPage:      null,
    nextPage:      null,
    pageNumbers:   null,
    pageInfo:      null,
    panels:        null,

    init() {
        this.tabs           = document.querySelectorAll('.de-tab');
        this.searchInput    = document.getElementById('searchInput');
        this.clearSearch    = document.getElementById('clearSearch');
        this.filterSelect   = document.getElementById('filterSelect');
        this.sortSelect     = document.getElementById('sortSelect');
        this.sortDirBtn     = document.getElementById('sortDirBtn');
        this.statsText      = document.getElementById('statsText');
        this.loadingState   = document.getElementById('loadingState');
        this.errorState     = document.getElementById('errorState');
        this.errorMsg       = document.getElementById('errorMsg');
        this.retryBtn       = document.getElementById('retryBtn');
        this.emptyState     = document.getElementById('emptyState');
        this.clearAllFilters= document.getElementById('clearAllFilters');
        this.studentsGrid   = document.getElementById('studentsGrid');
        this.eventsGrid     = document.getElementById('eventsGrid');
        this.faqsList       = document.getElementById('faqsList');
        this.paginationBar  = document.getElementById('paginationBar');
        this.prevPage       = document.getElementById('prevPage');
        this.nextPage       = document.getElementById('nextPage');
        this.pageNumbers    = document.getElementById('pageNumbers');
        this.pageInfo       = document.getElementById('pageInfo');
        this.panels         = document.querySelectorAll('[role="tabpanel"]');
    },
};

/* ═══════════════════════════════════════════════════════════
   9. APP — Initialize and wire all events
   ═══════════════════════════════════════════════════════════ */
const App = {

    init() {
        DOM.init();
        this.wireEvents();
        // Load default dataset
        UI.switchPanel(State.currentDataset);
        DataService.load(State.currentDataset);
    },

    wireEvents() {

        /* ── Tab switching ── */
        DOM.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Update tab active state
                DOM.tabs.forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');

                const dataset = tab.dataset.dataset;
                State.currentDataset = dataset;
                State.currentPage    = 1;

                UI.switchPanel(dataset);
                DataService.load(dataset);
            });

            // Keyboard: Left/Right arrow to navigate tabs
            tab.addEventListener('keydown', e => {
                const tabArray = [...DOM.tabs];
                const idx = tabArray.indexOf(tab);
                if (e.key === 'ArrowRight') {
                    tabArray[(idx + 1) % tabArray.length].focus();
                    tabArray[(idx + 1) % tabArray.length].click();
                }
                if (e.key === 'ArrowLeft') {
                    tabArray[(idx - 1 + tabArray.length) % tabArray.length].focus();
                    tabArray[(idx - 1 + tabArray.length) % tabArray.length].click();
                }
            });
        });

        /* ── Search ── */
        let searchTimer;
        DOM.searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            Search.updateClearBtn();
            // Debounce 300ms
            searchTimer = setTimeout(() => {
                State.searchQuery = DOM.searchInput.value;
                State.currentPage = 1;
                Pipeline.run();
            }, 300);
        });

        DOM.clearSearch.addEventListener('click', () => {
            DOM.searchInput.value = '';
            State.searchQuery     = '';
            State.currentPage     = 1;
            Search.updateClearBtn();
            Pipeline.run();
            DOM.searchInput.focus();
        });

        /* ── Filter ── */
        DOM.filterSelect.addEventListener('change', () => {
            State.filterValue = DOM.filterSelect.value;
            State.currentPage = 1;
            Pipeline.run();
        });

        /* ── Sort key ── */
        DOM.sortSelect.addEventListener('change', () => {
            State.sortKey     = DOM.sortSelect.value;
            State.currentPage = 1;
            Pipeline.run();
        });

        /* ── Sort direction toggle ── */
        DOM.sortDirBtn.addEventListener('click', () => {
            State.sortDir     = State.sortDir === 'asc' ? 'desc' : 'asc';
            State.currentPage = 1;
            Sorter.updateDirButton();
            Pipeline.run();
        });

        /* ── Pagination prev/next ── */
        DOM.prevPage.addEventListener('click', () => {
            if (State.currentPage > 1) {
                State.currentPage--;
                Pipeline.run();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        DOM.nextPage.addEventListener('click', () => {
            if (State.currentPage < Paginator.totalPages()) {
                State.currentPage++;
                Pipeline.run();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        /* ── Retry on error ── */
        DOM.retryBtn.addEventListener('click', () => {
            DataService.load(State.currentDataset);
        });

        /* ── Clear all filters ── */
        DOM.clearAllFilters.addEventListener('click', () => {
            DOM.searchInput.value  = '';
            DOM.filterSelect.value = '';
            DOM.sortSelect.value   = '';
            State.searchQuery = '';
            State.filterValue = '';
            State.sortKey     = '';
            State.currentPage = 1;
            Search.updateClearBtn();
            Pipeline.run();
        });
    },
};

/* ═══════════════════════════════════════════════════════════
   UTILITY — Escape HTML to prevent XSS
   ═══════════════════════════════════════════════════════════ */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/* ── Bootstrap ── */
document.addEventListener('DOMContentLoaded', () => App.init());
