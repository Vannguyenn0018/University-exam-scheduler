import sqlite3

DB_PATH = r"C:\Users\HP\Downloads\exam_scheduling.db"

def get_connection():
    return sqlite3.connect(DB_PATH)