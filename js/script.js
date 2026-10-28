// Student Hub Portal — Base Script
// Theme is fully managed by js/interactive.js to avoid double-toggle conflicts.
// This file is kept for other pages that only load script.js (without interactive.js).

document.addEventListener('DOMContentLoaded', () => {
    // Only attach theme toggle if interactive.js is NOT already loaded on this page.
    // interactive.js sets window.__interactiveLoaded = true before attaching.
    if (window.__interactiveLoaded) return;

    const themeToggle = document.getElementById('themeToggle');
    const DARK_KEY = 'studenthub-theme';

    // Restore saved preference immediately on page load
    const savedTheme = localStorage.getItem(DARK_KEY);
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        if (themeToggle) themeToggle.textContent = '☀️';
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            themeToggle.textContent = isDark ? '☀️' : '🌙';
            localStorage.setItem(DARK_KEY, isDark ? 'dark' : 'light');
        });
    }
});

document.addEventListener('DOMContentLoaded', async () => {
    const profileLink = document.querySelector('.header-nav .nav-profile-box');
    if (!profileLink || document.querySelector('.role-nav-badge')) return;

    try {
        const currentPage = window.location.pathname.split('/').pop().toLowerCase();
        const response = await fetch(`session_info.php?page=${encodeURIComponent(currentPage)}`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        });
        if (!response.ok) {
            throw new Error(`Could not load the signed-in account (HTTP ${response.status}).`);
        }
        const session = await response.json();
        if (currentPage === 'home.html') {
            window.location.replace('home.php');
            return;
        }

        document.querySelectorAll('a[href="home.html"]').forEach((link) => {
            link.href = 'home.php';
        });

        if (!session.authenticated) {
            profileLink.href = 'login.php';
            const name = profileLink.querySelector('.nav-profile-name');
            if (name) name.textContent = 'Sign in';
            const mobileProfile = document.querySelector('#mobileNav a[href="profile.html"]');
            if (mobileProfile) {
                mobileProfile.href = 'login.php';
                mobileProfile.textContent = 'Sign in';
            }
            return;
        }

        if (session.role === 'Admin') {
            window.location.replace('dashboard.php');
            return;
        }

        if (session.role !== 'Student' || typeof session.name !== 'string') {
            throw new Error('The signed-in account role is not recognized.');
        }

        if (typeof session.headerHtml !== 'string') {
            throw new Error('The role-specific navigation is missing.');
        }

        const template = document.createElement('template');
        template.innerHTML = session.headerHtml;
        const header = document.querySelector('header.app-header');
        if (!header || !template.content.firstElementChild) {
            throw new Error('The Student Hub navigation could not be installed.');
        }
        header.replaceWith(template.content);

        const hamburger = document.getElementById('hamburgerBtn');
        const mobileNav = document.getElementById('mobileNav');
        const overlay = document.getElementById('navOverlay');
        const closeNav = () => {
            hamburger?.classList.remove('open');
            hamburger?.setAttribute('aria-expanded', 'false');
            mobileNav?.classList.remove('open');
            mobileNav?.setAttribute('aria-hidden', 'true');
            overlay?.classList.remove('active');
            document.body.style.overflow = '';
        };
        hamburger?.addEventListener('click', () => {
            const isOpen = hamburger.classList.toggle('open');
            hamburger.setAttribute('aria-expanded', String(isOpen));
            mobileNav?.classList.toggle('open', isOpen);
            mobileNav?.setAttribute('aria-hidden', String(!isOpen));
            overlay?.classList.toggle('active', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        });
        overlay?.addEventListener('click', closeNav);
        mobileNav?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeNav));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeNav();
        });
        window.addEventListener('resize', () => {
            const breakpoint = document.querySelector('.portal-student-header') ? 1650 : 1300;
            if (window.innerWidth > breakpoint) closeNav();
        });

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const isDark = localStorage.getItem('studenthub-theme') === 'dark';
            document.body.classList.toggle('dark-mode', isDark);
            themeToggle.textContent = isDark ? '☀️' : '🌙';
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const darkMode = document.body.classList.contains('dark-mode');
                themeToggle.textContent = darkMode ? '☀️' : '🌙';
                localStorage.setItem('studenthub-theme', darkMode ? 'dark' : 'light');
            });
        }
    } catch (error) {
        console.error('Could not update the Student Hub navigation.', error);
    }
});

(() => {
    const form = document.getElementById('student-form');
    if (!form) return;

    const apiUrl = 'api.php';
    const message = document.getElementById('api-message');
    const list = document.getElementById('student-list');
    const count = document.getElementById('record-count');
    const search = document.getElementById('student-search');
    const saveButton = document.getElementById('save-button');
    const cancelButton = document.getElementById('cancel-edit');
    const refreshButton = document.getElementById('refresh-button');
    const fields = {
        student_id: document.getElementById('student-id'),
        enrollment_no: document.getElementById('enrollment-no'),
        full_name: document.getElementById('full-name'),
        email: document.getElementById('email'),
        course: document.getElementById('course')
    };
    let students = [];
    let csrfToken = '';
    let requestInProgress = false;

    function showMessage(text, kind) {
        message.textContent = text;
        message.className = `message visible ${kind}`;
        message.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    }

    function hideMessage() {
        message.textContent = '';
        message.className = 'message';
    }

    function setBusy(busy, text = 'Loading student records…') {
        requestInProgress = busy;
        saveButton.disabled = busy;
        refreshButton.disabled = busy;
        if (busy) {
            showMessage(text, 'loading');
            list.replaceChildren();
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.className = 'empty';
            cell.colSpan = 5;
            cell.textContent = 'Loading…';
            row.append(cell);
            list.append(row);
        }
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.method && options.method !== 'GET' ? { 'X-CSRF-Token': csrfToken } : {}),
                ...options.headers
            }
        });
        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error(`The server returned an invalid response (HTTP ${response.status}).`);
        }
        if (!response.ok || payload.success !== true) {
            throw new Error(payload.error || `Request failed (HTTP ${response.status}).`);
        }
        return payload;
    }

    function makeCell(text) {
        const cell = document.createElement('td');
        cell.textContent = text ?? '';
        return cell;
    }

    function renderStudents() {
        const query = search.value.trim().toLocaleLowerCase();
        const shownStudents = students.filter((student) =>
            [student.enrollment_no, student.full_name, student.email, student.course]
                .some((value) => String(value ?? '').toLocaleLowerCase().includes(query))
        );
        count.textContent = `${shownStudents.length} student${shownStudents.length === 1 ? '' : 's'} shown`;
        list.replaceChildren();

        if (shownStudents.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.className = 'empty';
            cell.colSpan = 5;
            cell.textContent = students.length === 0 ? 'No active student records.' : 'No records match your search.';
            row.append(cell);
            list.append(row);
            return;
        }

        shownStudents.forEach((student) => {
            const row = document.createElement('tr');
            row.append(
                makeCell(student.enrollment_no),
                makeCell(student.full_name),
                makeCell(student.email),
                makeCell(student.course)
            );
            const actionsCell = document.createElement('td');
            const actions = document.createElement('div');
            actions.className = 'actions';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'secondary';
            editButton.textContent = 'Edit';
            editButton.setAttribute('aria-label', `Edit ${student.full_name}`);
            editButton.addEventListener('click', () => beginEdit(student));

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'danger';
            deleteButton.textContent = 'Delete';
            deleteButton.setAttribute('aria-label', `Delete ${student.full_name}`);
            deleteButton.addEventListener('click', () => deleteStudent(student));

            actions.append(editButton, deleteButton);
            actionsCell.append(actions);
            row.append(actionsCell);
            list.append(row);
        });
    }

    function beginEdit(student) {
        fields.student_id.value = student.student_id;
        fields.enrollment_no.value = student.enrollment_no;
        fields.full_name.value = student.full_name;
        fields.email.value = student.email;
        fields.course.value = student.course;
        document.getElementById('form-title').textContent = 'Edit student';
        saveButton.textContent = 'Save changes';
        cancelButton.hidden = false;
        fields.enrollment_no.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        hideMessage();
    }

    function resetForm() {
        form.reset();
        fields.student_id.value = '';
        document.getElementById('form-title').textContent = 'Add a student';
        saveButton.textContent = 'Add student';
        cancelButton.hidden = true;
    }

    async function loadStudents(showLoading = true, propagateError = false) {
        if (requestInProgress && showLoading) return;
        if (showLoading) setBusy(true);
        try {
            const payload = await request(apiUrl);
            students = payload.data;
            csrfToken = payload.csrfToken;
            renderStudents();
            hideMessage();
        } catch (error) {
            students = [];
            count.textContent = 'Student records unavailable';
            list.replaceChildren();
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.className = 'empty';
            cell.colSpan = 5;
            cell.textContent = error.message;
            row.append(cell);
            list.append(row);
            showMessage(error.message, 'error');
            if (propagateError) throw error;
        } finally {
            requestInProgress = false;
            saveButton.disabled = false;
            refreshButton.disabled = false;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (requestInProgress) return;

        const student = {
            enrollment_no: fields.enrollment_no.value.trim(),
            full_name: fields.full_name.value.trim(),
            email: fields.email.value.trim(),
            course: fields.course.value.trim()
        };
        const isEditing = fields.student_id.value !== '';
        if (isEditing) student.student_id = Number(fields.student_id.value);

        setBusy(true, isEditing ? 'Updating student…' : 'Adding student…');
        try {
            const payload = await request(apiUrl, {
                method: isEditing ? 'PUT' : 'POST',
                body: JSON.stringify(student)
            });
            resetForm();
            await loadStudents(false, true);
            showMessage(payload.message, 'success');
        } catch (error) {
            showMessage(error.message, 'error');
            renderStudents();
        } finally {
            requestInProgress = false;
            saveButton.disabled = false;
            refreshButton.disabled = false;
        }
    });

    async function deleteStudent(student) {
        if (requestInProgress || !window.confirm(`Mark ${student.full_name} inactive?`)) return;
        setBusy(true, 'Deleting student…');
        try {
            const payload = await request(apiUrl, {
                method: 'DELETE',
                body: JSON.stringify({ student_id: student.student_id })
            });
            await loadStudents(false, true);
            showMessage(payload.message, 'success');
        } catch (error) {
            showMessage(error.message, 'error');
            renderStudents();
        } finally {
            requestInProgress = false;
            saveButton.disabled = false;
            refreshButton.disabled = false;
        }
    }

    cancelButton.addEventListener('click', resetForm);
    refreshButton.addEventListener('click', () => loadStudents());
    search.addEventListener('input', renderStudents);
    loadStudents();
})();
