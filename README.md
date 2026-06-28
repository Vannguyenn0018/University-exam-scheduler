# GA-Based University Exam Scheduling System

A university exam scheduling system that automatically generates conflict-free examination timetables using a Genetic Algorithm (GA). The system schedules examinations at the course section level, allocates examination rooms based on capacity, and generates personalized examination schedules for students.

---

## Overview

Manual examination scheduling is a complex and time-consuming process. Administrators must satisfy numerous constraints, including:

* Student examination conflicts
* Course section scheduling
* Room capacity
* Available timeslots
* Room allocation for large classes

This project applies a Genetic Algorithm to optimize the examination scheduling process while minimizing conflicts and satisfying institutional constraints.

---

## Features

* Automatic exam scheduling using Genetic Algorithm
* Course section-based scheduling
* Student conflict detection
* Multi-room allocation for large course sections
* Automatic student exam assignment
* Excel report generation
* SQLite database integration

---

## Genetic Algorithm Workflow

1. Load input data from the SQLite database.
2. Generate the initial population.
3. Evaluate chromosomes using the fitness function.
4. Perform selection.
5. Apply crossover.
6. Apply mutation.
7. Repair invalid solutions.
8. Repeat until the stopping criterion is met.
9. Store the best solution.
10. Generate examination schedules and export results.

---

## Database Design

The system consists of the following entities:

* Student
* Course
* Course Section
* Enrollment
* Timeslot
* Exam Room
* Exam Schedule
* Schedule Room
* Student Exam Assignment

### Scheduling Workflow

```text
Student
    │
Enrollment
    │
Course Section
    │
Genetic Algorithm
    │
Exam Schedule
    │
Schedule Room
    │
Student Exam Assignment
```

---

## Project Structure

```text
university-exam-scheduler
│
├── data/
│   ├── university_exam_management.db
│
├── docs/
│   ├── ERD.png
│   ├── Workflow.png
│   └── Report.pdf
│
├── outputs/
│   ├── exam_schedule.xlsx
│   └── student_exam_schedule.xlsx
│
├── screenshots/
│
├── src/
│   ├── scheduler.py
│   ├── chromosome.py
│   ├── crossover.py
│   ├── mutation.py
│   ├── repair.py
│   ├── fitness.py
│   └── selection.py
│
├── requirements.txt
└── README.md
```

---

## Technologies

* Python
* SQLite
* Genetic Algorithm
* Pandas
* OpenPyXL

---

## Installation

Clone the repository:

```bash
git clone https://github.com/your-username/university-exam-scheduler.git
```

Install the required packages:

```bash
pip install -r requirements.txt
```

Run the application:

```bash
python scheduler.py
```

---

## Outputs

The system generates:

* University examination schedule
* Student examination schedule
* Room allocation
* Excel reports

---

## Screenshots

Add screenshots of:

* User Interface
* Entity Relationship Diagram (ERD)
* Generated Examination Schedule
* Student Examination Schedule

---

## Future Work

* Web-based scheduling interface
* Invigilator assignment
* Multi-semester scheduling
* Additional optimization strategies
* Performance improvement for large datasets

---

## Author

**Vannguyenn0018**

Business Data Science Student

Ho Chi Minh City Banking University

---

## License

This project was developed for educational and research purposes.
