<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AJAX Event CRUD</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f7fb;
            color: #1f2937;
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 18px 40px;
        }
        .panel {
            background: white;
            border: 1px solid #dde5f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        h2 { margin-top: 0; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        label {
            font-size: 13px;
            font-weight: 700;
        }
        input, select, textarea, button {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #dfe8f3;
            font-size: 14px;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        button {
            cursor: pointer;
            background: #1f3c88;
            color: white;
            border: none;
            font-weight: 700;
        }
        .secondary {
            background: #eaf0ff;
            color: #1f3c88;
            border: 1px solid #d4e0ff;
        }
        .danger {
            background: #d93025;
            color: white;
        }
        .status {
            margin-bottom: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            display: none;
            font-weight: 600;
        }
        .status.success {
            display: block;
            background: #eaf8ef;
            color: #166534;
            border: 1px solid #bfe6c9;
        }
        .status.error {
            display: block;
            background: #fdeaea;
            color: #991b1b;
            border: 1px solid #f2b7b7;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #e6edf5;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        .poster {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dfe8f3;
        }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .small-btn {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }
        .small-btn.edit { background: #eaf0ff; color: #1f3c88; }
        .small-btn.delete { background: #fdeaea; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="panel">
            <h2>AJAX Event CRUD</h2>
            <div id="statusBox" class="status"></div>
            <form id="eventForm">
                <input type="hidden" id="event_id" name="event_id">
                <div class="grid">
                    <div class="field">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="field">
                        <label for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>
                    <div class="field">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" required>
                    </div>
                    <div class="field">
                        <label for="organizer">Organizer</label>
                        <input type="text" id="organizer" name="organizer">
                    </div>
                    <div class="field">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" min="1">
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="field" style="margin-top:14px;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required></textarea>
                </div>
                <div class="field" style="margin-top:14px; max-width: 260px;">
                    <label for="poster">Poster URL</label>
                    <input type="text" id="poster" name="poster" placeholder="https://example.com/poster.jpg">
                </div>
                <div style="display:flex; gap:10px; margin-top:18px; flex-wrap:wrap;">
                    <button type="submit" id="submitBtn">Add Event</button>
                    <button type="button" class="secondary" id="resetBtn">Reset</button>
                    <a href="admin_dashboard.php" style="text-decoration:none; color:#1f3c88; font-weight:700; display:inline-block; padding:10px 12px; border-radius:8px; background:#eef4ff; border:1px solid #d8e4ff;">Back to Dashboard</a>
                </div>
            </form>
        </div>

        <div class="panel">
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
                <input type="text" id="searchInput" placeholder="Search event by title, venue or organizer" style="flex:1; min-width:220px;">
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button id="loadBtn" class="secondary">Refresh</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Poster</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="eventTableBody"></tbody>
            </table>
        </div>
    </div>

    <script>
        const statusBox = document.getElementById('statusBox');
        const eventForm = document.getElementById('eventForm');
        const eventTableBody = document.getElementById('eventTableBody');
        const submitBtn = document.getElementById('submitBtn');
        const resetBtn = document.getElementById('resetBtn');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const loadBtn = document.getElementById('loadBtn');

        function setStatus(message, type = 'success') {
            statusBox.textContent = message;
            statusBox.className = 'status ' + type;
        }

        function resetForm() {
            eventForm.reset();
            document.getElementById('event_id').value = '';
            submitBtn.textContent = 'Add Event';
        }

        async function fetchEvents() {
            const search = searchInput.value.trim();
            const filterStatus = statusFilter.value;

            try {
                const params = new URLSearchParams();
                if (search) params.append('search', search);
                if (filterStatus) params.append('status', filterStatus);

                const res = await fetch('event_api.php?' + params.toString(), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Failed to load events.');
                }

                renderEvents(data.data.events || []);
            } catch (error) {
                setStatus(error.message, 'error');
                eventTableBody.innerHTML = '<tr><td colspan="6">Unable to load events.</td></tr>';
            }
        }

        function renderEvents(events) {
            if (!events.length) {
                eventTableBody.innerHTML = '<tr><td colspan="6">No events found.</td></tr>';
                return;
            }

            eventTableBody.innerHTML = events.map(event => `
                <tr>
                    <td>
                        ${event.poster ? `<img src="${event.poster}" alt="Poster" class="poster">` : '—'}
                    </td>
                    <td>${event.title}</td>
                    <td>${event.event_date}</td>
                    <td>${event.venue}</td>
                    <td>${event.status}</td>
                    <td>
                        <div class="actions">
                            <button type="button" class="small-btn edit" data-id="${event.event_id}">Edit</button>
                            <button type="button" class="small-btn delete" data-id="${event.event_id}">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        eventForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(eventForm);
            const payload = {
                action: document.getElementById('event_id').value ? 'update' : 'add',
                event_id: document.getElementById('event_id').value || null,
                title: formData.get('title'),
                description: formData.get('description'),
                event_date: formData.get('event_date'),
                venue: formData.get('venue'),
                organizer: formData.get('organizer'),
                capacity: formData.get('capacity'),
                status: formData.get('status'),
                poster: formData.get('poster')
            };

            try {
                const res = await fetch('event_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();
                if (!res.ok) {
                    throw new Error(result.message || 'Request failed.');
                }

                setStatus(result.message, 'success');
                resetForm();
                await fetchEvents();
            } catch (error) {
                setStatus(error.message, 'error');
            }
        });

        eventTableBody.addEventListener('click', async (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;

            const id = Number(target.dataset.id);
            if (!id) return;

            if (target.classList.contains('edit')) {
                const res = await fetch('event_api.php?search=' + encodeURIComponent(''), { method: 'GET' });
                const result = await res.json();
                const event = (result.data.events || []).find(item => Number(item.event_id) === id);
                if (!event) return;

                document.getElementById('event_id').value = event.event_id;
                document.getElementById('title').value = event.title;
                document.getElementById('event_date').value = event.event_date;
                document.getElementById('venue').value = event.venue;
                document.getElementById('organizer').value = event.organizer || '';
                document.getElementById('capacity').value = event.capacity || '';
                document.getElementById('status').value = event.status || 'open';
                document.getElementById('description').value = event.description || '';
                document.getElementById('poster').value = event.poster || '';
                submitBtn.textContent = 'Update Event';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            if (target.classList.contains('delete')) {
                const deleteRes = await fetch('event_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: 'delete', event_id: id })
                });

                const deleteResult = await deleteRes.json();
                if (!deleteRes.ok) {
                    setStatus(deleteResult.message || 'Delete failed', 'error');
                    return;
                }

                setStatus(deleteResult.message, 'success');
                await fetchEvents();
            }
        });

        searchInput.addEventListener('input', () => {
            clearTimeout(searchInput._timer);
            searchInput._timer = setTimeout(fetchEvents, 300);
        });

        statusFilter.addEventListener('change', fetchEvents);
        loadBtn.addEventListener('click', fetchEvents);
        resetBtn.addEventListener('click', resetForm);

        fetchEvents();
    </script>
</body>
</html>
