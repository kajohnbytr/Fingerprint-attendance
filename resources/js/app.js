const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
const page = document.body.dataset.page;
const subjects = ['Computer Science', 'Information Technology', 'Business Administration', 'Civil Engineering'];
const blocks = [
    { name: 'CS-1A', subject: subjects[0], year: 1, adviser: 'Dr. James Patel' },
    { name: 'CS-2A', subject: subjects[0], year: 2, adviser: 'Prof. Emily Carter' },
    { name: 'IT-1A', subject: subjects[1], year: 1, adviser: 'Prof. Daniel Reyes' },
    { name: 'IT-3A', subject: subjects[1], year: 3, adviser: 'Dr. Olivia Bennett' },
    { name: 'BA-2A', subject: subjects[2], year: 2, adviser: 'Dr. Michael Chen' },
    { name: 'BA-4A', subject: subjects[2], year: 4, adviser: 'Prof. Sarah Williams' },
    { name: 'CE-1A', subject: subjects[3], year: 1, adviser: 'Dr. Ethan Brooks' },
    { name: 'CE-3A', subject: subjects[3], year: 3, adviser: 'Prof. Amelia Cruz' },
];
const names = ['Sophia Chen', 'Liam Anderson', 'Isabella Garcia', 'Noah Williams', 'Olivia Martinez', 'Ethan Wilson', 'Amelia Thompson', 'Lucas Davis', 'Mia Robinson', 'Benjamin Lee', 'Charlotte Hall', 'James Walker', 'Ava Hernandez', 'Elijah Young', 'Harper King', 'Alexander Wright', 'Evelyn Scott', 'Daniel Green', 'Abigail Adams', 'Henry Baker', 'Emily Rivera', 'Sebastian Nelson', 'Sofia Mitchell', 'Jack Campbell'];
const initialStudents = names.map((name, index) => ({ key: `sample-${index + 1}`, studentId: `2026-${String(index + 1).padStart(5, '0')}`, name, subject: blocks[index % 8].subject, year: blocks[index % 8].year, block: blocks[index % 8].name, status: index % 7 === 5 ? 'Pending' : index % 11 === 9 ? 'Inactive' : 'Enrolled' }));
function readStorage(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
let students = readStorage('northbridge.students.v1', initialStudents);
if (Array.isArray(students)) {
    students = students.map(student => {
        if (!student || typeof student !== 'object') return student;
        const { course, ...record } = student;
        return { ...record, subject: record.subject ?? course };
    });
}
if (!Array.isArray(students) || !students.every(student => student && typeof student.key === 'string' && typeof student.studentId === 'string' && typeof student.name === 'string' && subjects.includes(student.subject) && blocks.some(block => block.name === student.block) && [1, 2, 3, 4].includes(Number(student.year)) && ['Enrolled', 'Pending', 'Inactive'].includes(student.status))) students = initialStudents;
function saveStorage(key, data) { try { localStorage.setItem(key, JSON.stringify(data)); return true; } catch { return false; } }
let toastTimer;
function toast(message) { const element = $('.toast'); element.textContent = message; element.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => element.hidden = true, 4200); }
const savedSettings = readStorage('northbridge.settings.v1', {});
if (typeof savedSettings.displayName === 'string') $('.profile strong').textContent = savedSettings.displayName;
if (savedSettings.notifications === false && $('.notification-button i')) $('.notification-button i').hidden = true;
$('.students-toggle').addEventListener('click', event => { const button = event.currentTarget; const expanded = button.getAttribute('aria-expanded') === 'true'; button.setAttribute('aria-expanded', String(!expanded)); $('#student-submenu').hidden = expanded; });
function closeNavigation() { $('#sidebar').classList.remove('open'); $('.sidebar-scrim').hidden = true; $('.mobile-menu').setAttribute('aria-expanded', 'false'); }
$('.mobile-menu').addEventListener('click', () => { $('#sidebar').classList.add('open'); $('.sidebar-scrim').hidden = false; $('.mobile-menu').setAttribute('aria-expanded', 'true'); $('.brand').focus(); });
$('.sidebar-scrim').addEventListener('click', closeNavigation);
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeNavigation(); if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { const search = $('#student-search') || $('.global-search input'); if (search && search.getClientRects().length) { event.preventDefault(); search.focus(); } } });
const options = (items, selected = '') => items.map(item => `<option value="${escapeHtml(item)}" ${String(item) === String(selected) ? 'selected' : ''}>${escapeHtml(item)}</option>`).join('');
const badge = status => `<span class="badge ${['Enrolled', 'Pending', 'Inactive'].includes(status) ? status.toLowerCase() : 'enrolled'}">${escapeHtml(status)}</span>`;
const initials = name => name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('');
const actionIcons = {
    view: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    edit: '<path d="m15 4 5 5M4 16 16 4a3 3 0 0 1 4 4L8 20l-5 1 1-5Z"/>',
    delete: '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
};
const actionButton = (action, student) => `<button type="button" class="row-action ${action}" data-action="${action}" data-key="${escapeHtml(student.key)}" title="${action[0].toUpperCase() + action.slice(1)} student" aria-label="${action} ${escapeHtml(student.name)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${actionIcons[action]}</svg></button>`;
if (page === 'students.enrolled') {
    let currentPage = 1;
    const perPage = 8;
    let editingKey = null;
    let deletingKey = null;
    let lastTrigger = null;
    const dialog = $('#student-dialog');
    const form = $('#student-form');
    const field = name => form.elements.namedItem(name);
    $('#subject-filter').insertAdjacentHTML('beforeend', options(subjects));
    $('#block-filter').insertAdjacentHTML('beforeend', options(blocks.map(block => block.name)));
    field('subject').innerHTML = options(subjects);
    $('#student-search').value = new URLSearchParams(location.search).get('q') || '';
    function render() {
        const query = $('#student-search').value.trim().toLowerCase();
        const filtered = students.filter(student => (!query || `${student.name} ${student.studentId}`.toLowerCase().includes(query)) && (!$('#subject-filter').value || student.subject === $('#subject-filter').value) && (!$('#year-filter').value || String(student.year) === $('#year-filter').value) && (!$('#block-filter').value || student.block === $('#block-filter').value) && (!$('#status-filter').value || student.status === $('#status-filter').value));
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        const start = (currentPage - 1) * perPage;
        $('#student-rows').innerHTML = filtered.slice(start, start + perPage).map((student, index) => `<tr><td>${escapeHtml(student.studentId)}</td><td><span class="student-name"><span class="student-avatar avatar-${index % 4}">${escapeHtml(initials(student.name))}</span>${escapeHtml(student.name)}</span></td><td>${escapeHtml(student.subject)}</td><td>Year ${escapeHtml(student.year)}</td><td>${escapeHtml(student.block)}</td><td>${badge(student.status)}</td><td><div class="row-actions">${Object.keys(actionIcons).map(action => actionButton(action, student)).join('')}</div></td></tr>`).join('');
        $('#student-empty').hidden = filtered.length > 0;
        $('#student-count').textContent = students.length;
        $('#pagination-summary').textContent = `Showing ${filtered.length ? start + 1 : 0}–${Math.min(start + perPage, filtered.length)} of ${filtered.length} students`;
        const pageNumbers = Array.from({ length: totalPages }, (_, i) => i + 1).filter(number => number === 1 || number === totalPages || Math.abs(number - currentPage) <= 1);
        $('#pagination-buttons').innerHTML = `<button class="page-button" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''} aria-label="Previous page">&#8592;</button>` + pageNumbers.map((number, index) => `${index > 0 && number - pageNumbers[index - 1] > 1 ? '<span aria-hidden="true">…</span>' : ''}<button class="page-button ${number === currentPage ? 'current' : ''}" data-page="${number}" aria-label="Page ${number}" ${number === currentPage ? 'aria-current="page"' : ''}>${number}</button>`).join('') + `<button class="page-button" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''} aria-label="Next page">&#8594;</button>`;
        const stats = $$('.student-stats .stat-bottom strong');
        [students.length, students.filter(student => student.status === 'Enrolled').length, students.filter(student => student.status === 'Pending').length, blocks.length].forEach((count, index) => stats[index].textContent = count);
    }
    function updateBlocks(selected = '') {
        const matching = blocks.filter(block => block.subject === field('subject').value && String(block.year) === field('year').value);
        field('block').innerHTML = matching.length ? options(matching.map(block => block.name), selected) : '<option value="">No block for this subject and year</option>';
    }
    ['subject', 'year'].forEach(name => field(name).addEventListener('change', () => updateBlocks()));
    function openStudent(action, key = null) {
        lastTrigger = document.activeElement;
        editingKey = key;
        form.reset();
        $('#student-error').textContent = '';
        const student = students.find(student => student.key === key);
        const viewing = action === 'view';
        $('#dialog-title').textContent = viewing ? 'Student information' : key ? 'Edit student' : 'Add student';
        $$('#student-form input, #student-form select').forEach(input => { input.disabled = viewing; if (student && input.name !== 'block') input.value = student[input.name]; });
        updateBlocks(student?.block);
        $('#save-student').hidden = viewing;
        dialog.showModal();
    }
    $('#add-student').addEventListener('click', () => openStudent('add'));
    $$('.close-dialog').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => lastTrigger?.focus());
    $('#student-rows').addEventListener('click', event => {
        const button = event.target.closest('[data-action]'); if (!button) return;
        if (button.dataset.action === 'delete') { deletingKey = button.dataset.key; lastTrigger = button; $('#delete-description').textContent = `Delete ${students.find(student => student.key === deletingKey).name}?`; $('#delete-dialog').showModal(); $('#cancel-delete').focus(); }
        else openStudent(button.dataset.action, button.dataset.key);
    });
    $('#cancel-delete').addEventListener('click', () => $('#delete-dialog').close());
    $('#delete-dialog').addEventListener('close', () => lastTrigger?.isConnected ? lastTrigger.focus() : $('#add-student').focus());
    $('#confirm-delete').addEventListener('click', () => {
        students = students.filter(student => student.key !== deletingKey);
        const saved = saveStorage('northbridge.students.v1', students);
        render(); $('#delete-dialog').close(); toast(saved ? 'Student record deleted.' : 'Deleted for this visit. Browser storage is unavailable.');
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(form));
        data.name = data.name.trim(); data.studentId = data.studentId.trim(); data.year = Number(data.year);
        if (!data.name || !data.studentId) { $('#student-error').textContent = 'Enter a name and student ID.'; return; }
        if (students.some(student => student.key !== editingKey && student.studentId.toLowerCase() === data.studentId.toLowerCase())) { $('#student-error').textContent = 'This student ID already exists. Enter a unique ID.'; field('studentId').focus(); return; }
        const studentBlock = blocks.find(block => block.name === data.block && block.subject === data.subject && block.year === data.year);
        if (!studentBlock) { $('#student-error').textContent = 'Select a block that matches the subject and year level.'; return; }
        const record = { ...data, key: editingKey || crypto.randomUUID() };
        if (editingKey) students = students.map(student => student.key === editingKey ? record : student); else students.unshift(record);
        const saved = saveStorage('northbridge.students.v1', students);
        if (!editingKey) { $$('.filters select, .filters input').forEach(input => input.value = ''); currentPage = 1; }
        render(); dialog.close(); toast(saved ? (editingKey ? 'Student information updated.' : 'Student added successfully.') : 'Saved for this visit. Browser storage is unavailable.');
    });
    $$('.filters input, .filters select').forEach(input => input.addEventListener(input.tagName === 'INPUT' ? 'input' : 'change', () => { currentPage = 1; render(); }));
    $$('.reset-filters').forEach(button => button.addEventListener('click', () => { $$('.filters select, .filters input').forEach(input => input.value = ''); currentPage = 1; render(); }));
    $('#pagination-buttons').addEventListener('click', event => { const button = event.target.closest('[data-page]'); if (button && !button.disabled) { currentPage = Number(button.dataset.page); render(); } });
    render();
}
function tableHead(labels) { return `<tr>${labels.map(label => `<th scope="col">${escapeHtml(label)}</th>`).join('')}</tr>`; }
function tableRow(cells) { return `<tr>${cells.map(cell => `<td>${cell}</td>`).join('')}</tr>`; }
if (page === 'students.blocks' || page === 'students.schedule') {
    $('#section-filter').insertAdjacentHTML('beforeend', options(blocks.map(block => block.name)));
    const subjects = ['Introduction to Computing', 'Data Structures', 'Web Technologies', 'Systems Administration', 'Business Analytics', 'Strategic Management', 'Engineering Mathematics', 'Structural Analysis'];
    function renderSections() {
        const filtered = blocks.filter(block => !$('#section-filter').value || block.name === $('#section-filter').value);
        const schedule = page === 'students.schedule';
        $('#section-head').innerHTML = tableHead(schedule ? ['Day', 'Time', 'Subject', 'Block', 'Room', 'Instructor'] : ['Block name', 'Subject', 'Year level', 'Adviser', 'Students', 'Status']);
        $('#section-rows').innerHTML = filtered.map(block => {
            const index = blocks.indexOf(block);
            return tableRow(schedule ? [index % 2 ? 'Tuesday / Thursday' : 'Monday / Wednesday', index % 3 ? '10:00 AM – 11:30 AM' : '8:00 AM – 9:30 AM', subjects[index], block.name, `Room ${201 + index}`, block.adviser].map(escapeHtml) : [block.name, block.subject, `Year ${block.year}`, block.adviser, String(students.filter(student => student.block === block.name).length)].map(escapeHtml).concat(badge('Active')));
        }).join('');
    }
    $('#section-filter').addEventListener('change', renderSections); renderSections();
}
if (['faculty', 'subjects', 'reports'].includes(page)) {
    const datasets = {
        faculty: { head: ['Faculty member', 'Department', 'Assigned block', 'Status'], rows: blocks.map(block => [block.adviser, block.subject, block.name, 'Active']) },
        subjects: { head: ['Subject code', 'Subject name', 'Program duration', 'Blocks', 'Status'], rows: subjects.map((subject, index) => [['BSCS', 'BSIT', 'BSBA', 'BSCE'][index], subject, '4 years', '2', 'Active']) },
        reports: { head: ['Report', 'Period', 'Students', 'Status'], rows: subjects.map(subject => [subject + ' enrollment', 'Fall 2026', String(students.filter(student => student.subject === subject).length), 'Available']) },
    };
    const dataset = datasets[page];
    $('#catalog-head').innerHTML = tableHead(dataset.head);
    $('#catalog-rows').innerHTML = dataset.rows.map(row => tableRow(row.map((cell, index) => index === row.length - 1 ? badge(cell) : escapeHtml(cell)))).join('');
    $('#download-report')?.addEventListener('click', () => {
        const csv = [dataset.head, ...dataset.rows].map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a'); link.href = url; link.download = 'northbridge-fall-2026-enrollment.csv'; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); toast('Enrollment report exported.');
    });
}
if (page === 'settings') {
    const form = $('#settings-form');
    for (const [key, value] of Object.entries(savedSettings)) { const input = form.elements.namedItem(key); if (input) { if (input.type === 'checkbox') input.checked = Boolean(value); else input.value = value; } }
    form.addEventListener('submit', event => { event.preventDefault(); const data = { ...Object.fromEntries(new FormData(form)), notifications: form.elements.notifications.checked }; const saved = saveStorage('northbridge.settings.v1', data); $('.profile strong').textContent = data.displayName; if ($('.notification-button i')) $('.notification-button i').hidden = !data.notifications; toast(saved ? 'Your preferences have been saved.' : 'Browser storage is unavailable. Preferences apply for this visit.'); });
}
