import sqlite3

DB_PATH = "Data/exam_scheduling.db"

def get_connection():
    return sqlite3.connect(DB_PATH)
