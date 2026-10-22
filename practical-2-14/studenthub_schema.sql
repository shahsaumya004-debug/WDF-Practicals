-- StudentHub database schema
-- Entities: students, events, registrations

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS students (
    student_id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    enrollment_number TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    department TEXT,
    semester TEXT,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
    event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    event_date TEXT NOT NULL,
    venue TEXT NOT NULL,
    organizer TEXT,
    capacity INTEGER,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed', 'cancelled')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    event_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'registered' CHECK (status IN ('registered', 'attended', 'cancelled')),
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_students_email ON students(email);
CREATE INDEX IF NOT EXISTS idx_students_enrollment ON students(enrollment_number);
CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_registrations_student ON registrations(student_id);
CREATE INDEX IF NOT EXISTS idx_registrations_event ON registrations(event_id);

-- Example data
INSERT INTO students (full_name, enrollment_number, email, phone, department, semester, password_hash)
VALUES
    ('Rahul Sharma', '24012011001', 'rahul.sharma@apex.edu.in', '9876543210', 'Computer Engineering', 'Semester 5', 'hashed_password_1'),
    ('Priya Patel', '24012011002', 'priya.patel@apex.edu.in', '9123456780', 'Information Technology', 'Semester 6', 'hashed_password_2');

INSERT INTO events (title, description, event_date, venue, organizer, capacity, status)
VALUES
    ('Tech Fest 2026', 'Annual technology festival with coding contests and workshops.', '2026-11-12', 'Main Auditorium', 'Student Council', 250, 'open'),
    ('Career Guidance Seminar', 'Placement and internship preparation session for final-year students.', '2026-11-05', 'Seminar Hall A', 'Training & Placement Cell', 120, 'open');

INSERT INTO registrations (student_id, event_id, status)
VALUES
    (1, 1, 'registered'),
    (2, 2, 'registered');
