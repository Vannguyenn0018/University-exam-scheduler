import pandas as pd

def export_schedule(df):

    # Export the DataFrame to an Excel file
    df.to_excel("initial_exam_schedule.xlsx", index=False)
print("Initial exam schedule exported to initial_exam_schedule.xlsx")