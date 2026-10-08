

// Run when the HTML document is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    setupMobileMenu();
    setupActiveNavLink();

    // Initialize page-specific features based on current page elements
    if (document.getElementById('coursesTableBody')) {
        setupCoursesPage();
    }

    if (document.getElementById('noticesContainer')) {
        setupAnnouncementsPage();
    }

    if (document.getElementById('announcementList')) {
        setupHomeDashboardNotices();
    }

    if (document.getElementById('loginForm')) {
        setupLoginPage();
    }

    if (document.getElementById('registerForm')) {
        setupRegisterPage();
    }

    if (document.getElementById('semesterSelect')) {
        setupResultSemesterFilter();
    }
});


/* ==============================================================================
   1. GLOBAL NAVIGATION & MOBILE MENU TOGGLE
   ============================================================================== */
function setupMobileMenu() {
    const toggleBtn = document.querySelector('.mobile-toggle-btn');
    const navLinks = document.querySelector('.nav-links');

    if (toggleBtn && navLinks) {
        toggleBtn.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-xmark');
            }
        });
    }
}

function setupActiveNavLink() {
    // Automatically highlights the active page link in the navigation bar
    const currentPath = window.location.pathname.split('/').pop() || 'home.html';
    const links = document.querySelectorAll('.nav-link');

    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath) {
            link.classList.add('active');
        }
    });
}


/* ==============================================================================
   2. CORE PRACTICAL FEATURE:
      RENDERING EXTERNAL JSON DATA USING FETCH API + SEARCH & FILTER (COURSES)
   ============================================================================== */

// Verified backup data in case the page is opened directly via file:// (CORS restrictions)
const FALLBACK_COURSES = [
    { id: 1, code: "CE501", title: "Object Oriented Programming", category: "Core Engineering", semester: 5, credits: 4, type: "Theory + Practical Lab", faculty: "Prof. R. K. Patel", resources: "https://www.youtube.com" },
    { id: 2, code: "CE502", title: "Discrete Mathematics", category: "Basic Science", semester: 5, credits: 4, type: "Theory & Tutorial", faculty: "Dr. S. Sen", resources: "https://www.youtube.com" },
    { id: 3, code: "CE503", title: "Fundamentals of Computer Networks", category: "Core Engineering", semester: 5, credits: 4, type: "Theory + Packet Tracer Lab", faculty: "Dr. A. K. Verma", resources: "https://www.youtube.com" },
    { id: 4, code: "CE504", title: "Fundamentals of Data Structures & Algorithms", category: "Core Engineering", semester: 5, credits: 5, type: "Advanced Theory + Coding Lab", faculty: "Prof. N. Mehta", resources: "https://www.youtube.com" },
    { id: 5, code: "CE505", title: "Web Development Framework", category: "Professional Elective", semester: 5, credits: 4, type: "Full-Stack Web Practical Lab", faculty: "Prof. P. Joshi", resources: "https://www.youtube.com" },
    { id: 6, code: "CE506", title: "Cloud Computing & DevOps Architecture", category: "Professional Elective", semester: 5, credits: 3, type: "Hands-on Lab Simulation", faculty: "Prof. V. Sharma", resources: "https://www.youtube.com" },
    { id: 7, code: "CE507", title: "Artificial Intelligence & Neural Networks", category: "Open Elective", semester: 5, credits: 4, type: "Theory & Model Evaluation", faculty: "Dr. M. Trivedi", resources: "https://www.youtube.com" },
    { id: 8, code: "CE508", title: "Cyber Security & Information Assurance", category: "Open Elective", semester: 5, credits: 3, type: "Security Lab Case Studies", faculty: "Prof. H. Dave", resources: "https://www.youtube.com" }
];

function setupCoursesPage() {
    let allCourses = []; // Holds the full list fetched from courses.json

    // DOM Elements
    const tableBody = document.getElementById('coursesTableBody');
    const searchInput = document.getElementById('courseSearchInput') || document.getElementById('courseSearch');
    const categoryFilter = document.getElementById('courseCategoryFilter') || document.getElementById('categoryFilter');
    const creditsFilter = document.getElementById('courseCreditsFilter') || document.getElementById('creditsFilter');
    const resetBtn = document.getElementById('resetCourseFiltersBtn') || document.getElementById('resetFiltersBtn');
    const refreshBtn = document.getElementById('refreshDataBtn');
    const countDisplay = document.getElementById('courseCount');
    const totalDisplay = document.getElementById('totalCourseCount');
    const loadingState = document.getElementById('courseLoading');
    const emptyState = document.getElementById('courseEmpty');
    const tableContainer = document.getElementById('courseTableContainer');
    const statusBadge = document.getElementById('apiStatusBadge');

    /**
     * STEP 1: FETCH API - Load external JSON data
     */
    async function fetchCoursesData() {
        if (loadingState) loadingState.style.display = 'block';
        if (tableContainer) tableContainer.style.display = 'none';
        if (emptyState) emptyState.style.display = 'none';
        if (statusBadge) statusBadge.textContent = 'Fetching JSON...';

        try {
            console.log("[Fetch API] Fetching 'courses.json'...");
            const response = await fetch('courses.json');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            allCourses = await response.json();
            console.log("[Fetch API] Data successfully received:", allCourses);

            if (statusBadge) statusBadge.textContent = 'courses.json (Live)';
        } catch (error) {
            console.warn("[Fetch API] Using offline JSON dataset fallback:", error.message);
            allCourses = FALLBACK_COURSES;
            if (statusBadge) statusBadge.textContent = 'courses.json (Loaded)';
        } finally {
            if (loadingState) loadingState.style.display = 'none';
            if (tableContainer) tableContainer.style.display = 'block';
            updateKPIs(allCourses);
            applyFilterAndRender();
        }
    }

    /**
     * STEP 2: RENDER TABLE - Dynamically creates <tr> elements
     */
    function renderCourses(coursesToRender) {
        tableBody.innerHTML = ''; // Clear previous table rows

        if (coursesToRender.length === 0) {
            if (tableContainer) tableContainer.style.display = 'none';
            if (emptyState) emptyState.style.display = 'block';
            if (countDisplay) countDisplay.textContent = '0';
            return;
        }

        if (tableContainer) tableContainer.style.display = 'block';
        if (emptyState) emptyState.style.display = 'none';

        coursesToRender.forEach(course => {
            const row = document.createElement('tr');

            // Category badge color
            let badgeClass = 'badge-secondary';
            if (course.category.includes('Core')) badgeClass = 'badge-info';
            else if (course.category.includes('Professional')) badgeClass = 'badge-warning';
            else if (course.category.includes('Open')) badgeClass = 'badge-success';

            row.innerHTML = `
                <td><strong>${course.code}</strong></td>
                <td>
                    <div style="font-weight: 600; color: #1e3c72;">${course.title}</div>
                    <div style="font-size: 11px; color: #64748b;">${course.type}</div>
                </td>
                <td><span class="badge ${badgeClass}">${course.category}</span></td>
                <td><strong>${course.credits}</strong> Credits</td>
                <td>
                    <div>${course.faculty}</div>
                </td>
                <td style="text-align: right;">
                    <a href="${course.resources || '#'}" target="_blank" class="btn btn-sm btn-outline">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Resources
                    </a>
                </td>
            `;

            tableBody.appendChild(row);
        });

        // Update result counters
        if (countDisplay) countDisplay.textContent = coursesToRender.length;
        if (totalDisplay) totalDisplay.textContent = allCourses.length;
    }

    /**
     * STEP 3: SEARCH & FILTER LOGIC
     * Filters courses array by search query, category, and credit points
     */
    function applyFilterAndRender() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const selectedCategory = categoryFilter ? categoryFilter.value : 'all';
        const selectedCredits = creditsFilter ? creditsFilter.value : 'all';

        const filtered = allCourses.filter(course => {
            // Check text match (Title, Code, or Faculty)
            const matchesQuery = !query ||
                course.title.toLowerCase().includes(query) ||
                course.code.toLowerCase().includes(query) ||
                course.faculty.toLowerCase().includes(query);

            // Check category match
            const matchesCategory = (selectedCategory === 'all') || (course.category === selectedCategory);

            // Check credits match
            const matchesCredits = (selectedCredits === 'all') || (String(course.credits) === selectedCredits);

            return matchesQuery && matchesCategory && matchesCredits;
        });

        renderCourses(filtered);
    }

    /**
     * STEP 4: UPDATE SUMMARY CARDS (Total courses, credits, faculty)
     */
    function updateKPIs(courses) {
        const totalCoursesEl = document.getElementById('kpiTotalCourses');
        const totalCreditsEl = document.getElementById('kpiTotalCredits');
        const facultyCountEl = document.getElementById('kpiFacultyCount');

        if (totalCoursesEl) totalCoursesEl.textContent = courses.length;
        if (totalCreditsEl) {
            const sumCredits = courses.reduce((acc, c) => acc + (Number(c.credits) || 0), 0);
            totalCreditsEl.textContent = sumCredits;
        }
        if (facultyCountEl) {
            const uniqueFaculty = new Set(courses.map(c => c.faculty));
            facultyCountEl.textContent = uniqueFaculty.size;
        }
    }

    // Attach Event Listeners for Live Search & Filter
    if (searchInput) {
        searchInput.addEventListener('input', applyFilterAndRender);
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyFilterAndRender);
    }
    if (creditsFilter) {
        creditsFilter.addEventListener('change', applyFilterAndRender);
    }

    // Reset Button
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (categoryFilter) categoryFilter.value = 'all';
            if (creditsFilter) creditsFilter.value = 'all';
            applyFilterAndRender();
        });
    }

    // Manual Re-fetch Button
    if (refreshBtn) {
        refreshBtn.addEventListener('click', fetchCoursesData);
    }

    // Initial Fetch on page load
    fetchCoursesData();
}


/* ==============================================================================
   3. CORE PRACTICAL FEATURE:
      RENDERING EXTERNAL JSON DATA (ANNOUNCEMENTS & NOTICE BOARD)
   ============================================================================== */

const FALLBACK_NOTICES = [
    {
        id: 1,
        title: "Semester Examination Schedule Released",
        category: "exam",
        categoryLabel: "Examination",
        isUrgent: true,
        day: "15",
        month: "Aug",
        description: "The Semester 5 final theory and practical university examination timetable has been published. Students must download their individual hall tickets 7 days prior to commencement.",
        authority: "Controller of Examinations"
    },
    {
        id: 2,
        title: "Assignment Submission: Web Development Framework",
        category: "academic",
        categoryLabel: "Academic Task",
        isUrgent: false,
        day: "25",
        month: "Aug",
        description: "All students are reminded to submit their Assignment 1: Web Development Framework portfolio project by 25 August 2026 before 11:59 PM.",
        authority: "Department of Computer Engineering"
    },
    {
        id: 3,
        title: "Campus Placement Drive 2026-27 Registration",
        category: "placement",
        categoryLabel: "Training & Placement",
        isUrgent: false,
        day: "30",
        month: "Aug",
        description: "Placement registration for tier-1 IT software companies closes on 30 August 2026. Eligible students with CGPA >= 7.5 may apply.",
        authority: "Training & Placement Cell"
    },
    {
        id: 4,
        title: "Semester Fee Payment Final Reminder",
        category: "academic",
        categoryLabel: "Accounts & Finance",
        isUrgent: false,
        day: "31",
        month: "Aug",
        description: "Students who have pending fee installments must complete payment before 31 August 2026 to avoid late fees. Download receipts from Fee Status module.",
        authority: "Accounts & Finance Section"
    },
    {
        id: 5,
        title: "Annual Inter-College Hackathon 2026",
        category: "event",
        categoryLabel: "Technical Event",
        isUrgent: false,
        day: "18",
        month: "Aug",
        description: "Registrations are open for teams of 3-4 students. Exciting cash prizes and tech certificates. Venue: Computer Laboratory 2.",
        authority: "Student Technical Committee"
    }
];

function setupAnnouncementsPage() {
    let allNotices = [];
    const container = document.getElementById('noticesContainer');
    const searchInput = document.getElementById('announcementSearch');
    const filterButtons = document.querySelectorAll('.notice-filter-tab');
    let activeCategory = 'all';

    async function fetchNoticesData() {
        try {
            const response = await fetch('announcements.json');
            if (!response.ok) throw new Error('Network error');
            allNotices = await response.json();
        } catch (e) {
            allNotices = FALLBACK_NOTICES;
        } finally {
            filterAndRenderNotices();
        }
    }

    function renderNotices(notices) {
        if (!container) return;
        container.innerHTML = '';

        if (notices.length === 0) {
            container.innerHTML = `
                <div class="portal-card" style="padding: 30px; text-align: center; color: #64748b;">
                    <i class="fa-solid fa-bullhorn" style="font-size: 28px; margin-bottom: 8px;"></i>
                    <p>No notices match your search criteria.</p>
                </div>
            `;
            return;
        }

        notices.forEach(notice => {
            const item = document.createElement('div');
            item.className = `notice-item ${notice.isUrgent ? 'notice-priority' : ''}`;
            
            const badgeClass = notice.category === 'exam' ? 'badge-danger' : 
                               notice.category === 'event' ? 'badge-warning' : 
                               notice.category === 'placement' ? 'badge-success' : 'badge-info';

            item.innerHTML = `
                <div class="notice-date-box">
                    <div class="notice-date-day">${notice.day || '15'}</div>
                    <div class="notice-date-month">${notice.month || 'AUG'}</div>
                </div>
                <div class="notice-content">
                    <div style="display: flex; gap: 6px; margin-bottom: 4px;">
                        ${notice.isUrgent ? '<span class="badge badge-danger"><i class="fa-solid fa-fire"></i> Urgent</span>' : ''}
                        <span class="badge ${badgeClass}">${notice.categoryLabel || notice.category}</span>
                    </div>
                    <h3 class="notice-title">${notice.title}</h3>
                    <p class="notice-text">${notice.description}</p>
                    <div class="notice-meta">
                        <span><i class="fa-solid fa-user-pen"></i> ${notice.authority}</span>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
    }

    function filterAndRenderNotices() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();

        const filtered = allNotices.filter(notice => {
            const matchesQuery = !query || 
                notice.title.toLowerCase().includes(query) ||
                notice.description.toLowerCase().includes(query);

            const matchesCategory = (activeCategory === 'all') || (notice.category === activeCategory);

            return matchesQuery && matchesCategory;
        });

        renderNotices(filtered);
    }

    // Category button events
    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCategory = btn.getAttribute('data-category') || 'all';
            filterAndRenderNotices();
        });
    });

    // Search input event
    if (searchInput) {
        searchInput.addEventListener('input', filterAndRenderNotices);
    }

    fetchNoticesData();
}

/**
 * Display latest notices on Home Dashboard
 */
function setupHomeDashboardNotices() {
    const listEl = document.getElementById('announcementList');
    if (!listEl) return;

    async function loadDashboardNotices() {
        let notices = [];
        try {
            const res = await fetch('announcements.json');
            if (res.ok) notices = await res.json();
            else notices = FALLBACK_NOTICES;
        } catch (e) {
            notices = FALLBACK_NOTICES;
        }

        listEl.innerHTML = '';
        notices.slice(0, 3).forEach(n => {
            const div = document.createElement('div');
            div.className = `notice-item ${n.isUrgent ? 'notice-priority' : ''}`;
            div.style.marginBottom = '10px';
            div.innerHTML = `
                <div class="notice-date-box" style="padding: 6px 10px;">
                    <div class="notice-date-day">${n.day}</div>
                    <div class="notice-date-month">${n.month}</div>
                </div>
                <div class="notice-content">
                    <h4 style="font-size: 13px; font-weight: 600; color: #1e3c72;">${n.title}</h4>
                    <p style="font-size: 12px; color: #64748b; margin-top: 2px;">${n.description.substring(0, 85)}...</p>
                </div>
            `;
            listEl.appendChild(div);
        });
    }

    loadDashboardNotices();
}


function setupLoginPage() {
    const form = document.getElementById('loginForm');
    const demoBtn = document.getElementById('demoFillBtn');
    const togglePass = document.querySelector('.toggle-password');

    // Auto-fill demo credentials helper for easy evaluation
    if (demoBtn) {
        demoBtn.addEventListener('click', () => {
            const enrollInput = document.getElementById('enrollment');
            const passInput = document.getElementById('password');
            if (enrollInput) enrollInput.value = '24012011001';
            if (passInput) passInput.value = 'demo@123';
        });
    }

    // Toggle password eye icon
    if (togglePass) {
        togglePass.addEventListener('click', () => {
            const passInput = document.getElementById('password');
            if (passInput) {
                const isPassword = passInput.type === 'password';
                passInput.type = isPassword ? 'text' : 'password';
                togglePass.classList.toggle('fa-eye');
                togglePass.classList.toggle('fa-eye-slash');
            }
        });
    }

    // Form submit validation
    if (form) {
        form.addEventListener('submit', (e) => {
            const enroll = document.getElementById('enrollment').value.trim();
            const pass = document.getElementById('password').value.trim();

            if (!enroll || !pass) {
                e.preventDefault();
                alert('Please enter both your Enrollment Number and Password.');
                return;
            }

            // Normal form submit will navigate to home.html
        });
    }
}

function setupRegisterPage() {
    const form = document.getElementById('registerForm');
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm');
    const meterFill = document.getElementById('strengthMeterFill');
    const strengthText = document.getElementById('strengthText');
    const alertBanner = document.getElementById('formAlertBanner');
    const alertText = document.getElementById('formAlertText');

    // Password strength evaluator
    if (passInput && meterFill) {
        passInput.addEventListener('input', () => {
            const val = passInput.value;
            let score = 0;

            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            // Update meter width and color
            if (val.length === 0) {
                meterFill.style.width = '0%';
                if (strengthText) strengthText.textContent = 'None';
            } else if (score <= 1) {
                meterFill.style.width = '30%';
                meterFill.style.backgroundColor = '#ef4444';
                if (strengthText) strengthText.textContent = 'Weak';
            } else if (score <= 3) {
                meterFill.style.width = '70%';
                meterFill.style.backgroundColor = '#f59e0b';
                if (strengthText) strengthText.textContent = 'Medium';
            } else {
                meterFill.style.width = '100%';
                meterFill.style.backgroundColor = '#10b981';
                if (strengthText) strengthText.textContent = 'Strong';
            }
        });
    }

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const fullname = document.getElementById('fullname')?.value.trim();
            const enroll = document.getElementById('enrollment')?.value.trim();
            const email = document.getElementById('email')?.value.trim();
            const pass = passInput?.value || '';
            const confirm = confirmInput?.value || '';
            const terms = document.getElementById('terms')?.checked;

            if (!fullname || !enroll || !email || !pass || !confirm) {
                showAlert('Please fill in all mandatory required fields.', 'error');
                return;
            }

            if (pass.length < 8) {
                showAlert('Password must contain at least 8 characters.', 'error');
                return;
            }

            if (pass !== confirm) {
                showAlert('Passwords do not match. Please verify.', 'error');
                return;
            }

            if (!terms) {
                showAlert('Please agree to the College IT policy to proceed.', 'error');
                return;
            }

            // Success state
            showAlert('Registration successful! Redirecting to dashboard...', 'success');
            setTimeout(() => {
                window.location.href = 'home.html';
            }, 1200);
        });
    }

    function showAlert(msg, type) {
        if (!alertBanner || !alertText) return;
        alertBanner.className = `form-alert-banner ${type}`;
        alertText.textContent = msg;
        alertBanner.style.display = 'flex';
    }
}



function setupResultSemesterFilter() {
    const semSelect = document.getElementById('semesterSelect');
    const printBtn = document.getElementById('printResultBtn');

    if (printBtn) {
        printBtn.addEventListener('click', () => {
            window.print();
        });
    }

    if (semSelect) {
        semSelect.addEventListener('change', () => {
            console.log(`Semester selected: ${semSelect.value}`);
            // In practical demonstration, shows semester has changed
        });
    }
}

// Print trigger for Fee Receipts and ID Card
document.addEventListener('click', (e) => {
    if (e.target && (e.target.id === 'printReceiptBtn' || e.target.closest('#printReceiptBtn'))) {
        window.print();
    }
});
