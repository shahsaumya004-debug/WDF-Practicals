
INSERT INTO students (full_name, enrollment_number, email, phone, department, semester)
VALUES
('Aarav Mehta', 'STU2026001', 'aarav.mehta@college.edu', '9876500001', 'Computer Science', 'Semester 1'),
('Ananya Sharma', 'STU2026002', 'ananya.sharma@college.edu', '9876500002', 'Computer Science', 'Semester 1'),
('Vikram Singh', 'STU2026003', 'vikram.singh@college.edu', '9876500003', 'Computer Science', 'Semester 2'),
('Diya Patel', 'STU2026004', 'diya.patel@college.edu', '9876500004', 'Information Technology', 'Semester 2'),
('Rohan Gupta', 'STU2026005', 'rohan.gupta@college.edu', '9876500005', 'Information Technology', 'Semester 3'),
('Pooja Verma', 'STU2026006', 'pooja.verma@college.edu', '9876500006', 'Electronics', 'Semester 3'),
('Karan Nair', 'STU2026007', 'karan.nair@college.edu', '9876500007', 'Electronics', 'Semester 4'),
('Neha Jain', 'STU2026008', 'neha.jain@college.edu', '9876500008', 'Mechanical', 'Semester 4'),
('Rajesh Iyer', 'STU2026009', 'rajesh.iyer@college.edu', '9876500009', 'Mechanical', 'Semester 5'),
('Sneha Reddy', 'STU2026010', 'sneha.reddy@college.edu', '9876500010', 'Civil', 'Semester 5'),
('Ishita Das', 'STU2026011', 'ishita.das@college.edu', '9876500011', 'Civil', 'Semester 6'),
('Mohit Kapoor', 'STU2026012', 'mohit.kapoor@college.edu', '9876500012', 'Computer Science', 'Semester 1'),
('Tanya Shah', 'STU2026013', 'tanya.shah@college.edu', '9876500013', 'Computer Science', 'Semester 2'),
('Aditya Joshi', 'STU2026014', 'aditya.joshi@college.edu', '9876500014', 'Information Technology', 'Semester 2'),
('Meera Kulkarni', 'STU2026015', 'meera.kulkarni@college.edu', '9876500015', 'Information Technology', 'Semester 3'),
('Harsh Vora', 'STU2026016', 'harsh.vora@college.edu', '9876500016', 'Electronics', 'Semester 3'),
('Sakshi Pawar', 'STU2026017', 'sakshi.pawar@college.edu', '9876500017', 'Electronics', 'Semester 4'),
('Yash Malhotra', 'STU2026018', 'yash.malhotra@college.edu', '9876500018', 'Mechanical', 'Semester 4'),
('Nisha Rao', 'STU2026019', 'nisha.rao@college.edu', '9876500019', 'Civil', 'Semester 5'),
('Dev Khanna', 'STU2026020', 'dev.khanna@college.edu', '9876500020', 'Civil', 'Semester 5')
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    phone = VALUES(phone),
    department = VALUES(department),
    semester = VALUES(semester);
