import sqlite3
import pandas as pd
import random
from datetime import datetime, timedelta

from chromosome import Gene, Chromosome

# --- 1. Scheduler Class to load data from DB ---
class Scheduler:
    def __init__(self, db_file="exam_scheduling.db"):
        self.db_file = db_file
        self.sections = {} # {section_id: num_students}
        self.section_details = {} # {section_id: {'course_name': '...', 'num_students': ...}}
        self.rooms = {} # {room_id: capacity}
        self.timeslots = [] # List of timeslot_ids
        self.timeslot_details = {} # {timeslot_id: {'exam_date': '...', 'start_time': '...', 'end_time': '...'}}
        self.enrollments = {}
        self._load_data()

    def _load_data(self):
        conn = None
        try:
            conn = sqlite3.connect(self.db_file)
            cursor = conn.cursor()

            # Load Course Sections and Course Names, and count enrolled students
            cursor.execute("SELECT cs.section_id, c.course_name FROM CourseSection cs JOIN Course c ON cs.course_id = c.course_id;")
            section_course_names = {row[0]: row[1] for row in cursor.fetchall()}

            cursor.execute("SELECT section_id FROM CourseSection;")
            all_section_ids = [row[0] for row in cursor.fetchall()]

            for section_id in all_section_ids:
                cursor.execute("SELECT COUNT(student_id) FROM Enrollment WHERE section_id = ?;", (section_id,))
                num_students = cursor.fetchone()[0]
                self.sections[section_id] = num_students
                self.section_details[section_id] = {
                    'course_name': section_course_names.get(section_id, 'N/A'),
                    'num_students': num_students
                }

            # Load Exam Rooms
            cursor.execute("SELECT room_id, capacity FROM ExamRoom;")
            for room_id, capacity in cursor.fetchall():
                self.rooms[room_id] = capacity

            # Load Timeslots details
            cursor.execute("SELECT timeslot_id, exam_date, start_time, end_time FROM Timeslot;")
            for timeslot_id, exam_date, start_time, end_time in cursor.fetchall():
                self.timeslots.append(timeslot_id) # Keep list of IDs for random choice
                self.timeslot_details[timeslot_id] = {
                    'exam_date': exam_date,
                    'start_time': start_time,
                    'end_time': end_time
                }

            # Load Enrollments (for student conflict check)
            cursor.execute("SELECT student_id, section_id FROM Enrollment;")
            for student_id, section_id in cursor.fetchall():
                if student_id not in self.enrollments:
                    self.enrollments[student_id] = []
                self.enrollments[student_id].append(section_id)

        except sqlite3.Error as e:
            print(f"Database error during data loading: {e}")
        finally:
            if conn:
                conn.close()


# --- 2. Generate and Insert Timeslots ---
def generate_and_insert_timeslots(db_file="exam_scheduling.db", num_days=5, times_per_day=3):
    conn = None
    try:
        conn = sqlite3.connect(db_file)
        cursor = conn.cursor()

        # Delete existing timeslots to avoid duplicates if run multiple times
        cursor.execute("DELETE FROM Timeslot;")

        start_date = datetime(2024, 6, 1)
        current_timeslot_id = 1
        for day in range(num_days):
            exam_date = (start_date + timedelta(days=day)).strftime('%Y-%m-%d')
            for slot in range(times_per_day):
                start_time = (datetime(1,1,1, 8,0) + timedelta(hours=slot*3)).strftime('%H:%M') # 8:00, 11:00, 14:00
                end_time = (datetime(1,1,1, 10,0) + timedelta(hours=slot*3)).strftime('%H:%M') # 10:00, 13:00, 16:00
                timeslot_id = f"TS_{current_timeslot_id:03d}"
                cursor.execute("INSERT INTO Timeslot (timeslot_id, exam_date, start_time, end_time) VALUES (?, ?, ?, ?)",
                               (timeslot_id, exam_date, start_time, end_time))
                current_timeslot_id += 1
        conn.commit()
        print(f"Generated and inserted {current_timeslot_id-1} timeslots into the database.")
    except sqlite3.Error as e:
        print(f"Database error during timeslot generation: {e}")
    finally:
        if conn:
            conn.close()

# Call the function to generate and insert timeslots
generate_and_insert_timeslots()

# Initialize the scheduler after timeslots are populated
scheduler = Scheduler()

# --- 3. Function to generate an initial random Chromosome (Schedule) ---
def generate_initial_chromosome(scheduler):
    chromosome = Chromosome()

    # Define courses that require Building A
    building_a_course_names = [
        'An toàn bảo mật thông tin trong kinh doanh',
        'Phân tích dữ liệu cho tài chính',
        'Trực quan hóa dữ liệu'
    ]
    # Assuming foreign language courses start with 'CNL' or 'ELI' in section_id
    building_a_prefixes = ['CNL', 'ELI']

    # Separate rooms by building for easier selection.
    rooms_A_pool = {room_id: capacity for room_id, capacity in scheduler.rooms.items() if room_id.startswith('A')}
    rooms_C_pool = {room_id: capacity for room_id, capacity in scheduler.rooms.items() if room_id.startswith('C')}

    for section_id, num_students in scheduler.sections.items():
        # Determine target building for the section
        course_name = scheduler.section_details.get(section_id, {}).get('course_name', '')

        target_building = None
        if course_name in building_a_course_names:
            target_building = 'A'
        elif any(section_id.startswith(prefix) for prefix in building_a_prefixes):
            target_building = 'A'
        else:
            target_building = 'C'

        eligible_rooms = rooms_A_pool if target_building == 'A' else rooms_C_pool
        
        # If no eligible rooms in the target pool, use all rooms as a fallback
        if not eligible_rooms:
            print(f"Warning: No rooms found for target building {target_building} for section {section_id}. Falling back to any available room.")
            eligible_rooms = scheduler.rooms 
            
        
        # Randomly pick a timeslot
        timeslot_id = random.choice(scheduler.timeslots)

        assigned_rooms = []
        current_assigned_capacity = 0

        # Shuffle eligible room IDs to pick randomly
        shuffled_room_ids = list(eligible_rooms.keys())
        random.shuffle(shuffled_room_ids)

        # Assign rooms until total capacity covers number of students
        for room_id in shuffled_room_ids:
            if current_assigned_capacity >= num_students:
                break 
            assigned_rooms.append(room_id)
            current_assigned_capacity += eligible_rooms[room_id] # Use actual room capacity

        # Ensure at least one room is assigned if num_students > 0
        if not assigned_rooms and num_students > 0:
            if shuffled_room_ids:
                assigned_rooms = [random.choice(shuffled_room_ids)]
            else: # Absolutely no rooms available, even after fallback
                print(f"Critical Warning: No rooms available at all for section {section_id}. Assigning dummy room.")
                assigned_rooms = ['NO_ROOM_ASSIGNED'] # Placeholder for debugging if no rooms

        chromosome.add_gene(Gene(section_id, timeslot_id, assigned_rooms))
    return chromosome

# Generate an initial schedule
initial_schedule = generate_initial_chromosome(scheduler)
print(f"\nGenerated initial schedule with {len(initial_schedule.genes)} genes.")

# --- 4. Display the schedule ---
def display_schedule(chromosome, scheduler):
    schedule_list = []
    for gene in chromosome.genes:
        section_id = gene.section_id
        timeslot_id = gene.timeslot_id

        course_name = scheduler.section_details.get(section_id, {}).get('course_name', 'N/A')
        num_students = scheduler.sections.get(section_id, 'N/A')

        timeslot_info = scheduler.timeslot_details.get(timeslot_id, {})
        exam_date = timeslot_info.get('exam_date', 'N/A')
        start_time = timeslot_info.get('start_time', 'N/A')
        end_time = timeslot_info.get('end_time', 'N/A')

        exam_time_range = f"{start_time}-{end_time}"

        # Determine 'Ca thi' based on start time
        if start_time == '08:00':
            session_name = 'Ca1'
        elif start_time == '11:00':
            session_name = 'Ca2'
        elif start_time == '14:00':
            session_name = 'Ca3'
        else:
            session_name = 'N/A'

        schedule_list.append({
            'Mã LHP': section_id,
            'Tên LHP': course_name,
            'Sĩ số SV': num_students,
            'Ngày thi': exam_date,
            'Giờ thi': exam_time_range, # Renamed from 'Ca thi'
            'Ca thi': session_name, # New column
            'Phòng thi': ', '.join(gene.room_ids)
        })
    df_schedule = pd.DataFrame(schedule_list)
    return df_schedule

# Display the initial schedule
df_initial_schedule = display_schedule(initial_schedule, scheduler)
print("\n--- Initial Exam Schedule ---")
print(df_initial_schedule.to_string())
